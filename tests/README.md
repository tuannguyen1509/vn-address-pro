# Core and regression checks

Use a disposable WordPress database, English locale and PHP with mbstring. Do not run these tests against a production site: they temporarily replace two address transients and exercise the version marker. Existing cache values are restored with a fresh 24-hour TTL.

No PHPUnit, Composer or end-user dependency is required. The contract runner uses the real WordPress REST dispatcher, parameter validation, HTTP API filter and Transients API.

```sh
wp plugin activate vn-address-pro
wp eval-file wp-content/plugins/vn-address-pro/tests/Contract/RestV1ContractTest.php
wp eval-file wp-content/plugins/vn-address-pro/tests/FoundationTest.php
wp eval-file wp-content/plugins/vn-address-pro/tests/Unit/CoreTest.php
wp eval-file wp-content/plugins/vn-address-pro/tests/Unit/ReliabilityTest.php
wp eval-file wp-content/plugins/vn-address-pro/tests/Integration/LocalRepositoryTest.php
wp eval-file wp-content/plugins/vn-address-pro/tests/LocalRepositorySmokeTest.php
wp plugin deactivate vn-address-pro
wp plugin activate vn-address-pro
wp eval-file wp-content/plugins/vn-address-pro/tests/FoundationTest.php
find wp-content/plugins/vn-address-pro -name '*.php' -exec php -l {} \;
php wp-content/plugins/vn-address-pro/tests/RequirementsTest.php
```

`RequirementsTest.php` defaults to an unsupported WordPress version; also run it on PHP 7.4 with argument `6.3` to exercise the PHP gate. Its stub functions live only in this separate CLI process and are never loaded by the plugin.

`Contract/fixtures/rest-v1.json` was captured with `capture` against the original 1.0.0 implementation before changing the entrypoint. It records 18 comparisons: responses, types, sorting, accent-sensitive searches, errors, routes, outbound request settings and shared raw cache. Records are test fixtures, not a runtime dataset. Do not regenerate the baseline to make a regression pass. WordPress-generated validation messages depend on the locale/core release.

For live smoke tests, call all four routes over HTTP on the test site. Enable `WP_DEBUG` and `WP_DEBUG_LOG`, inspect `wp-content/debug.log`, and verify the Plugins admin row shows VN Address Pro / Tunlamweb. Repeat lifecycle and contract checks without WooCommerce; with WooCommerce installed only verify coexistence, since no integration exists yet.

## Phase 2 core checks

`Unit/CoreTest.php` runs 42 named checks for Normalizer, Search, Validator, AddressService, ProvinceOpenApiProvider, AddressCache, HttpClient, v1 error mapping and direct legacy calls. It uses mocked provider/cache/HTTP implementations; a `pre_http_request` filter intercepts every outbound request, so the test suite does not need internet. Cache adapter checks use the real WordPress Transients API. PHP warnings/notices during test execution become test failures. The temporary `vnap_unit_cache` key is also restored after the suite.

The frozen fixture has not been regenerated or edited. Phase 3 updates the contract runner's state isolation only, clearing active/LKG/negative/failure/lock state before no-fallback error cases. Expected public schemas, messages/statuses and baseline comparisons are unchanged. Run contract and core checks on PHP 8.1 and 8.3, with WordPress 6.3+ and no WooCommerce required.

## Phase 3 reliability checks

`Unit/ReliabilityTest.php` adds 60 named tests; total core/reliability is **42 existing + 60 new = 102**, plus 18 frozen REST comparisons and the foundation/requirements suites. HTTP is mocked globally; time advances in memory, not by sleeping. Real database-backed leases are tested for exclusion, expiry/reclaim and ownership-safe release.

`Support/CacheState.php` snapshots and clears only explicitly named provider resources used by a test (default list and province 1). It covers active/LKG/negative/failure transients and the matching option lease, restoring saved values afterwards. It does not scan/delete arbitrary transients. Run only on a disposable site; transient TTL restoration with external object cache is approximate, and tests deliberately exercise lock/version/cache state.

Existing tests updated: CoreTest no longer requires only one active-cache write, acceptance of empty raw datasets, lack of failure state or immediate error on corrupt active data. It now asserts valid active/LKG outcomes, rejection/refresh, bounded failure markers and preserved no-fallback public error mapping. Public schema/signature/search/sort/health expectations were not weakened.

Reliability cases cover empty/duplicate/missing/invalid required fields, province and ward-parent mismatch, valid empty wards, cache/LKG writes and write failures, timeout/DNS/500/503/429/invalid JSON/schema outages, stale-first backoff, negative 404 expiry, retry/no-retry, TTL/filter/Retry-After bounds, lock release/crash recovery and REST fallback schema equality. Error-contract cases clear all fallback state; separate stale scenarios intentionally expect HTTP 200 with identical response shape.

