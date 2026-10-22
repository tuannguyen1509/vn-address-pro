# Phase 8 capability gate and validation

Status: PASS. Capability spike, implementation and acceptance validation completed on 22 October 2026. Cart/Checkout Blocks compatibility was enabled only after the Block and Classic acceptance suites passed, then the declaration and lifecycle were rechecked. Local commit only; no push, tag or release.

## Capability spike before production changes

Inspected official WooCommerce 11.2.1 distribution and tagged source, current developer documentation, and WordPress 7.1.3 source. Disposable runtime: PHP 8.3, Woo 11.2.1, WordPress 7.1.3, Playwright 1.64.0/Chromium. Production tree was unchanged during the spike.

Strategy A is insufficient: SelectFieldType requires a registration-time array of options and prepares an enum from it. Conditional visibility/required rules do not provide a supported asynchronous per-province option loader. Address-location fields do provide separate billing/shipping persistence, but cannot alone implement the required dependent select without shipping all wards.

Strategy B is viable: registerCheckoutBlock supports address parents and force=true. A real browser probe registered separate shipping/billing children via IntegrationInterface and rendered the shipping probe automatically on an existing Checkout Block page whose stored content contained no extension child blocks. No editor visit, page rewrite, DOM manipulation or Woo patch was required. Billing is naturally hidden while Woo uses shipping as billing. The registration script must be a dependency supplied through the integration registry; simply enqueueing it after checkout hydration misses initial component discovery.

WordPress Block Hooks were also evaluated, but are unnecessary: last_child insertion did not run for an address anchor with no serialized children, while a populated-anchor control did insert. Do not combine them with Woo's force mechanism (that duplicates children already present).

Native VN state options appeared with VN-79. Public cart customer selectors, checkout same-address selector, and validation actions were present. Country locale is consumed by prepare-form-fields.ts, including hidden flags. Store API source exposes cart/checkout customer update hooks and checkout order-from-request hooks with CRUD persistence before payment. IntegrationInterface supplies frontend/editor dependencies and settings. The implementation reuses Phase 7 state/CRUD contracts and extracts the Phase 6 ward data client for both renderers.

Sources:

