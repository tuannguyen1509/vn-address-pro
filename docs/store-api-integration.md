# Store API address contract

The normal Woo endpoint `/wc/store/v1/checkout` processes checkout, using its existing Nonce/session or Cart Token authentication. No custom checkout submission endpoint or authentication is added. The existing VN REST v1 remains unchanged.

The registered `extensions` namespace is `vn-address-pro`:

```json
{
  "billing_address": { "country": "VN", "state": "VN-79", "city": "client display name" },
  "shipping_address": { "country": "VN", "state": "VN-1", "city": "client display name" },
  "extensions": {
    "vn-address-pro": {
      "billingWardCode": "26882",
      "shippingWardCode": "1"
    }
  }
}
```

This illustrates field placement only, not a valid complete order or guaranteed ward-parent pair. Callers must supply the other native checkout/payment fields and valid current dataset codes. Native province uses the unchanged strict Phase 7 mapping. Ward keys are independent, maximum 12 characters, string or null in schema. Null/omitted values are accepted only where that VN address is not required (foreign addresses or shipping for a virtual cart); a required VN selection is rejected by server validation. Nullable schema avoids old Woo inserting invalid null defaults into optional international extension values. Unregistered keys inside this namespace are rejected, including keys Woo's sanitizer would otherwise silently discard.

## Validation and persistence timing

1. A narrowly scoped WordPress `rest_pre_dispatch` filter retains raw country/state and extension keys for POST `/wc/store/v1/checkout` in a request-keyed WeakMap. Woo can map arbitrary state labels to identifiers during REST sanitization, so validating only the sanitized string would weaken the strict Phase 7 contract. The filter does not bypass authentication, change responses or validate unrelated routes.
2. `woocommerce_store_api_checkout_update_customer_from_request` resolves the effective country/state (including partial native address updates) and ward through the existing AddressValidator / AddressService::validate_selection. Missing, unknown, malformed and mismatched VN values throw a controlled Store API RouteException before payment. Submitted city/display names are ignored when resolving the canonical name. Foreign addresses do not require VN codes.
3. `woocommerce_store_api_checkout_update_order_from_request` writes canonical order properties and metadata through the existing OrderAddress class, before Woo's save/payment. Virtual carts require billing only. PATCH draft updates and order-pay endpoints are not forced through the full address requirement.
4. `woocommerce_store_api_checkout_order_processed` fills missing canonical customer metadata after Woo creates an account. Native account synchronization copies native addresses but not extension-owned metadata. This hook uses only selections validated for that exact order object; an existing customer with identical codes is not saved redundantly. It runs before payment, so failed-payment retry retains the new account's valid address metadata too.

WC_Order and WC_Customer CRUD are used in all cases. There is no direct order SQL/postmeta dependency. Classic and Block orders share:

```text
_vnap_billing_province_code
_vnap_billing_ward_code
_vnap_shipping_province_code
_vnap_shipping_ward_code
```

Orders also retain the Phase 7 province-name snapshots; native city stores the ward-name snapshot. Address formatting/email/thank-you reuse the same Phase 7 filters. Customer edits never rewrite previous orders. No separate Additional Checkout Field meta copies or customer-facing numeric ward output are created.

## Cart reads and writes

`CartSchema` exposes only current matching saved selections:

```json
{
  "extensions": {
    "vn-address-pro": {
      "billing": { "state": "VN-79", "wardCode": "26882" },
      "shipping": { "state": "VN-1", "wardCode": "1" }
    }
  }
}
```

Again, example codes show shape only. Each saved pair is validated against the current local core before exposure. Invalid/foreign/unavailable pairs return empty strings; no names, phones, emails, streets, administrative status or full datasets are added to this extension response. Native Woo responses retain their own authenticated cart fields.

During editing, public wc/store/cart actions manage addresses and native recalculation. Ward requests use the existing same-site VN REST URL. Cart editing can be incomplete; full authoritative ward/city validation occurs on the final checkout POST before payment, not on every partial draft. Shipping-zone/tax calculation continues using the stable native state key. The module logs no shopper details and adds no diagnostic response fields.
