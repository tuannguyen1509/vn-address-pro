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

## Phase 6 frontend checks

```sh
wp eval-file wp-content/plugins/vn-address-pro/tests/Frontend/RenderTest.php
```

This adds 32 PHP checks for shortcode/API rendering, names/labels, flags, escaping, unique IDs, asset isolation and authoritative server relationship validation. HTTP is denied throughout. It changes only process-local script queues/screen context; use a separate CLI invocation as with the other suites.

For browser tests, use a disposable site, enable WP_DEBUG/WP_DEBUG_LOG and disable unrelated WP-Cron. Copy `Frontend/TestPage.php` and `Support/BlockHttp.php` into its mu-plugins directory; reset `vnap_test_http_count` before running and assert it remains zero afterwards. TestPage renders three components via shortcode/PHP after wp_head, plus a plain page with no component. Never install test MU fixtures on production.

Install Playwright in a **temporary directory outside the repository**, matching your browser/image version (validation used Playwright 1.64.0 with its corresponding Docker image). Run from that directory:

```sh
VNAP_TEST_URL="http://your-disposable-test-server/" node /path/to/plugin/tests/Frontend/BrowserTest.mjs
```

The browser runner resolves Playwright from the current working directory; no npm package or production build step is required by plugin users. It runs 41 Chromium checks covering actual offline same-site requests, multiple instances, initial values, fulfilled caching, no duplicate init, deliberately reversed responses that ignore abort, controlled failures/malformed payloads, invalid markup/ward relationships, reset, destroy/re-init, keyboard/mobile layout, event contracts and clean console. Temporary browser mocks exercise failure states without intentional HTTP-503 resource noise in the console. It creates no screenshots/log dumps in the repository.

Remove both MU fixtures before ordinary server suites/admin HTTP tests so the blocker does not override remote mocks. Existing Phase 1–5 tests and baseline JSON remain unchanged. See [Phase 6 results](../docs/phase6-validation.md).

## Phase 7 WooCommerce checks

Use a **disposable site/database only**. `WooCommerce/Setup.php` intentionally replaces Woo checkout/cart pages and shipping-zone/payment/tax settings and creates a synthetic product. It is not a merchant configuration wizard. Test on a vendor-supported PHP/WP pairing; validation uses WC 8.2.0/PHP 8.1/WP 6.3.7 and WC 11.2.1/PHP 8.3/WP 7.1.3.

```sh
wp eval-file wp-content/plugins/vn-address-pro/tests/WooCommerce/Setup.php
wp eval-file wp-content/plugins/vn-address-pro/tests/WooCommerce/IntegrationTest.php
```

The integration runner verifies state mapping, strict validation, independent billing/shipping, CRUD/snapshots/native email formatting, customer metadata, zones/taxes, Block exclusion and declarations. Run separately with HPOS enabled and legacy storage. **Synchronize existing test orders with Woo's official CLI before switching stores**, e.g. `wp wc hpos sync` (current) or `wp wc cot sync` (8.2); do not bypass Woo's pending-sync protection.

Copy `WooCommerce/OfflineFixture.php` to the disposable site's mu-plugins directory for actual HTTP/browser tests. It denies outgoing HTTP, counts address-provider attempts, intercepts wp_mail, isolates unrelated Woo admin/update checks, and supplies a test-only COD failure plus order-key-protected read-only report. Never install it on production. Set its vnap_test_address_http_count to zero before validation; expected result remains zero.

From the temporary Playwright directory used above, supply VNAP_TEST_URL and VNAP_TEST_PRODUCT from Setup output; optionally VNAP_CHECKOUT_URL for subdirectory/index.php permalink installations:

```sh
node /path/to/plugin/tests/WooCommerce/BrowserTest.mjs
node /path/to/plugin/tests/WooCommerce/HttpCheckoutTest.mjs
```

BrowserTest exercises real Classic fields, state/ward changes, shipping toggles/zones, AJAX and replaced address fragments, VN↔US, malicious submissions, failed payment/retry and successful guest checkout. HttpCheckoutTest exercises create-account BACS, logged-in COD and foreign BACS orders and reads authoritative order/customer metadata through the test report. Orders/accounts remain only in the disposable database for inspection; do not run against production. No gateway is altered by production code.

