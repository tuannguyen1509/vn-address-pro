# Phase 9 validation

Status: PASS, validated 22 October 2026. Version remains 2.0.0 Unreleased. Local commit only; no push, tag, release or Phase 10 implementation.

## Source review before implementation

Inspected local official WooCommerce 8.2.0 and 11.2.1 distributions plus official [Form Handler](https://woocommerce.github.io/code-reference/classes/WC-Form-Handler.html) and [Order Data](https://woocommerce.github.io/code-reference/classes/WC-Meta-Box-Order-Data.html) references. Confirmed My Account field preparation, native nonce/authentication and validation/save timing; reviewed default/billing/shipping field filters, address_to_edit and process-field hooks. The before-save validation hook receives the customer on both targets; newer post-save arguments are deliberately not assumed.

Reviewed admin fields, HPOS/legacy save dispatch, before-order-object-save CRUD timing, native customer-details AJAX, load-address and billing-to-shipping copy loops. Woo 8.2 lacks the newer field order/context arguments and update_callback, and overwrites shipping field values. The adapter explicitly supports those differences without copying metadata or modifying Woo source.

## Test isolation

All changes to Woo pages, tax/zones, accounts and orders are confined to disposable databases. External HTTP and mail are blocked using test-only MU fixtures. Credentials, browser package, fixture JSON, cookies, WordPress, Woo source and databases remain outside the repository. No public REST fixture is regenerated.

The new PHP tests originally compared separate WC_Meta_Data object identities when checking unchanged customer data. The assertion now compares serialized data values. Browser historical comparisons exclude the deliberately current customer fields from the test-only report. These corrections preserve the intended historical-order invariant rather than changing its expected values. Native admin state/country selects are SelectWoo-enhanced, so the browser runner sets their underlying native select and emits normal change events; plugin ward controls are exercised directly.

## Supported runtime/datastore matrix

| Runtime | Storage | Account/Admin PHP | Admin asset isolation | Management browser | Cross-surface parity |
| --- | --- | --- | --- | --- | --- |
| WP 6.3.7 / PHP 8.1 / Woo 8.2.0 | HPOS | 43 PASS | 4 PASS | 46 PASS | PASS |
| WP 6.3.7 / PHP 8.1 / Woo 8.2.0 | Legacy | 43 PASS | 4 PASS | 46 PASS | PASS |
| WP 7.1.3 / PHP 8.3 / Woo 11.2.1 | HPOS | 43 PASS | 4 PASS | 46 PASS | PASS |
| WP 7.1.3 / PHP 8.3 / Woo 11.2.1 | Legacy | 43 PASS | 4 PASS | 46 PASS | PASS |

The browser ran in Playwright 1.64.0/Chromium outside the repository. Native order save/reload tests reused actual Classic and Store API orders with real items, not merely orders labeled with a created_via string. Separate synthetic legacy/obsolete orders covered historical preservation. Woo's official `wc hpos sync` / older `wc cot sync` commands were used before changing storage mode. No production code changes a merchant's storage settings.

The race runner passed for both account/admin surfaces on both runtime pairings, deliberately resolving an abort-insensitive old province response after a newer selection. No obsolete response replaced the selected code/options; no browser exception occurred. Native load customer billing/shipping and native copy billing → shipping passed, including after save/reload. The test-only outbound address-provider counter remained zero.

## New coverage and invariants

- Independent billing/shipping account changes, forged display names, strict province/ward relationship checks, malformed native arrays, VN/foreign round trips, readable overview and saved-value reload.
- Customer current address changes never alter historical order native fields or canonical metadata. Order edits never update current customer data.
- Native Order Data CRUD timing, all four unchanged code keys, name snapshots and absence of parallel transport metadata in both old/new native save loops.
- Invalid admin selections restore the prior administrative address and produce native errors; no invalid canonical pair is persisted. An independently valid address/other order properties may still save.
- Existing, missing and obsolete ward metadata: no guessing, no migration during rendering, no-change saves retain snapshots, intentional administrative changes validate against today's repository.
- Account/admin assets absent from ordinary frontend, dashboard and order lists; present on supported order edit screens. No new Users → Profile editor, metabox, REST write route or customer AJAX endpoint.

Source review caught that native item/totals handling can save the order before Order Data. The adapter therefore arms canonical writes at priority 39, after items/totals and immediately before native Order Data at 40. The before-order-object-save hook applies the validated fields after native properties are set. The actual native save-loop assertions and browser tests passed with this ordering.

The first isolated asset test invoked an admin-only Woo helper without loading Woo's admin functions. The test now explicitly loads that native context, and production also safely returns if the helper is unavailable. Screen detection requires HPOS edit/new action, so the shared HPOS list/edit screen ID alone does not enqueue assets.

## Existing regression tests

On both Woo versions and both datastores: Classic PHP **47**, Classic browser **20**, Classic HTTP **20**, Block PHP **23**, Block browser **29** and direct Store API **23** checks passed. These existing runners and their expected public behaviors were not weakened or edited. Block browser testing uses the validated default Twenty Twenty-Five theme on the newer pairing and Twenty Twenty-One on the minimum pairing. Classic and management flows were also exercised with Twenty Twenty-One.

With Woo inactive, both runtime pairings passed FoundationTest, **18 REST comparisons**, **42 core**, **60 reliability**, **40 repository**, **65 sync/admin**, four local offline routes, **32 frontend PHP**, Woo absent/unsupported and standalone requirements checks. The original frontend browser runner passed **41** checks on both runtime pairings using their default FSE themes (Twenty Twenty-Five / Twenty Twenty-Three). Actual offline REST transport returned HTTP 200 for all four routes with zero outbound attempts. Existing admin HTTP tests passed **30** checks on the newer pairing.

With Woo active again, **18 REST comparisons** passed on both runtimes. Plugin deactivate/reactivate passed without duplicate classes/routes/hooks or fatal errors. All **90 PHP files** passed PHP 8.1 syntax checking; new production/test JavaScript passed node syntax checking. No build/runtime dependencies are bundled or required from end users.

REST schema/namespace/routes/search/sorting/health/errors remain unchanged. Fixture changed: **NO**. SHA-256:

```text
436c9637f0444bacc719beab0057bdcbe8a5dc49af0ce4ea8a979b792bce5e62
```

No dataset, schema, importer, sync engine, provider, REST implementation, legacy facade or Phase 7/8 checkout implementation was modified. Existing tests changed only in their README; new management tests are separate.

## Notices, diagnostics and harness findings

Woo 8.2 can render Blocks-style notice banners on My Account when checkout is configured as a Block. Its success text was already rendered and address data saved correctly; the initial new test selected only the older `.woocommerce-message` class. The new management runner now recognizes both official native success/error styles and still requires the expected notice. Production does not replace or clear Woo notices. Temporary route/notice probes were removed.

Native admin avatars produced Gravatar requests. The disposable Setup disables avatars rather than altering production behavior or ignoring external address requests. Outbound mail is intercepted in fixtures/PHP tests. No address component calls an upstream provider from the browser.

Early development exposed and fixed translated admin labels being evaluated before init. Test assertion failures and an incorrect isolated admin-helper invocation were recorded during development, then resolved; logs were reset before the final controlled validation. Final PHP/native save/lifecycle checks on both runtimes contained no new PHP Warning, Notice, Deprecated, Fatal, Parse or database error. Browser console/exception checks passed. Intentional provider-failure diagnostics in core regression tests are expected.

Minimum Woo's previously documented `Hydration.php:51` empty-cart confirmation preload warning remains upstream (see Phase 8). Offline initial admin data-source polling also produced Woo 8.2's false-to-array deprecation during environment setup; later controlled management checks were clean. These vendor files were not patched and PHP error reporting was not suppressed.

## Known limitations and baseline checks

No fuzzy legacy match, bulk migration, customer CSV handling or automatic order conversion is included. Users → Profile stays native. Third-party checkout/field editors, shipping mappings, real express gateways and a complete multisite/external-cache matrix still need their own validation. Existing historical administrative snapshots are retained on unchanged saves; selecting a current ward intentionally replaces that address snapshot only.

Two extra theme probes exposed baseline behavior outside the standard regression matrix. The unchanged generic Phase 6 component under Twenty Twenty-One had a 2px text-input overflow at a 320px viewport; the original suite passed unchanged with each site's default FSE theme. No generic CSS was modified in this phase. Woo 11.2's native same-address checkbox interaction in the Block browser runner failed under Twenty Twenty-One. A separate read-only export of the exact Phase 8 commit `cbe295b`, activated alone in the disposable site, reproduced the same failure. The unchanged runner passed on its validated Twenty Twenty-Five pairing. This is not a new Phase 9 regression, and this report does not certify that additional theme/Block combination. No prior tests were changed to mask either finding.

Git checks cover intended file scope, whitespace, frozen fixture, direct order posts/postmeta access, prohibited polling/private APIs and secrets/build artifacts. Temporary WordPress, Woo sources, browser dependencies, cookies, logs and fixture JSON are not repository files. Previous commits remain intact and will not be amended.

Recommended Phase 10: Address Normalization + Legacy Migration Foundation. Not implemented.
