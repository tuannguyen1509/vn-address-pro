# Phase 1 validation

Executed on 2026-10-21 in disposable Docker containers and a dedicated database/network. Existing website containers were not used or modified.

## Baseline and compatibility

- Before editing the main plugin, ran the original 1.0.0 implementation in WordPress and captured `tests/Contract/fixtures/rest-v1.json` through the real REST dispatcher.
- Compared the moved `VN_Address_API_2025` class against the original: exact text match. Only its file wrapper and bootstrap invocation moved.
- After implementation, all 18 baseline comparisons passed: success payloads/types, filtering, accent behavior, different ward sort orders, public routes, HTTP configuration, shared raw cache, parameter errors and upstream errors.
- `/health` intentionally retains its original service/version strings; plugin metadata is independently 2.0.0 / VN Address Pro.

## Executed checks

| Check | Environment | Result |
| --- | --- | --- |
| All 9 PHP files: syntax | PHP 8.1.34 and PHP 8.3.35 | PASS |
| Contract: 18 strict baseline comparisons | WordPress 6.3.7 / PHP 8.1.34 CLI, no WooCommerce | PASS |
| Foundation and real activate → deactivate → activate | WordPress 6.3.7 / PHP 8.1.34 CLI | PASS |
| Contract and foundation | WordPress 7.1.3 / PHP 8.3.35, no WooCommerce | PASS |
| Contract, foundation and real deactivate → activate | WordPress 7.1.3 / PHP 8.3.35, WooCommerce 11.2.1 active | PASS |
| Live HTTP GET: provinces, wards with province_code=79, province/79, health | WordPress 7.1.3 / PHP 8.3.35, original remote provider | All HTTP 200; response keys/types checked |
| Authenticated Plugins admin HTML | WordPress 7.1.3 | Shows VN Address Pro / Tunlamweb / 2.0.0 |
| Unsupported PHP gate | Actual PHP 7.4 CLI, standalone WordPress function stubs | Core not loaded; admin notice and activation rejection verified |
| Unsupported WordPress gate | PHP 8.3 CLI, standalone stubs with WordPress version 6.2 | Core not loaded; notice and activation rejection verified |
| WP_DEBUG and WP_DEBUG_LOG | Both WordPress test installations | No logged warning, notice, deprecation or fatal during final checks |
| Website-specific code/security-pattern scan | Plugin workspace | No site-specific configuration or newly added integration logic |

Foundation checks cover singleton/repeated entrypoint loading, exactly one `vnap_loaded`, legacy public methods, registered lifecycle hooks, metadata/header consistency, no same-version option write, upgrade marker advancement, no marker downgrade, no lifecycle HTTP call, and preservation of cache and cron state.

## Scope and limits

- PHP 8.1 minimum was exercised through real WordPress CLI/bootstrap/REST dispatch; the HTTP/admin test server used PHP 8.3.
- Unsupported-runtime notice tests use isolated stubs; they are not full old-WordPress browser tests.
- WooCommerce checks demonstrate baseline coexistence only. No checkout/address field changes or integration were implemented.
- Multisite marker strategy is documented but was not exercised on a multisite network.
- Contract fixtures use an English WordPress locale with mbstring. WordPress-generated validation message translations can differ with locale/core changes.
- Source is unchanged for known legacy limitations: schema validation, upstream 404 → 502 mapping, no stale fallback/refresh lock, accent-sensitive search and static city classification.
- No folder/entrypoint rename, custom table, local runtime dataset, cron, admin sync UI or form integration was introduced.
- Do not activate a separate old and new copy together. Upgrade the existing plugin in place, preserving its installed folder/main filename.

Repeatable commands and fixture policy: [tests/README.md](../tests/README.md).