With Woo deactivated, run `WooCommerce/AbsentTest.php` plus the full existing core suites; the absent runner also simulates an unsupported WC version in its own process. Remove the offline MU fixture before ordinary provider mock suites. No public REST baseline is regenerated. See [Phase 7 results](../docs/phase7-validation.md).

## Phase 8 Blocks and Store API

Keep using a disposable database. Run the Phase 7 Setup once to configure synthetic product/zones/tax, then run `WooCommerce/Blocks/Setup.php` to install a standard Checkout Block page without extension child markup and create a virtual product. Production never edits checkout content. Read `vnap_test_product_id` and `vnap_test_virtual_product_id` for environment values; repeating the Phase 7 Setup creates new product/method IDs.

```sh
wp eval-file wp-content/plugins/vn-address-pro/tests/WooCommerce/Blocks/Setup.php
wp eval-file wp-content/plugins/vn-address-pro/tests/WooCommerce/Blocks/IntegrationTest.php
```

The Block PHP runner has 23 checks covering native/foreign locale scope, forced registration, strict identifiers/relations, raw REST sanitizer protection, CRUD, guest-to-account metadata timing, virtual carts, saved selections, offline operation and actual renderer detection without page rewriting. Run with HPOS on/off; synchronize with Woo's official CLI before changing storage modes, as in Phase 7.

Install both `WooCommerce/OfflineFixture.php` and `WooCommerce/Blocks/OfflineFixture.php` as separate temporary MU fixtures. The first blocks outgoing HTTP/intercepts mail and provides the order-key-protected report; the second supplies a deliberate Store API payment failure and an inert express-presence button registered through Woo's public payment registry. It never processes a real express payment. Never install either fixture on production.

From the external temporary Playwright directory, set VNAP_TEST_URL, VNAP_TEST_PRODUCT and VNAP_TEST_VIRTUAL_PRODUCT. Set VNAP_CHECKOUT_URL for subdirectory/index.php permalinks. Run:

```sh
node /path/to/plugin/tests/WooCommerce/Blocks/BrowserTest.mjs
node /path/to/plugin/tests/WooCommerce/Blocks/StoreApiTest.mjs
```

BrowserTest passes 29 checks: automatic fields, native state/VN locale, independent and same-address workflows, international transitions, actual guest BACS/create-account COD/payment failure/retry, registered-customer persistence/reload/cards, deliberately obsolete fetch responses, final server state, historical order preservation, virtual orders, express presence, offline lookup and clean console. A short test-only wait permits Woo's native debounced form/payment changes to settle before submit. Production uses no polling/delays. Only the intentional native payment 400/402 resource message is excluded from unexpected console errors, and its exact fixture error is asserted separately.

StoreApiTest has 23 real HTTP checks using native Nonce/Cart Token authentication: missing/unknown/mismatched/malformed codes, strict raw state labels, unknown extension keys, shipping zones/taxes, valid canonical BACS/COD orders, foreign checkout without VN codes, and all four independent order codes. Its API fixture report is test-only. No public VN REST expected schema is changed.

Existing tests updated: RenderTest now checks the shared internal data dependency and reads localized config from that handle. Generic HTML names, VNAddressPro methods/events and all REST fixtures remain unchanged. The Phase 7 compatibility-declaration assertion follows the newly supported Block capability; its Classic behavior assertions remain intact. The shared order report adds shipping state/province-code observations without changing production routes.

Run the existing 41-check frontend browser suite and 30-check admin HTTP suite too. Remove the offline MU fixtures before remote-mock/contract tests; the highest-priority blocker otherwise overrides their mocks. RequirementsTest is standalone (`php tests/RequirementsTest.php`), not a wp eval-file script. See [Phase 8 report](../docs/phase8-validation.md) for the tested runtime/datastore matrix and harness corrections.

## Phase 9 My Account and Order Admin

Use disposable sites/databases only. Keep Woo 8.2.0/PHP 8.1/WP 6.3.7 and Woo 11.2.1/PHP 8.3/WP 7.1.3 pairings. The module minimum remains Woo 8.2; no new build dependency is needed.