- [Additional checkout fields](https://developer.woocommerce.com/docs/block-development/extensible-blocks/cart-and-checkout-blocks/additional-checkout-fields/)
- [Tagged registry documentation and forced blocks](https://github.com/woocommerce/woocommerce/blob/11.2.1/plugins/woocommerce/client/blocks/packages/public-api/blocks-checkout/blocks-registry/README.md)
- [Tagged registration implementation](https://github.com/woocommerce/woocommerce/blob/11.2.1/plugins/woocommerce/client/blocks/packages/public-api/blocks-checkout/blocks-registry/register-checkout-block.ts)
- [Cart store public API](https://developer.woocommerce.com/docs/block-development/reference/data-store/cart/)
- [Checkout extension data](https://developer.woocommerce.com/docs/apis/store-api/extending-store-api/extend-store-api-add-custom-fields/)
- [WordPress 6.8 post-content Block Hooks](https://make.wordpress.org/core/2025/03/25/miscellaneous-developer-changes-in-wordpress-6-8/)

## Runtime and datastore matrix

All sites, accounts, orders and payment fixtures were disposable. Chromium ran in Playwright 1.64.0, outside the repository. Outbound HTTP and mail were intercepted; no live payment was made.

| Runtime | Storage | Block PHP | Block browser | Direct Store API | Classic PHP | Classic HTTP |
| --- | --- | --- | --- | --- | --- | --- |
| WP 6.3.7 / PHP 8.1 / WC 8.2.0 | HPOS | 23 PASS | 29 PASS | 23 PASS | 47 PASS | 20 PASS |
| WP 6.3.7 / PHP 8.1 / WC 8.2.0 | Legacy | 23 PASS | 29 PASS | 23 PASS | 47 PASS | 20 PASS |
| WP 7.1.3 / PHP 8.3 / WC 11.2.1 | HPOS | 23 PASS | 29 PASS | 23 PASS | 47 PASS | 20 PASS |
| WP 7.1.3 / PHP 8.3 / WC 11.2.1 | Legacy | 23 PASS | 29 PASS | 23 PASS | 47 PASS | 20 PASS |

Storage switches used Woo's official synchronization commands before changing the enabled datastore. Classic browser checks: 20 PASS on both runtime pairings with HPOS. Block rendering was exercised with both FSE and Classic themes. The minimum Classic regression used Twenty Twenty-One so its checkout shortcode was actually rendered rather than overridden by an FSE Checkout template. Production contains no theme-specific branch.

The final capability-declaration change was followed by 47 Classic PHP checks, 18 REST comparisons, plugin deactivate/reactivate and the standalone RequirementsTest on both runtimes. Block PHP/browser and Store API checks were also rechecked with HPOS. No duplicate class, hook or route errors occurred.

## Existing tests and public contracts

With WooCommerce inactive, both runtime pairings passed FoundationTest, 18 REST baseline comparisons, 42 CoreTest checks, 60 reliability checks, 40 local repository checks, 65 sync/admin checks, four offline REST routes with zero outgoing HTTP, 32 frontend rendering checks and Woo absent/unsupported checks. Standalone requirements handling passed under PHP 8.1 and 8.3.

The unchanged frontend browser runner passed all 41 checks on both runtimes, including abort-insensitive reversed responses, fulfilled cache reuse, reset/destroy, keyboard/mobile layout and console checks. Admin HTTP passed 30 checks on the newer pairing. An initial newer-runtime mobile assertion failed immediately after viewport resizing; a separate geometry probe and the complete unchanged 41-check rerun passed. No production CSS or test expectation was changed to hide that result.

Existing tests updated:

- Frontend RenderTest: internal dependency assertion and localization handle follow the extracted shared data client. Public markup/API expectations remain unchanged.
- Woo IntegrationTest: only the old Blocks-incompatible feature assertion now checks the supported API capability. The other Classic assertions are retained.
- Woo OfflineFixture: the test-only protected report adds independent shipping state and province-code observations. No production report or endpoint was added.

REST response schema unchanged: **YES**. Baseline fixture changed: **NO**. Frozen SHA-256 for `tests/Contract/fixtures/rest-v1.json`:

```text
436c9637f0444bacc719beab0057bdcbe8a5dc49af0ce4ea8a979b792bce5e62
```

No dataset, schema, sync provider, REST controller or legacy facade was modified. Classic state identifiers and order/customer metadata keys are unchanged.

## Block acceptance coverage

- Automatic forced billing/shipping children without editing checkout content; Classic adapter absent from Blocks; editor metadata/dependencies registered.
- Native VN province list, canonical ward values/readable labels, independent addresses, same-address mode, VN/international transitions and invalidation after province changes.
- Actual guest BACS, create-account COD, deliberate payment failure/retry, logged-in checkout, repeated saved-address reload and billing-only virtual orders.
- Customer/order CRUD, all four canonical code keys, ward/province name snapshots, human-readable native formatting and historical order preservation.
- Deliberately obsolete fetch response cannot replace the newer React selection; final Store API customer state matches the final UI.
- Native Nonce/Cart Token transport and direct POST rejection of missing/unknown/mismatched/malformed codes, raw state labels, unknown extension keys and incomplete shipping selections. Foreign checkout without extension codes remains valid.
- Province shipping-zone/tax behavior and native request-queue recalculation, official express-button coexistence, zero browser external requests and zero address-provider HTTP attempts during checkout.

The express probe is an inert official payment-registry button. It verifies presence/coexistence, not wallet or gateway certification. The intentional native payment failure is asserted separately; only its expected 400/402 resource console message is excluded from unexpected browser errors.

## Implementation findings and harness corrections

The public cart action takes snake_case address keys. Using camelCase silently misses server updates; the reversed-response test exposed that issue. `editing=true` lets Woo update totals without overwriting newer local inputs. The native hidden city can be cleared during editing, so the integration writes once per selection and resolves canonical city authoritatively before payment instead of entering a render loop.

Woo 8.2 does not expose newer store descriptors globally; stable public store names are used. Its integration registry asks for handles during global initialization, so that method must not enqueue styles or establish checkout context. The official pre-enqueue Checkout Block action supplies the actual render signal, including FSE template overrides. City hiding is separately tested against unrelated REST and ordinary account contexts.

Raw input is retained before REST sanitization because Woo can normalize state labels and discard unknown extension properties. Optional nullable code schema handles older Woo's international defaults; final VN validation still rejects absent codes. Account creation happens after the customer update hook, so the order-processed hook copies only selections validated for that exact order object into newly created customer metadata.

Test setup corrections were confined to fixtures: native address/payment changes are allowed to finish their debounced updates before submit; shipping rates are identified semantically rather than by an installation-specific numeric method ID. RequirementsTest runs with `php`, not `wp eval-file`. A blocked theme-install/update request and the earlier incorrect CLI invocation were environment/harness failures, not checkout failures. Debug logs were reset before the final controlled validation window.

## Syntax, diagnostics and release boundaries

All 83 PHP files passed PHP 8.1 syntax checking. Production Block, shared client and vanilla scripts passed `node --check`; the test runners were syntax checked too. The minimum site's plugin path is a symlink, so lint uses `find -L` to check actual files. No npm production build is required: committed browser JavaScript uses WordPress/Woo-provided externals and the committed dependency asset file.

Final controlled WP_DEBUG/WP_DEBUG_LOG checks found no new plugin PHP Warning, Notice, Deprecated, Fatal or Parse errors. The newer runtime log was clean. On Woo 8.2, order confirmation pages emitted an upstream `Undefined array key "/wc/store/v1/checkout"` warning from Woo's `Domain/Services/Hydration.php:51`: its checkout GET returns `woocommerce_rest_cart_empty`, WordPress omits the unsuccessful preload entry, and Woo reads that entry without a guard. A separate request to the same confirmation page with VN Address Pro **deactivated** reproduced the same warning. This is a documented upstream limitation, not a claim that the minimum-runtime log contains zero warnings. Woo code and error reporting were not patched or suppressed. Temporary request instrumentation recorded only route/method/error codes and was removed afterwards.

Structured diagnostics deliberately generated by failure-path core tests are expected. Browser console/exception checks passed. Production Block code has no DOM selectors/manipulation, polling, private React imports or initial full ward payload. Git scope/whitespace, secret and artifact checks found no credentials, dependencies, test WordPress, logs or generated databases to commit.

Known limits: the upstream Woo 8.2 empty-cart confirmation preload warning described above; the ward selector occupies the supported address extension area after the native form and is not interleaved into native fields. Third-party checkout replacements, field editors, shipping-provider mappings and real express gateways need separate certification. My Account and Order Admin address-editing UI are not implemented. Existing external state-zone mappings are not migrated. Ward cache is page-lifetime only; reload obtains the current dataset.

Phase 9 recommendation: WooCommerce My Account + Order Admin, using the same canonical validation/CRUD contracts. No Phase 9 implementation is included.
