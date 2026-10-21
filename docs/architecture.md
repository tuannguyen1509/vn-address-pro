# Core architecture — Phase 3

Phase 3 remains development of plugin 2.0.0. No database migration, cache reset, reactivation, provider reconfiguration or release-version bump is required. The main filename stays `vn-address-api.php`.

```text
WordPress REST v1             VN_Address_API_2025 (public facade)
        │                                │
        └──────────────┬─────────────────┘
                       ↓
              Rest\V1\AddressController
                       ↓
                AddressService
                 ├── Normalizer
                 ├── Validator
                 ├── Search
                 └── AddressProviderInterface
                             ↓
                 ProvinceOpenApiProvider
                      ├── CacheInterface → AddressCache → Transients
                      └── HttpClient → WordPress HTTP API → Province Open API
```

## Bootstrap and composition

The Phase 1 entrypoint, early PHP/WordPress requirements gate, constants and namespace-only PSR-4 loader are unchanged. `Plugin` is the composition root: it creates one service and one controller, wires their dependencies once, loads the facade and fires `vnap_loaded` once. No HTTP request occurs during construction or activation.

The existing `rest_api_init` callback identity `[VN_Address_API_2025, register_routes]` remains registered so existing `has_action()`/`remove_action()` calls continue to work. That callback delegates to the controller, which owns all route declarations. It registers at most once per REST server instance, including if the facade's registration method is called repeatedly.

Textdomain loading, `vnap_version` checkpoint and lifecycle behavior remain from Phase 1. There are no new options, cron events, tables or frontend assets.

## Layer responsibilities

| Layer | Responsibility |
| --- | --- |
| Controller | Read v1 request parameters, preserve schemas/methods/permissions, call service, produce responses |
| ErrorMapper | Map internal WP_Error codes to frozen v1 messages, statuses and error data |
| AddressService | Normalize, filter and sort application results; check payload usability and not-found branch |
| AddressProviderInterface | Raw province list or raw province detail/ward context; array or WP_Error |
| ProvinceOpenApiProvider | Fixed URLs, validated active/LKG state, negative/failure markers and retry coordination |
| ProvinceOpenApiValidator | Required provider fields, unique codes and requested/returned/ward parent consistency |
| ReliabilityPolicy | Safe TTL/attempt filters, short retry window and bounded Retry-After |
| RefreshLock | Ownership-safe expiring leases in the existing options table |
| Logger | Redacted diagnostic action and optional WP_DEBUG logging |
| CacheInterface / AddressCache | Read/write/delete transients; false exclusively means miss |
| HttpClient | wp_remote_get, transport/status checks and JSON-array decoding; no address mapping or provider URL |
| Normalizer | Legacy field mapping, prefixes, scalar defaults and static city classification |
| Validator | List/record/consumed-field shape checks and v1 input coercion |
| Search | Unchanged case-insensitive, accent-sensitive substring search |
| Legacy facade | Keep original class/constants/public signatures and forward to controller |

## Data and error contracts between layers

Provider methods return raw arrays or `WP_Error`, never REST responses. `get_wards(code)` intentionally returns the same raw province-detail structure as `get_province(code)`, including parent fields and `wards`, so v1 can include parent context without an extra HTTP call. This requirement is documented in the interface; a future provider adapts its native format to that shape.

Service results:

- `get_provinces(search)`: normalized province list.
- `get_wards(code, search)`: `province` record and `wards` list, without the REST envelope.
- `get_province(code)`: normalized province with `wards` and `ward_count` in original upstream order.

Core service code receives integer province codes. Controller compatibility coercion still uses `absint()`, including its permissive negative/string behavior; search keeps trimming and route-level `sanitize_text_field()`. Direct legacy calls retain their original bypass of route-level sanitization, but non-scalar searches now safely become empty strings instead of emitting cast warnings.

