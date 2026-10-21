# Phase 3 validation

Executed 2026-10-21 using isolated Docker WordPress/MariaDB containers and an independent network/volume. Existing website containers were not used or modified. No WooCommerce, form plugin, custom table, local dataset or sync cron was installed by Phase 3.

## Phase 2 checkpoint and commit

Before editing Phase 3 code, inspected the full status/stat and intended Phase 2 paths, checked for actual secret values and ran the complete existing validation: 18 REST comparisons, 42 core checks, foundation, lifecycle, requirements, live HTTP and 22-file PHP syntax. PHP 8.1/WordPress 6.3.7 and PHP 8.3/WordPress 7.1.3 passed.

Committed Phase 2 separately as `712a6e7` — `refactor(core): extract address service architecture`. Working tree was clean before Phase 3. No push or credential/remote changes were made.

## Public Contract Tests

- REST response schema unchanged: **YES**.
- Baseline fixture changed: **NO**.
- Namespace, four routes, request parameters, no-fallback error code/message/status/data, search, sorting and health: unchanged.
- Legacy facade public signatures/constants and entrypoint: unchanged.
- Outage + valid LKG intentionally returns HTTP 200 with the identical success schema, without `stale`/`source` fields.

Frozen fixture SHA-256, unchanged from Phase 1/2:

```text
436C9637F0444BACC719BEAB0057BDCBE8A5DC49AF0CE4EA8A979B792BCE5E62
```

## Existing Tests Updated

- `Contract/RestV1ContractTest.php`: uses the shared CacheState helper to clear active/LKG/negative/failure/lease state before each no-fallback error scenario, and to restore state afterwards, including before reporting a failure. No expected public result or fixture entry was edited.
- `Unit/CoreTest.php`: keeps 42 named checks. Replaced exact active-only write assertions with validated active/LKG outcomes, replaced acceptance of empty raw detail caching with rejection, verified short failure markers instead of no internal state, and verified corrupt active data refresh. HTTP error public mapping and all schema/signature/search/sort/health checks remain protected.
- `Support/CacheState.php`: isolates only explicitly named resources, preserving unrelated transients. Legacy direct-call cases seed their own active payload after cleanup instead of relying on earlier tests.

These changes were explicitly authorized by the Phase 3 test compatibility clarification; they reflect intentional internal reliability behavior, not relaxed public schema checks.

## Results

| Check | WordPress 6.3.7 / PHP 8.1.34 CLI | WordPress 7.1.3 / PHP 8.3.35 |
| --- | --- | --- |
| Frozen REST comparisons | 18/18 PASS | 18/18 PASS |
| Existing core checks, internal assertions updated | 42/42 PASS | 42/42 PASS |
| New reliability checks | 60/60 PASS | 60/60 PASS |
| Foundation suite | PASS | PASS |
| Actual activate/deactivate/reactivate | PASS | PASS |
| All 28 PHP files: syntax | PASS | PASS |
| WP_DEBUG / WP_DEBUG_LOG | No PHP errors | No PHP errors |

Total named core/reliability tests: **42 existing + 60 new = 102**. REST comparisons are reported separately. Existing standalone requirements checks also passed before the Phase 2 commit on PHP 8.3 and actual PHP 7.4; requirement/bootstrap files were not modified in Phase 3.

## Reliability Tests

All network access in CoreTest/ReliabilityTest is mocked. Expiration/slow failures use a simulated clock, not sleeps; actual option-backed lease operations are also exercised.

