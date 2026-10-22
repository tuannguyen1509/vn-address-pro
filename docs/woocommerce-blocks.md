# WooCommerce Checkout Block

VN Address Pro adds province-dependent ward selection to the official billing and shipping address extension areas. WooCommerce is optional. Supported Block minimum is WooCommerce 8.2.0, matching the existing Classic minimum: this is the HPOS stable baseline and the release introducing `woocommerce_store_api_checkout_update_customer_from_request`, required to canonicalize customer addresses before order creation. Tested minimum pairing: WordPress 6.3.7/PHP 8.1/Woo 8.2.0. Current pairing: WordPress 7.1.3/PHP 8.3/Woo 11.2.1.

## Strategy and automatic availability

Additional Checkout Fields was evaluated and rejected as the selector renderer: its select options are a registration-time array/enum, without the required supported asynchronous province-dependent loader. Registering all wards or many province-specific fields would violate the payload requirements. Conditional visibility is not dynamic option loading.

Instead, `BlocksIntegration` implements Woo's IntegrationInterface. It registers two server block types from block.json and supplies their script through the checkout integration registry. The script registers WordPress block types and `registerCheckoutBlock` components, with the correct address parents and force=true. Woo inserts these components automatically, even when the saved checkout page contains no VNAP children. Activation never edits the checkout page. Registry dependency ordering is required; a late generic enqueue can miss initial checkout hydration.

The shipping block follows Woo's shipping step. The billing block follows Woo's billing step, including virtual carts and separate billing. Woo controls address cards, same-address toggles, and which steps are mounted. The selector occupies the official extension area after the native address form; arbitrary interleaving between native state and street fields is not attempted. Explicitly inserting duplicate extension children manually is unnecessary.

The official `woocommerce_blocks_enqueue_checkout_block_scripts_before` action establishes actual renderer context and dequeues the Classic adapter before footer output. This handles FSE templates that render a Checkout Block even when the page content is a Classic shortcode. Merely asking the integration registry for dependency handles is not a render signal: Woo 8.2 also does that during initialization. Registration therefore has no enqueue/context side effects; styles are enqueued at the actual Block hook. VN city hiding is scoped to Block checkout or Store API routes and does not affect ordinary My Account forms.

WordPress Block Hooks were also investigated during the spike. Insertion into an empty serialized address anchor was not reliable, and combining it with Woo's forced registration duplicated controls. They are not used.

## Address state and UI

Native province state remains `VN-{code}` from the existing core-backed state list. Country locale hides native city for VN and makes it not required during editing; other countries retain native city. The React selector renders ward names and keeps ward codes in namespaced checkout extension data. It never writes a ward code into city.

Woo can clear hidden city values during editing, especially on older releases. The integration updates the local city once per selection rather than fighting that cleanup on every render. Customer/order city is always resolved authoritatively from the submitted ward code before final checkout/payment. A transient empty hidden client city is not a persisted address snapshot. Native public `updateCustomerData` receives snake_case `billing_address` / `shipping_address` payloads to synchronize server addresses and Woo rates/totals; `editing=true` preserves newer local input while responses update totals. Ward changes do not call a separate calculate-totals endpoint.

Billing and shipping keep distinct component state and extension keys. When Woo uses shipping as billing, codes and address updates follow that public checkout state; forced billing-only shipping follows the existing store configuration. No Woo configuration is changed by production code.

## Shared data client and lifecycle

`assets/js/address-data-client.js` provides `VNAddressProData.getWards(code, {signal})`. Both the Phase 6 HTML component and React use it. It owns same-origin URL checks, timeout, schema/parent/duplicate checks, successful page-lifetime cache and abort protection. Aborted responses cannot seed the cache. React also guards its own effect lifetime and binds loaded rows to their province, so stale options cannot become selectable after a province change. No localStorage or initial all-ward payload is used.

Saved codes arrive in the cart extension response. Restoration waits for both matching saved address state and validated ward rows, without consuming the attempt on provisional empty cart hydration data. User changes invalidate old selections. Validation errors use wc/store/validation and are cleaned up on unmount. The integration clears a stale native counterpart state error only when same-address mode is active and both actual VN states match a valid resolved ward selection.

## Assets and dependencies

The Block bundle is authored directly as browser JavaScript using the platform's React `createElement`; no JSX/transpilation or npm production build is needed. Runtime files include checkout.js, checkout.asset.php and two block.json files. WordPress/Woo provide React, wp.data, wp.i18n and Blocks packages as declared externals; no copies are bundled. Plugin users need neither Node nor Composer. Script translations use vn-address-pro.

The Block script is supplied through Woo's checkout integration registry for actual Block rendering and the editor. Classic retains woocommerce-classic.js; the generic HTML API and VNAddressPro namespace remain compatible. No DOM selectors, polling, MutationObserver, React patching, checkout HTML replacement, or private Woo imports appear in the production Block module.

## Limits

COD/BACS, guest/create-account/logged-in, saved address cards, virtual carts, international checkout, HPOS and legacy storage are covered. An official express-button presence probe checks coexistence, not real wallet/payment-network certification. External field editors, checkout replacements, gateways and shipping-provider ward mappings require their own integration tests. Phase 9 supplies separate [My Account](woocommerce-my-account.md) and [Order Admin](woocommerce-order-admin.md) editors. Existing state identifiers/zones from other extensions are not automatically migrated. Woo 8.2 has an upstream empty-cart checkout preload warning on confirmation pages, reproduced with this plugin deactivated; see the validation report for the exact scope.

See [Store API contract](store-api-integration.md), [capability and validation report](phase8-validation.md), and [state identifiers](woocommerce-state-codes.md).

Official sources: [registry/forced blocks](https://github.com/woocommerce/woocommerce/blob/11.2.1/plugins/woocommerce/client/blocks/packages/public-api/blocks-checkout/blocks-registry/README.md), [additional fields](https://developer.woocommerce.com/docs/block-development/extensible-blocks/cart-and-checkout-blocks/additional-checkout-fields/), [cart store](https://developer.woocommerce.com/docs/block-development/reference/data-store/cart/), [extension data](https://developer.woocommerce.com/docs/apis/store-api/extending-store-api/extend-store-api-add-custom-fields/).
