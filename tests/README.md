# Core and regression checks

Use a disposable WordPress database, English locale and PHP with mbstring. Do not run these tests against a production site: they temporarily replace two address transients and exercise the version marker. Existing cache values are restored with a fresh 24-hour TTL.

No PHPUnit, Composer or end-user dependency is required. The contract runner uses the real WordPress REST dispatcher, parameter validation, HTTP API filter and Transients API.

```sh
wp plugin activate vn-address-pro
wp eval-file wp-content/plugins/vn-address-pro/tests/Contract/RestV1ContractTest.php
wp eval-file wp-content/plugins/vn-address-pro/tests/FoundationTest.php
wp eval-file wp-content/plugins/vn-address-pro/tests/Unit/CoreTest.php
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

The existing contract runner and its frozen fixture have not been regenerated or edited. Run contract and core checks on PHP 8.1 and 8.3, with WordPress 6.3+ and no WooCommerce required.

Optional live check (network/provider availability can cause failure; excluded from the core suite):

```sh
wp eval-file wp-content/plugins/vn-address-pro/tests/LiveSmokeTest.php
```

This checks real HTTP GET requests to the current site's four REST URLs, including province code 79. It uses `rest_url()`/`add_query_arg()` and works with query-style REST URLs. Run after the offline checks, then inspect `wp-content/debug.log`. No frontend component or integration is installed by the test.
