# Phase 2 validation

Executed on 2026-10-21 in a dedicated disposable Docker network, WordPress volume and MariaDB database. Existing website containers were not used or modified. No WooCommerce or form plugin was installed in either Phase 2 test installation.

## Refactor checkpoints

1. Ran the unchanged Phase 1 implementation against its existing frozen fixture: all 18 baseline comparisons passed.
2. Extracted Normalizer/Search and delegated the corresponding private legacy helpers: all 18 comparisons passed.
3. Wired service/provider/cache/HTTP/controller, reduced the legacy class to its public facade: all 18 comparisons and the existing foundation checks passed.
4. Added basic malformed-payload protection and 42 core checks. A wrong-root fixture exposed a missing list-shape check; added that check, then all 42 checks and all 18 comparisons passed.

The fixture was not regenerated or edited. SHA-256 before and after:

```text
436C9637F0444BACC719BEAB0057BDCBE8A5DC49AF0CE4EA8A979B792BCE5E62
```

## Executed results

| Check | WordPress 6.3.7 / PHP 8.1.34 CLI | WordPress 7.1.3 / PHP 8.3.35 |
| --- | --- | --- |
| Frozen REST baseline | 18/18 PASS | 18/18 PASS |
| Core suite, all HTTP mocked | 42/42 PASS | 42/42 PASS |
| Existing foundation suite | PASS | PASS |
| Actual activation/deactivation/reactivation | PASS | PASS |
| WP_DEBUG + WP_DEBUG_LOG | No logged errors | No logged errors |
| Syntax for all 22 PHP files | PASS | PASS |

Core checks cover:

- Normalizer defaults, prefixes, city classification and unchanged ward parent defaults.
- Search case/accent behavior and v1 permissive input coercion.
- Optional/missing fields and safe rejection of unexpected root/record/ward shapes.
- Mock-provider service list/detail/search/sorting, missing wards and error propagation.
- Transient adapter miss/set/get/delete, empty-array hits and TTL.
- Mock-cache/provider exact URL/key/TTL, existing raw-cache hits, shared detail fetch and no caching of errors.
- HTTP 200, transport WP_Error, HTTP 404/429/500, invalid JSON and scalar JSON roots.
- Exact legacy error mapping, direct legacy callback schemas/signatures, constants and idempotent routes.
- Frozen health payload, single loaded hook, reusable service instance and safe non-scalar direct-call search.

The suite intercepts all HTTP requests and uses provider/cache doubles. WordPress adapter tests use real transients in the disposable database. Warnings/notices during the core checks are promoted to test failures. The existing baseline fixture remains English-locale/mbstring dependent, as documented in Phase 1.

## Live HTTP smoke tests

The independent optional `tests/LiveSmokeTest.php` used real requests to the PHP 8.3 WordPress web server with the original remote provider:

| GET endpoint | Result |
| --- | --- |
| `/vn-address/v1/provinces` | HTTP 200; response keys/list/record types PASS |
| `/vn-address/v1/wards?province_code=79` | HTTP 200; response keys/list/record types PASS |
| `/vn-address/v1/province/79` | HTTP 200; response keys/detail types/count PASS |
| `/vn-address/v1/health` | HTTP 200; exact legacy payload PASS |

The health values remain `VN Address API 2025+` / `1.0.0`; plugin metadata/version remains VN Address Pro / 2.0.0. Live provider availability is not a dependency of the core test suite.

## Upgrade and compatibility

The test site remained active while its bind-mounted plugin files were refactored; subsequent requests loaded the new core without reactivation, database migration, cache reset or configuration changes. Real deactivate/reactivate checks were then performed separately. Existing raw-cache hits are also tested directly, with no HTTP request or TTL rewrite.

| Component | Before | After | Compatible |
| --- | --- | --- | --- |
| Entrypoint | `vn-address-api.php` | same | YES |
| Plugin version / PHP / WordPress requirements | 2.0.0 / 8.1+ / 6.3+ | same | YES |
| Legacy public class/signatures/constants | `VN_Address_API_2025` | delegating facade, same API | YES |
| REST namespace/routes/methods/permissions | v1, four public GET routes | same | YES |
| Cache keys/raw format/TTL | legacy raw transients, 24h | same | YES |
| Provider URL / HTTP headers | Province Open API v2, legacy headers | same | YES |
| Timeout / redirects | 15s / 3 | same | YES |
| Search / sorting / health | frozen v1 behavior | same | YES |

Malformed payloads outside the valid v1 record contract now receive a safe v1-shaped 502 instead of cast warnings/TypeError. Missing/null wards, optional scalar defaults, not-found precedence and permissive scalar code coercion remain unchanged.

## Other checks and limits

- Existing standalone requirement tests passed on PHP 8.3 (unsupported WordPress stub) and actual PHP 7.4 (unsupported PHP gate, using stubs); no new core was loaded.
- Source scan confirms only HttpClient performs outbound requests, only AddressCache accesses transients, and only AddressController declares REST routes. No site-specific configuration, new tables, sync jobs, integrations or frontend assets were added.
- `git diff --check` passed. Entrypoint, autoloader, original contract runner/fixture, requirements class and foundation test remain unmodified.
- PHPStan/PHPCS are not configured in this repository; no runtime dependency was introduced for them.
- PHP 8.1 coverage used real WordPress CLI/bootstrap/REST dispatch; live web-server requests used PHP 8.3.
- Multisite, external object-cache backends and third-party integration combinations were not exercised in Phase 2.
- Raw schema-invalid arrays may still be cached before service validation; code matching, static city classification, legacy 502 mapping, stale fallback, refresh locks, retries and cache schema versioning remain deferred.

Architecture: [architecture.md](architecture.md). Repeatable test commands: [tests/README.md](../tests/README.md). No Phase 3 implementation was started.