```sh
wp eval-file wp-content/plugins/vn-address-pro/tests/WooCommerce/Management/IntegrationTest.php
wp eval-file wp-content/plugins/vn-address-pro/tests/WooCommerce/Management/ParityTest.php
wp eval-file wp-content/plugins/vn-address-pro/tests/WooCommerce/Management/AssetsTest.php
```

IntegrationTest has 43 checks for independent customer/order CRUD, strict validation, malformed native arrays, legacy/obsolete values, no-change historical retention, native customer-load codes, scoped foreign clearing, zero HTTP, save-hook ordering and no customer writes from order edits. The actual native Order Data save loop is checked for canonical fields and absence of parallel transport metadata. It restores current user/POST/admin errors and deletes its owned customer/order. ParityTest compares actual Classic, Block and Account callbacks for independent billing/shipping native properties and all four canonical metadata keys; it saves no customer/order and restores checkout content/cart state. AssetsTest adds four checks for dashboard/order-list exclusion and native edit-screen enqueue. Run separately with HPOS on/off using Woo's official synchronization before switches.

Install the Phase 7 OfflineFixture as a temporary MU plugin (no additional production endpoint). First run actual Classic and Block checkout browser/API tests to create orders with real items. Run Management/Setup.php through WP-CLI and store its JSON output in a temporary file outside Git. It reuses a real native order from each checkout type, resets their addresses/customer for deterministic editing, creates legacy/obsolete fixtures, sets the first administrator's test customer addresses and disables native Gravatar requests for network isolation. It is destructive to test configuration and must never run on a merchant site. IDs, order keys and credentials are never committed.

Copy that JSON where the external Playwright process can read it. Set VNAP_TEST_URL, VNAP_TEST_USER, VNAP_TEST_PASSWORD and VNAP_TEST_FIXTURE (absolute JSON path), then run:

```sh
node /path/to/plugin/tests/WooCommerce/Management/BrowserTest.mjs
node /path/to/plugin/tests/WooCommerce/Management/RaceTest.mjs
```

The 46-check browser runner uses real native My Account POSTs and Order Admin saves/reloads on actual Classic/Block-created orders: both addresses, tampering, VN/foreign transitions, readable overview, historical separation, native customer loading/copy, obsolete/legacy no-change saves, console and network checks. SelectWoo-enhanced native admin state/country controls are set through their underlying selects with normal events. Ward controls are ordinary visible selects. The separate race runner deliberately resolves an abort-insensitive old province response after a newer selection on both account/admin screens and checks that current code/options remain coherent; it saves no address.

Preserve existing Phase 1–8 tests and REST fixture. Run Classic and Blocks browser/API suites in the same datastore matrix, and remove MU blockers before remote-mock suites. For minimum Woo's upstream empty-cart confirmation preload warning, see Phase 8/9 reports; do not patch vendor code or suppress PHP errors. See [Phase 9 results](../docs/phase9-validation.md).

## Phase 10 migration

Run Migration/MatchingTest.php and Migration/IntegrationTest.php with wp eval-file on disposable sites only. Matching has 51 checks; integration covers zero SQL writes/options/customer/order changes during scan, explicit apply/confirmation, confidence, stale source/dataset, offline integrity gates, token/nonce/capability/site isolation, query pagination/date filters, historical snapshot safety, partial save failure and bounded non-autoload audit retention. Integration creates/deletes only its synthetic customers/orders/audits and restores the prior audit index/user/metadata; all outbound HTTP/mail is blocked. Run with both supported runtime pairings and HPOS/legacy, using official Woo synchronization before switches.

Run Migration/Setup.php on a disposable site, save its JSON outside Git, then run Migration/BrowserTest.mjs from an external Playwright installation with VNAP_TEST_URL, VNAP_TEST_USER, VNAP_TEST_PASSWORD and VNAP_TEST_FIXTURE. It exercises 23 actual admin-browser checks, including default policy, protected preview, customer apply, historical confirmation and HTTP 403 for invalid nonce. Setup creates owned fixtures and disables avatars only in the test site; delete the disposable database afterward. Never commit credentials, JSON, cookies or browser dependencies. Existing tests/fixtures remain unchanged. Full matrix/results and known vendor/harness findings: [Phase 10 validation](../docs/phase10-validation.md).
