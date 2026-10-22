# WooCommerce core integration and Classic Checkout

VN Address Pro remains an independent address core. WooCommerce is optional. The module registers once after plugins load only when WC_VERSION meets **8.2.0**. Older/absent Woo leaves REST, frontend fields and dataset administration operational; System Status reports detected/unsupported/inactive state.

The baseline is justified by [WooCommerce 8.2's stable/default HPOS release](https://developer.woocommerce.com/2023/10/10/woocommerce-8-2-0-released/); order/customer CRUD and Classic hooks used here predate it. Validation covers 8.2.0 on PHP 8.1/WordPress 6.3.7 and 11.2.1 on PHP 8.3/WordPress 7.1.3. Core requirements remain PHP 8.1 / WordPress 6.3. Use a PHP/WordPress combination supported by the installed Woo version; minimum Woo is not a recommendation to keep an old store release.

## Scope and sources reviewed

The module supports the configured checkout page containing `[woocommerce_checkout]`, including billing/shipping, checkout validation/creation/customer save and native shipping/taxes. It uses the current [Classic field hooks](https://developer.woocommerce.com/docs/code-snippets/customising-checkout-fields/) and [HPOS CRUD/declaration APIs](https://developer.woocommerce.com/docs/features/orders/high-performance-order-storage/recipe-book/).

The installed production distribution source was reviewed in class-wc-checkout.php (get_posted_data, validation, process_customer and create_order), class-wc-countries.php (states/locale/format), class-wc-order.php (formatted addresses), assets/js/frontend/checkout.js and country-select.js (native changes, country_to_state_changed, update_checkout, updated_checkout). Current Woo 11.2 uses newer notice markup on Classic Checkout; integration does not replace its notices.

Checkout Block and Store API use the separate Phase 8 module described in [Blocks integration](woocommerce-blocks.md). FeaturesUtil declares tested Cart/Checkout Blocks compatibility after acceptance validation. Detection uses page content plus the actual Block render signal. Classic assets and mutation callbacks skip Block, unknown-page and REST contexts. Global province states and historical order formatting are shared. No custom Cart UI is added. My Account and Order Admin use their separate Phase 9 modules; field-editor adapters, legacy migration and shipping-provider APIs remain deferred.

## Mapping and fields

| Woo field | VN meaning | Stored value |
| --- | --- | --- |
| billing_state / shipping_state | Province | VN-{administrative code} |
| billing_city / shipping_city | Ward | Canonical readable ward name |
| billing_address_1 / shipping_address_1 | House/street | Customer-entered text, preserved |
| address_2 | Optional additional line | Retained |
| postcode | Postal code | Retained with Woo VN optional behavior |

States come from the existing AddressService/local-first repository. VN country locale enables/relabels state and city, orders province/ward/street, and retains address_2/postcode/phone/email. Other countries' locale/state behavior is untouched. See [state code contract](woocommerce-state-codes.md) before configuring shipping zones/taxes.

## Frontend adapter

Only a Classic checkout page (excluding order endpoints/admin/cart/product/blog) enqueues woocommerce-classic.js with dependencies vnap-address, wc-checkout, wc-country-select and jQuery. The Phase 6 core remains vanilla. Its successful ward cache, request cancellation, sequence guards, validation and events are reused; the adapter contains no ward fetching or response parsing.

The native Woo state select remains under Woo control, including its own SelectWoo enhancement/classes. A hidden numeric province bridge maps the selected WC state through a server-generated identifier map. The existing city input is retained as the readable model and enhanced with a native ward select; a separate registered hidden checkout field carries `vnap_billing_ward_code` / `vnap_shipping_ward_code`. Only province labels/codes are embedded, never all wards. Data is fetched through the same-site REST v1 route and healthy local data produces no remote provider calls.

Billing/shipping instances are independent. Woo's native state change drives totals; a user ward change updates the city and triggers its native change handler. Initial/programmatic restoration emits component events without triggering checkout updates. Namespaced updated_checkout/country_to_state_changed handlers reconnect replaced fields idempotently and restore a still-valid saved code. There is no interval, global DOM observer or checkout-section replacement.

VN → another country destroys the VN instance, clears its code/name, restores the original native city input and label association, and leaves country/state/postcode handling to Woo. Switching back to VN initializes once with an unselected ward. Canonical names are not inferred from legacy city text. If a field editor replaces city with a non-input control, the adapter skips that unsupported markup rather than throwing; VN checkout still requires canonical validation. Such editors need a separate adapter, not a validation bypass.

## Server validation and persistence

woocommerce_checkout_posted_data resolves codes with a strict States parser plus Phase 6 AddressService::validate_selection. It replaces submitted city names with authoritative names and retains a controlled validation error for unknown/mismatched/missing selections. Raw state labels/lowercase prefixes are not accepted merely because Woo's later native validator can map a label to a state. Hostile arrays are cleared before Woo scalar validators while the rejection is retained. Street data is not changed.

woocommerce_after_checkout_validation adds translated field-scoped errors before customer/order creation. Native Woo checkout nonce, payment and required-field checks remain in place. No custom public write route or nonce is added. Invalid JS-off/text-only submissions cannot bypass canonical ward checks; without the adapter a shopper must restore working JavaScript to select a code, and the server fails safely.

Physical same-as-billing shipping uses Woo's copied address fields plus the validated billing ward code. Different shipping addresses validate independently. If Woo omits shipping entirely (for example a virtual cart), no artificial shipping address is created.

Order and customer hooks write through WC_Order/WC_Customer only, before Woo's existing save. No direct SQL/postmeta API or redundant save is used. Codes are validated integers; foreign addresses clear obsolete VNAP codes when saved through this checkout. Guest orders receive metadata without requiring an account; newly created and logged-in accounts receive customer codes in process_customer.

Metadata:

```text
_vnap_billing_province_code
_vnap_billing_ward_code
_vnap_shipping_province_code
_vnap_shipping_ward_code
```

Orders additionally store `_vnap_billing_province_name` / `_vnap_shipping_province_name`. Ward name is already a native city snapshot, so it is not duplicated. Province name needs a separate snapshot because Woo normally resolves state labels from the current dataset. Order-specific formatted-address filters use that historical province name without changing the stored native state identifier. Customer changes/dataset updates never rewrite previous order addresses. Old orders without these keys pass through safely.

Woo's native VN address format omits state. A country-specific format filter inserts `{state}` once when no state placeholder exists, preserving other countries and formats that already include it. Native email/thank-you templates and formatter are retained; no extra concatenated province or template override is used. Guest email verification on newer Woo remains intact. No full customer address/phone/email is logged by the integration.

## HPOS and operational limits

After CRUD validation in both stores, FeaturesUtil declares custom_order_tables=true on before_woocommerce_init using VNAP_FILE. The same hooks work with HPOS and legacy storage. Tests switch stores using Woo's supported synchronization process; this integration never changes a merchant's HPOS setting or synchronizes Woo order tables itself.

No integration settings/toggles or postcode-hiding options were added. Supported Woo loads the module automatically. Settings → VN Address → System Status shows Woo version, integration status, checkout type, HPOS mode and Block capability status.

Checkout supports COD/BACS through native Woo lifecycle; no production gateway-specific logic is present. Shipping zones and taxes use deterministic province state keys. Existing zones/taxes based on a different extension's Vietnam keys need merchant review; there is no automatic migration. Advanced third-party field editors, custom checkout builders, payment/address autocomplete replacements and provider-specific ward shipping calculations are not certified.

Phase 8's [Checkout Block module](woocommerce-blocks.md) and [Store API contract](store-api-integration.md) reuse these native fields and metadata. Phase 9 adds [My Account](woocommerce-my-account.md) and [Order Admin](woocommerce-order-admin.md) through native form/CRUD hooks. Each UI scopes its VN city enhancement to its own address editor. Classic retains its own hooks and adapter.

See [Phase 7 validation](phase7-validation.md), [Phase 8 validation](phase8-validation.md) and [Phase 9 validation](phase9-validation.md) for exact tests, versions and limitations.