All expected failures use `WP_Error`; there is no domain exception hierarchy, null/error-array convention or boolean error result. Cache `false` is an explicitly separate miss sentinel; validator booleans are shape predicates.

| Internal code | v1 code | HTTP |
| --- | --- | --- |
| `vnap_transport_error` | `vn_address_upstream_error`, original `detail` field | 502 |
| `vnap_remote_http_error` | `vn_address_upstream_http_error`, original `upstream_status` | 502, including upstream 404/429/500 |
| `vnap_invalid_payload` | `vn_address_invalid_json` | 502 |
| `vnap_not_found` | `vn_address_not_found` | 404 |

Unknown internal error codes map to the safe invalid-payload v1 error; internal messages are not exposed. Frozen Vietnamese v1 labels/messages are intentionally copied unchanged and not translated dynamically, preserving the existing public contract. New internal error strings are translation-ready. No stack trace is returned.

## Cache flow and upgrade compatibility

Only the provider owns cache coordination. It validates legacy active keys before use, checks LKG/short markers on a miss, acquires a refresh lease, then performs HTTP and provider validation **before** writing active/LKG data. Invalid active data is deleted/ignored and cannot be served. Invalid remote data never replaces LKG. The service retains its generic defensive validation and builds application results after provider retrieval.

Default active TTL and raw format remain unchanged. LKG has longer retention. 404 negative state, transient-failure backoff and bounded retry are infrastructure behavior, not public REST fields. See [cache-reliability.md](cache-reliability.md) for exact keys, lease algorithm, filters and policy bounds.

```text
Provider → validated active ── hit → return
                   ↓ miss
          LKG / markers / refresh lease
                   ↓ owner
           HTTP → provider validation
                  ↙          ↘
                valid       failure
                  ↓          ↓
             active + LKG   LKG or v1-mapped error
```

## Validation boundaries and deliberate compatibility

- Province lists must be list-shaped arrays of record arrays. Consumed fields must be scalar or absent/null; optional field defaults are preserved.
- Detail with empty/missing code still follows the original not-found branch before ward validation.
- Generic service/normalizer defaults still map missing/null wards to `[]` for other provider implementations; Province Open API detail is now required to contain an array `wards` before caching. Empty array remains valid for wards, not for the province dataset.
- Ward `province_code` remains 0 if absent; no parent inference or code-matching rule was introduced.
- Malformed non-scalar records/fields and invalid ward collections are rejected without cast warnings/TypeError. This protects unsupported malformed payloads; valid v1 payloads remain unchanged.
- Provider validation checks required positive integer-compatible codes, uniqueness and parent matching. V1 request coercion, accent behavior, locale-independent ordering and static city classification remain unchanged.

Public routes remain GET and unauthenticated. Remote URLs are fixed inside the provider; no REST argument or option supplies an arbitrary URL to HttpClient. The client is injectable for tests but is not exposed as a public proxy.

## Legacy and provisional access

The facade preserves `NS`, `UPSTREAM`, `CACHE_TTL`, `init(): void`, `register_routes(): void`, and all three untyped-return public request callbacks with their original `WP_REST_Request` parameter. Constants reference the owning core classes to prevent duplicated configuration. Private legacy helpers were replaced by the dedicated components; they were not public APIs.

`Plugin::instance()->address_service()` and `rest_controller()` are provisional internal accessors. Dependency construction occurs only in Plugin; tests may instantiate AddressService with another `AddressProviderInterface` and an in-memory `CacheInterface`. There is no production provider switcher or stable developer facade yet.

## Verification and deferred work

The frozen baseline fixture is unchanged. The 42 existing core checks retain public assertions and update superseded cache expectations. Another 60 reliability checks cover validation/fallback/retry/locking with mocks, simulated time and actual option leases. Run instructions: [tests](../tests/README.md). Results: [Phase 3 validation](phase3-validation.md).

Deferred: local repository/dataset, sync, versioned public schemas/error semantics, full multisite/external-cache matrix and integrations. Phase 4 has not started.
