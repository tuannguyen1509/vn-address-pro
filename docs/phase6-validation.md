# Phase 6 validation

Validated 2026-10-21 on disposable Docker WordPress sites, with no WooCommerce/form plugins. Release remains 2.0.0 and entrypoint remains vn-address-api.php. No database/schema/dataset/manifest/sync implementation was changed. No Phase 7 work, push, tag or release is included.

## Matrix and existing regression suites

| Runtime | WordPress | Result |
| --- | --- | --- |
| PHP 8.1 CLI | 6.3.7, non-default table prefix | All server suites and frontend PHP checks passed |
| PHP 8.3 CLI / Apache | 7.1.3 | All server suites, frontend PHP and browser/admin HTTP checks passed |
| Apache PHP 8.3 + Chromium | 6.3.7 in a subdirectory | All frontend browser checks passed |
| PHP 7.4 | Standalone requirements gate | Core not loaded; notice/activation rejection passed |
| PHP 8.3 | Unsupported WP 6.2 stub | Requirements gate passed |

On each supported CLI runtime: **18/18 REST comparisons**, FoundationTest, **42 core + 60 reliability + 40 local + 65 sync checks**, and local offline smoke passed. The existing Phase 5 admin HTTP runner passed all **30 checks** on the Apache site. No old server test expectations or fixtures were modified.

Frozen REST fixture SHA-256 remains `436c9637f0444bacc719beab0057bdcbe8a5dc49af0ce4ea8a979b792bce5e62`. Git comparison confirms data/, src/Data/, src/Database/, src/Sync/ and provider implementations remain unchanged. Additive AddressService::validate_selection does not change v1 coercion, schemas, routes, health, error mapping or legacy signatures.

## New frontend PHP checks

RenderTest passes **32 checks on both PHP versions**:

- Default shortcode and namespaced/prefixed PHP rendering APIs return fields without a submission handler.
- Canonical code fields and convenience hidden names are separate; provinces are rendered through AddressService.
- Custom bracketed names/labels/placeholders, optional address and global/per-field boolean required flags.
- Unique IDs, labels, described-by relationships, loading/status and error regions.
- Malicious attributes cannot create HTML/event handlers; custom class tokens receive vnap-.
- Initial province option and ward code config, without embedded ward dataset/script payload.
- Assets absent before rendering, enqueued once on demand, no jQuery, versioned handles, correct rest_url configuration and no public-GET nonce; no enqueue in wp-admin.
- Server relationship validator accepts valid codes/names, rejects cross-province/unknown/malformed/negative/overflow/boolean/fractional inputs.
- Rendering/validation produce **zero outbound HTTP attempts** while the local repository is healthy.

## Real browser checks

Playwright 1.64.0 / Chromium passed **41 checks** on the normal site and **41 checks** on the WordPress 6.3.7 subdirectory installation. The package was installed only in a temporary browser container, not the repository or production plugin. Incomplete/non-native markup is rejected before creating an instance.

TestPage renders three independent fields via shortcode/PHP after wp_head and a separate plain page. Checks cover conditional/deduplicated asset printing (including late stylesheet), server province options, initially disabled ward, unique labels/IDs, preselected province/ward/address, ready/events, real same-site ward requests, independent instance state, hidden names, shared fulfilled cache and idempotent initialization.

The race mock deliberately ignores AbortController, resolves the newer province response first and the obsolete one last. Only the newer ward list remains; abort is observed and stale names are cleared. Controlled empty lists, HTTP failure, invalid JSON/schema/parent, network exception and invalid preselected ward produce safe state and translated feedback. DOM option text escapes a malicious-looking label. Required validation, reset, native form reset, pending-request destroy, listener/reference cleanup and re-init pass.

Native keyboard navigation and 320px mobile control widths pass. Every documented event is observed. The console has **no errors**, page exceptions or unhandled promise rejections. Reload discards the in-memory cache. No screenshot, browser trace or node_modules was added to Git.

## Offline and REST URL verification

Both browser runs used the test-only server MU outbound blocker and DISABLE_WP_CRON to isolate plugin activity. Rendering and actual same-site REST requests completed with HTTP attempt counters remaining **0** on each site. The browser requested no external API/domain. Query-style rest_route URLs and the /wp63/ subdirectory worked without a hard-coded wp-json path. Temporary MU fixtures were removed before remote-mock/admin suites.

## Syntax, debug and lifecycle

All **64 PHP files** passed PHP 8.3 syntax checks; production frontend code and selection validation ran on PHP 8.1. Node syntax checks passed for production JavaScript and the browser runner. WP_DEBUG/WP_DEBUG_LOG were enabled on both sites. Logs contained no PHP Warning/Notice/Deprecated/Fatal/Parse, uncaught exception/TypeError or WordPress database errors during validation. Sanitized application diagnostics from intentional server outage tests are expected.

Foundation tests retained activation/deactivation/single-boot/version/cache/cron behavior. The normal activate/deactivate/activate sequence is also checked with WP-CLI. The frontend does not register a sync/repair/write callback or change settings, schema or data. A development-only render assertion was corrected to decode localized JSON rather than assume a particular URL escaping representation; public fixtures were not touched.

## Limits and follow-up

Browser coverage is Chromium; Firefox/WebKit and manual assistive-technology audits are not claimed. Native ward selection requires JavaScript; a noscript explanation is rendered. Consumers own submission, server validation, distinct input names across multiple submitted instances and host-page asset preparation for dynamic fragments. Rendering after footer assets have printed is unsupported; templates need normal wp_head/wp_footer hooks. Late CSS can briefly be unstyled; early explicit Assets::enqueue avoids that.

Only successful responses are cached for this page, without persistent browser storage. Concurrent uncached instances may issue independent requests; in-flight Promise deduplication is deliberately omitted. Data updated during an open page becomes visible after reload. Existing remote emergency fallback still applies only when local is unavailable. No integrations or full-address formatter were added.

Recommended Phase 7: WooCommerce Core Integration + Classic Checkout. Implementation stops at Phase 6.