Tests can capture `vnap_diagnostic` without producing PHP log spam via `vnap_log_to_error_log`. Logs from other deliberate failure checks are prefixed application diagnostics; inspect separately from PHP Warning/Notice/Deprecated/Fatal.

Optional live check (network/provider availability can cause failure; excluded from the core suite):

```sh
wp eval-file wp-content/plugins/vn-address-pro/tests/LiveSmokeTest.php
```

This checks real HTTP GET requests to the current site's four REST URLs, including province code 79. It uses `rest_url()`/`add_query_arg()` and works with query-style REST URLs. Run after the offline checks, then inspect `wp-content/debug.log`. No frontend component or integration is installed by the test.

## Phase 4 persistent repository checks

Use a **disposable database**: LocalRepositoryTest deliberately replaces local rows with deterministic fixtures, exercises rollback and renames/restores the owned ward table, then reinstalls the shipped snapshot. Do not run it on a site with a custom/newer installed dataset. It has 40 checks, including 11 exact public response comparisons against the unchanged JSON baseline, schema/manifest/checksum validation, indexed query bounds, full offline data, fallback and failed-import preservation. No network is required. Total named checks are 102 existing + 40 local = 142, with 18 original REST comparisons reported separately.

`Support/LocalState.php` temporarily removes/restores only dataset selection metadata in old remote-path tests. CoreTest's provider double now implements the repository abstraction; actual remote provider service tests use the explicit adapter. Schema/signature/search/sort/health expectations and baseline JSON remain unchanged.

`LocalRepositorySmokeTest.php` clears only its named remote cache/LKG/marker resources and denies every outbound request while dispatching the four real WordPress routes. It asserts zero HTTP attempts and restores prior state. `RemoteProviderSmokeTest.php` is an optional network test of the Phase 3 provider, separate from site/local smoke.

For fresh offline activation, copy `Support/BlockHttp.php` into a disposable site's `mu-plugins` directory **before activation**, reset/read its `vnap_test_http_count` option and activate VN Address Pro. Expected count: zero. Remove the test MU plugin before the normal remote mock suites: its PHP_INT_MAX HTTP blocker intentionally overrides lower-priority contract fixtures. This is test-environment isolation, not a change to public expected output. Disable unrelated WP-Cron in that disposable environment to isolate plugin requests. For actual HTTP transport, keep outbound denied in the server and call the four REST URLs from outside WordPress; all should return 200 without active/LKG cache.

The shipped dataset and generated metadata must remain paired; `.gitattributes` ensures JSON LF bytes survive checkout. See [local dataset](../docs/local-dataset.md) and [Phase 4 results](../docs/phase4-validation.md).

## Phase 5 sync and administration checks

```sh
wp eval-file wp-content/plugins/vn-address-pro/tests/Integration/DatasetSyncTest.php
```

Use a disposable database only. The suite installs deterministic local fixtures, temporarily renames owned tables, injects SQL failures, opens a second database connection for concurrent REST reads, creates/deletes a test subscriber and finally reinstalls the shipped bundle. It restores scheduling/status options. It has 65 checks with all HTTP mocked/blocked, including update/version conflict, download/schema/partial failures, failed import/verification/COMMIT, lease ownership/lost-worker rejection, audit/repair/no-downgrade, schedules/lifecycle, capability/nonces and offline lookup. Existing 18 REST comparisons and 142 core/reliability/local checks remain unchanged.

For actual admin HTTP transport, copy `Support/SyncHttpFixture.php` into the disposable site's mu-plugins directory. It provides a deterministic complete source response and blocks other outgoing HTTP. Supply VNAP_TEST_URL (trailing slash), VNAP_TEST_USER and VNAP_TEST_PASSWORD in the process environment, then run `node tests/AdminHttpSmokeTest.mjs` with Node 20+. This checks login, menu/tabs, seven protected POST actions/redirects/feedback and denial of anonymous/invalid-nonce requests (30 checks). Never commit credentials. Remove the fixture before normal remote-mock suites because its highest-priority filter overrides their responses. Neither test MU fixture is loaded by production plugin code.

For actual offline REST transport, install Support/BlockHttp.php as a temporary MU fixture, reset its outbound counter, run `VNAP_TEST_URL=... node tests/OfflineHttpSmokeTest.mjs`, then verify the counter stayed zero. Disable unrelated WP-Cron in this environment and remove the fixture afterwards. See [Phase 5 results](../docs/phase5-validation.md).

Optional upstream availability check, never publishes: `wp eval-file wp-content/plugins/vn-address-pro/tests/RemoteDatasetSmokeTest.php`.