- Provider schema: nonempty/unique province list, missing/empty names, missing/boolean/array/overflow codes, numeric-string codes, requested province mismatch, invalid/missing/null wards, duplicate wards and parent mismatch.
- Optional fields: valid empty wards and missing optional parent/codename retain defaults; no fixed province/ward counts.
- Cache: validated hits avoid HTTP/writes, invalid active is refreshed, validated active/LKG writes, independent write failures do not fail current valid data.
- LKG: invalid remote never overwrites good backup or extends retention; corrupt backup is not served.
- Outages: timeout, DNS, 500, 503, 429, invalid JSON/schema with expired active return LKG without retry, and repeat requests use backoff without more HTTP.
- No fallback: legacy transport error response remains identical.
- Negative cache: detail HTTP 404 uses one attempt, preserves the legacy 502 error and suppresses calls until expiry. 500 never becomes long-lived not-found state.
- Retry: fast 503 followed by 200 succeeds and fills both caches; slow timeout is not repeated; 400/404/429 are not synchronously retried; attempt filters cannot exceed two.
- Lock: contention serves LKG without HTTP; cold contention is controlled; success/failure/exception release; database acquisition excludes another owner even with stale WordPress negative option cache, reclaims expired/malformed leases and prevents an old owner releasing a successor.
- Policy: safe TTL/filter types and lower bounds, maximum attempts, clamped numeric/date Retry-After and 429 internal header/classification without public data changes.
- REST fallback: provinces, wards and province detail each return exactly their original successful body after active removal and simulated outage.
- Diagnostics: first failure/fallback emits redacted events; repeated marker hits do not log every request.

## Live Smoke Results

Optional live smoke was run separately against the PHP 8.3 WordPress HTTP server using the original Province Open API:

| GET | Result |
| --- | --- |
| `/vn-address/v1/provinces` | HTTP 200, keys/list/record types PASS |
| `/vn-address/v1/wards?province_code=79` | HTTP 200, keys/list/record types PASS |
| `/vn-address/v1/province/79` | HTTP 200, keys/detail/count types PASS |
| `/vn-address/v1/health` | HTTP 200, exact legacy payload PASS |

Live availability is not required by the offline suites. Existing valid Phase 2 active cache remained readable. Mocked cache-miss tests specifically prove validation-before-write and creation of LKG.

## WP_DEBUG and source review

WP_DEBUG and WP_DEBUG_LOG enabled in both WordPress installations. Intentional failure diagnostics are prefixed `[VN Address Pro diagnostic]`, distinct from PHP Warning/Notice/Deprecated/Fatal/uncaught errors. ReliabilityTest captures the diagnostic action while disabling that PHP-log sink to avoid intentional log spam. No new PHP errors were observed.

Syntax, whitespace, scope and obvious-secret checks passed before the Phase 3 commit. Changes are limited to provider/HTTP reliability, support classes, authorized test isolation/internal assertions and documentation. No fixture, core mapping, service, normalizer, search, controller, legacy facade, metadata or production autoloader changes were necessary. Final debug logs contained zero PHP error entries; the latest inspection recorded 12 and 28 clearly prefixed intentional application diagnostics in the PHP 8.3 and minimum-version installations respectively.

## Limits and deferred work

- LKG uses transients, which may be evicted early; this is not an offline dataset/database and cannot guarantee survival of cache flushes or outages beyond retention.
- Existing valid active entries without a backup gain LKG at the next successful remote refresh; no mass cache clear or cold upgrade is forced.
- Lock correctness assumes normal WordPress options-table uniqueness and a writable database. Expired rows are reclaimed on demand, not by cron.
- No persistent Redis/Memcached or full multisite test matrix; locks intentionally use the existing database rather than backend-specific cache atomicity.
- Cold contention returns a v1-shaped 502 rather than waiting. Retry timing remains subject to WordPress HTTP transport/redirect behavior; a slow failure is not doubled.
- Failure state is per resource, not a global circuit breaker/rate limiter. Static city classification and legacy HTTP error mapping remain.
- PHPStan/PHPCS are not configured; no runtime packages were added.

Phase 4 recommendation: design a versioned local address dataset/repository and its migration/update policy. No Phase 4 implementation was started.

Architecture and limits: [cache-reliability.md](cache-reliability.md). Commands: [tests/README.md](../tests/README.md).
