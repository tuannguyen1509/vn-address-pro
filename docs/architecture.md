# Core architecture — Phase 6

Phase 5 remains development of plugin 2.0.0. Schema/dataset versions are independent of the release. Tables/data install on activation or the existing init upgrade checkpoint; no reactivation or remote configuration is required. The main filename stays `vn-address-api.php`. Sync/admin hooks register once from Plugin; no remote sync runs on frontend init.

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
                 └── AddressRepositoryInterface
                             ↓
                 FallbackAddressRepository
                      ├── LocalAddressRepository → per-site WordPress tables
                      └── RemoteAddressRepository → ProvinceOpenApiProvider
                      ├── CacheInterface → AddressCache → Transients
                      └── HttpClient → WordPress HTTP API → Province Open API
```

## Bootstrap and composition

The Phase 1 entrypoint, early PHP/WordPress requirements gate, constants and namespace-only PSR-4 loader are unchanged. `Plugin` is the composition root: it creates one service and one controller, wires their dependencies once, loads the facade and fires `vnap_loaded` once. No HTTP request occurs during construction or activation.

The existing `rest_api_init` callback identity `[VN_Address_API_2025, register_routes]` remains registered so existing `has_action()`/`remove_action()` calls continue to work. That callback delegates to the controller, which owns all route declarations. It registers at most once per REST server instance, including if the facade's registration method is called repeatedly.

Textdomain loading and release checkpoint remain. The checkpoint additionally runs a cheap schema/dataset version check before its unchanged-release early return. Missing/older data triggers validated bundled installation. No cron or frontend assets are introduced; data tables/options are described in [database-schema.md](database-schema.md).

## Layer responsibilities

| Layer | Responsibility |
| --- | --- |
| Controller | Read v1 request parameters, preserve schemas/methods/permissions, call service, produce responses |
| ErrorMapper | Map internal WP_Error codes to frozen v1 messages, statuses and error data |
| AddressService | Normalize, filter and sort application results; check payload usability and not-found branch |
| AddressProviderInterface | Raw province list or raw province detail/ward context; array or WP_Error |
| AddressRepositoryInterface | Provider-compatible record reads plus get_ward and installed dataset usability |
| LocalAddressRepository | Indexed persistent SQL reads; no HTTP, import or REST registration |
| FallbackAddressRepository | Prefer healthy local; fallback only on unavailability, never entity not-found |
| RemoteAddressRepository | Adapt the existing provider without changing remote semantics |
| DatasetLoader / DatasetValidator | Local file/checksum/manifest and full canonical data validation |
| DatasetInstaller / SchemaManager | Versioned schema and shared transactional, fail-safe bundled/remote installation |
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

`Plugin::instance()->address_service()`, `repository()` and `rest_controller()` are provisional internal accessors. The lookup graph is composed in Plugin; tests inject another AddressRepositoryInterface, or wrap an AddressProviderInterface with RemoteAddressRepository. DatasetSyncService separately accepts a DatasetProviderInterface with a default sync adapter. There is no production provider switcher or stable developer facade yet.

## Verification and deferred work

The frozen fixture is unchanged. The existing 42 core and 60 reliability checks isolate local state when exercising remote behavior. New local integration checks compare the same frozen public responses using a fixture installed in the real repository, and prove offline/rollback/query bounds. Run instructions: [tests](../tests/README.md). Results: [Phase 4 validation](phase4-validation.md).

Persistent local reads are now the normal path and never depend on the remote cache flow above. Bundled loader/import runs only when installation/upgrade requires it. See [local-dataset.md](local-dataset.md) for canonical format, source, SHA-256 and default selection. Remote reliability from Phase 3 remains unchanged for emergency fallback.

## Dataset administration path

```text
SettingsPage (manage_options + action nonce) / DatasetScheduler
                     ↓
             DatasetSyncService
                     ↓
       DatasetProviderInterface (complete canonical package)
                     ↓
         DatasetPackage / DatasetValidator
                     ↓
      DatasetInstaller::install_package (shared transaction staging)
                     ↓
       staged content/count/relationship verification
                     ↓
       atomic rows + metadata COMMIT → LocalAddressRepository
```

DatasetSyncLock coordinates check/sync/repair/reinstall independently of lookup refresh leases. DatasetMetadata centralizes bounded operation state and dataset metadata. DatasetIntegrityAuditor reconstructs database content for explicit audit/repair and pre-commit verification. RemoteCacheCleaner invalidates only named active/negative/failure state, preserving LKG. SettingsPage renders native WordPress forms; no custom JS/CSS assets or public write endpoints exist. DatasetScheduler defaults disabled and runs only its registered WP-Cron job. See [sync design](dataset-sync.md), [admin UI](admin-settings.md) and [Phase 5 validation](phase5-validation.md).

## Reusable frontend path

```text
Shortcode / vnap_render_address_fields / AddressComponent::render
                     ↓
      AddressService provinces → FieldRenderer → unique HTML fields
                     ↓
      conditional Assets → VNAddressPro per-container instances
                     ↓
      same-origin REST v1 wards → existing service/local repository
```

Frontend classes do not query SQL or access providers directly. Client code uses native selects, per-instance cancellation/sequence state, page-local successful ward cache and bubbling vnap: events. No global asset enqueue, form submission, new REST endpoint or integration is added. AddressService::validate_selection() is an additive strict relationship check for consumers; it does not change legacy v1 input coercion. See [frontend component](frontend-component.md) and [Phase 6 validation](phase6-validation.md).

Deferred: WooCommerce/form adapters, versioned public error/schema changes, trusted upstream snapshot manifests and full multisite/external-cache matrix.
