# Provider reliability and cache resilience

Phase 3 remains development of plugin 2.0.0, PHP 8.1+ and WordPress 6.3+. No custom tables, local dataset, external package, Redis dependency, background job, admin UI or integration is introduced.

## Flow and ownership

```text
AddressService
      ↓
ProvinceOpenApiProvider
      ↓
Validate active cache ─ valid → return
      ↓ miss/invalid (invalid entry is deleted/ignored)
Validate LKG + read negative/failure marker
      ├── marker → LKG or original mapped error, no HTTP
      ↓
Acquire expiring refresh lease
      ├── busy → validated active/LKG or controlled v1-shaped 502, no waiting
      ↓ owner
Recheck active/markers → one HTTP attempt → validate raw payload
      ├── valid → write active + LKG → clear failure state → return
      └── failure → optional bounded retry → marker → LKG or legacy error
Finally release only the owned lease
```

Provider owns all state and retry policy. AddressService, Controller, Normalizer and Search do not know lock keys, HTTP statuses, backoff or Retry-After. HttpClient performs exactly one HTTP attempt. `ReliabilityPolicy` centralizes time limits and filters. `ProvinceOpenApiValidator` owns provider-specific schema/relationship checks.

## Validation

- Province response must be a nonempty list of record arrays; no fixed province count.
- Required province/ward fields: positive integer-compatible `code` and nonempty string `name`.
- Integer codes, digit strings (including leading zero formatting) and integral positive floats are accepted; booleans, arrays, fractional/overflow/negative codes are rejected. Normalized v1 output is still integer.
- Province codes are unique after integer normalization. Ward codes within a detail are also unique.
- Detail code must equal the requested code. Its `wards` field must be an array/list; `[]` is valid and no minimum ward count is imposed. Missing/null/non-array wards is a malformed remote detail and cannot replace active/LKG data.
- If an optional ward `province_code` is present/non-null, it must match the parent. If absent/null, the original output default 0 remains; it is not inferred.
- Other optional fields retain the existing generic validator/normalizer defaults. No new upstream field is required merely because an example happens to contain it.

The provider validates active and LKG reads, not just HTTP responses. Malformed Phase 2 cache is removed/ignored and refresh/fallback attempted. Only validated remote data updates LKG; reading active cache or serving fallback never extends its retention. Existing valid raw active entries remain usable without a cache reset. An old active entry without LKG gains a backup after its next successful remote refresh, not by treating the old entry as a new remote success.

No schema-version envelope or key migration is required because raw data shape is retained and revalidated. HTTP 200 + empty province list is invalid. A detail payload with empty/missing province code still maps to legacy not-found when there is no fallback, but is never stored as active/LKG data.

## Runtime keys and defaults

`ProvinceOpenApiProvider::runtime_keys(code)` is the single naming location. With no code, suffix is `provinces_v2`; with a code it is `province_{code}_depth2`.

| State | Key | Default lifetime |
| --- | --- | --- |
| Active | `vn_address_{suffix}` | 24 hours, unchanged |
| LKG | `vnap_lkg_{suffix}` | 7 days |
| Detail 404 | `vnap_negative_{suffix}` | 10 minutes |
| Failure marker | `vnap_provider_failure_{suffix}` | 30 seconds |
| Refresh lease | option `vnap_refresh_lock_{md5(active_key)}` | 30 seconds, logical expiry |

Active and LKG store raw validated arrays. Error markers store only the original internal code/message/data needed to reconstruct the existing `WP_Error`; they are never interpreted as address records. Markers are per resource, not a global outage circuit breaker. Repeated marker hits do not refresh TTL or make another request.

Only a detail endpoint's HTTP 404 uses negative-cache semantics; HTTP 404 on the list uses short failure backoff. HTTP 500, transport/DNS/timeout, invalid JSON/schema and 429 never become not-found markers. If LKG is present, both negative and failure states prefer that valid backup until its retention expires. A valid refresh clears both markers.

A failed active/LKG write does not fail a valid current response. An unchanged value may cause WordPress to return false from an update; it is only diagnosed as a write problem if a subsequent read does not match the desired value.

## Refresh lock correctness

`RefreshLock` uses non-autoloaded options in WordPress's existing per-site options table. It does not use a get-transient/set-transient sequence as a distributed mutex.

