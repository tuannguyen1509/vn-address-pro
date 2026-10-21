# Phase 7 validation

Validated 2026-10-21 on disposable Docker sites. Phase 7 adds an optional WooCommerce Classic Checkout module; plugin version 2.0.0 and entrypoint vn-address-api.php are retained. No production gateway, dataset, schema, sync engine or REST controller changes are included.

## Supported test matrix

| WordPress | PHP | WooCommerce | CRUD integration | Actual checkout HTTP | Chromium Classic checkout |
| --- | --- | --- | --- | --- | --- |
| 6.3.7, subdirectory and non-default table prefix | 8.1 | 8.2.0 minimum | 47 checks in HPOS and 47 in legacy storage | 20 checks in each storage mode | 20 checks with HPOS |
| 7.1.3 | 8.3 | 11.2.1 current | 47 checks in HPOS and 47 in legacy storage | 20 checks in each storage mode | 20 checks with HPOS |

Woo 8.2 is the baseline because HPOS became stable/default for new installations in that release. APIs were checked against installed official Woo source. Compatibility declarations use the unchanged actual plugin entrypoint. The HPOS declaration was enabled only after initial CRUD tests passed in both datastores on both versions. Blocks compatibility is explicitly false.

## Existing tests and public contract

With Woo deactivated, both supported CLI runtimes passed FoundationTest, 18 REST baseline comparisons, 42 CoreTest, 60 ReliabilityTest, 40 LocalRepositoryTest, 65 DatasetSyncTest, local offline smoke and 32 frontend PHP checks. AbsentTest also passed on each runtime, including a process-local unsupported Woo 8.1 simulation. The existing frontend browser runner passed 41 checks and existing admin HTTP runner passed 30 checks on the current site with Woo deactivated.

With Woo active, both runtimes additionally passed all 18 REST comparisons and the four-route offline local smoke. No existing test assertion was weakened. REST response schema unchanged: **YES**. Baseline fixture changed: **NO**. SHA-256 remains `436c9637f0444bacc719beab0057bdcbe8a5dc49af0ce4ea8a979b792bce5e62`.

## New integration checks

The 47 checks cover conditional/idempotent loading, declarations, dynamic states, strict reversible identifiers (including malformed/overflow rejection), untouched international states, native fields, province/ward existence and parent validation, forged display names, hostile scalar-field arrays, and independent billing/shipping. An original state-label error remains rejected even if Woo subsequently converts the submitted label to a registered state key.

WC_Order/WC_Customer CRUD checks reload stored canonical metadata, preserve human city snapshots and stable state identifiers, exercise changed province labels, retain historical order snapshots when customer details change, format old orders without metadata safely and clear obsolete VN customer codes on foreign checkout. The native Woo email address template renders readable historical province/ward names. Tests intercept mail; external delivery was not tested.

Province-specific and country-only shipping zones match; a province tax rate resolves using the registered identifier. Block checkout detection disables the Classic normalization path. No Store API integration is claimed. Healthy local reads require zero HTTP attempts. Each integration runner deletes its own CRUD fixtures.

## Browser and checkout transport

Playwright 1.64.0 / Chromium exercises real Classic checkout: assets once, stable state options, human city/canonical ward synchronization, shipping zones and recalculation, independent addresses, shipping toggle, repeated updated_checkout, actual replacement of address fragments, VN to US to VN, tampered ward/state-array rejection, selections surviving validation/payment errors, successful guest order and readable native formatting. Both browser runs pass 20 checks with no console errors or page exceptions. Woo 8.2 can omit customer addresses from the guest thank-you template; that case verifies the actual order's native formatted address via the test-only protected report rather than replacing a Woo template.

HttpCheckoutTest passes 20 checks per run through real Woo AJAX/nonce processing: create-account BACS, logged-in COD and foreign BACS orders; it reads authoritative order/customer codes and confirms submitted forged city names are ignored. Both HPOS and legacy datastores were tested on both Woo versions. The test-only payment failure and read-only report exist exclusively in a disposable MU fixture; production code does not change gateways or add write endpoints.

Outbound HTTP is denied during browser/checkout tests. After resetting counters for the final isolated checkout runs, both address-provider attempt counters remain **0**. All four REST URLs return 200 offline. Browser requests stay on the WordPress REST origin; there is no external address fetch or embedded all-ward payload.

## Lifecycle, syntax and debug

Actual VN Address Pro deactivate/activate succeeds with Woo active on both sites. Woo off/on, absent/unsupported versions and repeated integration loading are tested without duplicate registration. All 77 repository PHP files pass PHP 8.1 syntax checks; the new adapter and both Node runners pass JavaScript syntax checks.

Final supported-runtime runs with WP_DEBUG and WP_DEBUG_LOG enabled contain no PHP Warning, Notice, Deprecated or Fatal entries. Intentional provider-failure contract scenarios can write existing structured application diagnostics; these contain resource classifications rather than shopper addresses. No customer/contact data logging is added by the module.

## Test isolation corrections and limits

- Remote contract mocks must run without the highest-priority offline MU blocker. Running them together initially reported a provinces mismatch and counted blocked fixture attempts. Removing only the disposable blocker restored 18/18 on both sites without changing production code, tests or baseline. Counters were then reset for isolated checkout verification.
- Existing orders must be synchronized before switching HPOS stores. An initial direct test switch hit Woo's pending-sync safeguard; subsequent runs used official `wc hpos sync` / `wc cot sync` before toggling. Production code never changes this setting.
- A preliminary Woo 8.2/PHP 8.3 browser environment produced upstream deprecations. Minimum-version results above use Woo 8.2 with PHP 8.1 instead; the unsupported experimental pairing is not certified.
- A preliminary full-email CLI assertion lacked Woo's email action initialization. The final test renders Woo's native email-address template directly, which is the formatting boundary under test; no production email template was overridden.
- COD/BACS are covered. Other gateways, checkout builders, third-party field editors and provider-specific shipping calculations are not certified. Existing foreign/VN legacy state identifiers are not automatically migrated.
- Checkout Blocks, Store API and My Account editing UI are outside this phase. Dataset transaction/concurrent-read regression checks remain in the existing 65-check sync suite; no new end-to-end concurrent checkout/sync stress benchmark is claimed.

See [WooCommerce architecture](woocommerce.md), [state-code contract](woocommerce-state-codes.md) and [test instructions](../tests/README.md). Test databases, mail interceptors, product/customer fixtures, browser dependencies and logs are disposable and are not shipped as production runtime resources. Local Phase 7 commit only; no push, tag or release.