- Initial acquisition uses prepared `INSERT IGNORE` and the unique option-name constraint, never an upsert. WordPress `add_option()` can upsert after a stale negative option-cache read, so it is deliberately not used as a mutex.
- Expired or malformed leases are replaced by a database compare-and-swap on the exact old JSON value.
- Every lease includes a random UUID and expiry. Release deletes only a matching owner value, so an expired worker cannot release its successor's lease.
- Direct SQL reads/CAS/release use `$wpdb->options` and prepared values. Relevant option caches are invalidated after changes.
- Works without persistent object cache and does not depend on Redis-specific atomicity. It assumes the normal WordPress writable database and unique options schema.
- A crashed worker's option row is not proactively deleted; it becomes reclaimable on the next acquisition after logical expiry. No cleanup cron is created.

Contending requests never sleep or poll. They serve validated active/LKG if available; without either, `vnap_cache_lock` maps through the existing generic v1 invalid-payload 502 boundary, without exposing new response fields or stack traces.

## Retry and backoff

Each HTTP attempt retains timeout **15 seconds**, redirects **3**, original Accept/User-Agent and fixed provider URLs. No REST input supplies a remote URL.

Maximum two attempts: initial plus one retry. Retry is eligible only for transport errors or HTTP 500/502/503/504, without LKG, when elapsed time since refresh began is below **0.5 seconds**. A 15-second timeout is therefore not repeated; a fast failure followed by a full attempt is approximately bounded by the existing attempt timeout plus that short eligibility window, subject to WordPress HTTP transport/redirect behavior. No blocking sleep or jitter is used.

With LKG, the first refresh failure immediately falls back and sets a failure marker; subsequent requests serve LKG without waiting for HTTP. 400, 404, invalid JSON/schema and 429 are not synchronously retried. 429 uses a separate short backoff: Retry-After seconds or HTTP date is parsed and clamped to **15–300 seconds**, default **60 seconds**. It never becomes a permanent not-found condition.

## Filters

Filters receive only the default integer, no request URL or personal data. Only integer return values are accepted; other types use the default. Integers are clamped to the ranges below, so zero/negative values cannot create unbounded retention or loops.

| Hook | Default | Bounds |
| --- | --- | --- |
| `vnap_cache_ttl` | 86400 | 60–86400 seconds |
| `vnap_lkg_ttl` | 604800 | 172800–2592000 seconds |
| `vnap_negative_cache_ttl` | 600 | 300–900 seconds |
| `vnap_failure_ttl` | 30 | 15–300 seconds |
| `vnap_refresh_lock_ttl` | 30 | 30–60 seconds |
| `vnap_provider_max_attempts` | 2 | 1–2 attempts |

The default legacy active TTL is unchanged. LKG minimum exceeds active maximum. The 429 Retry-After range is a fixed safety bound; it overrides ordinary failure TTL for that response. Internal retry window remains fixed.

## Errors and diagnostics

Internal codes stay compatible with Phase 2: `vnap_transport_error`, `vnap_remote_http_error`, `vnap_invalid_payload`, `vnap_not_found`, plus `vnap_cache_lock`. HTTP error data adds internal classification (`http_404`, `http_429`, `http_5xx`, etc.); decode errors distinguish invalid JSON/root and provider validation distinguishes province mismatch/schema. The existing v1 ErrorMapper ignores these internal details and keeps its original public code/message/status/data keys.

`do_action('vnap_diagnostic', $event, $context)` reports notable events: `provider_failure`, `lkg_fallback`, `invalid_cached_payload`, `cache_write_failure`. Context is limited to resource key, internal error code/classification and status. It does not include bodies, names, addresses, phone/email, arbitrary error messages, secrets or server paths. Normal active hits and repeated backoff/fallback hits do not emit diagnostics.

With WP_DEBUG enabled, events also use `error_log` prefixed `[VN Address Pro diagnostic]`. No default PHP logging when WP_DEBUG is false. `vnap_log_to_error_log` can return false to disable that sink while retaining the action hook. Intentional failure tests use the hook; application diagnostics are distinct from PHP warnings/notices/fatals.

## Compatibility and limitations

REST routes/parameters/schemas, no-fallback error mapping, search/sort and health remain frozen. Fallback intentionally turns an outage into an ordinary HTTP 200 response with the same data schema; no `stale` or `source` field is added. Version stays 2.0.0; the legacy facade and its public signatures/constants are unchanged.

LKG is a transient, not an offline database: WordPress/object cache may evict it earlier than TTL. No cache/LKG plus upstream failure still returns the original mapped error. Long outages beyond retention may exhaust fallback. Cold lock contention returns an error instead of queueing workers. No proactive refresh, global rate limit, local repository, provider status UI or dataset migration is implemented. Static city classification is unchanged.

Validation results: [phase3-validation.md](phase3-validation.md).
