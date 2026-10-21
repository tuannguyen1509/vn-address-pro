# WooCommerce state identifier contract

Province administrative code **79** maps to WooCommerce state identifier **VN-79**. In a shipping zone location it is qualified by Woo's country separator: **VN:VN-79**. Tax-rate state uses VN-79 with country VN. The name is obtained from AddressService, never a separate province array.

Rules: literal uppercase VN-, followed by the positive canonical decimal integer without leading zeroes. No padding, label-based keys, numeric coercion of arbitrary text or legacy-name migration. `States::to_wc_state_code(int): string` and `from_wc_state_code(string): ?int` own the conversion. Invalid/overflow/negative/malformed identifiers are rejected. An identifier can be structurally valid without existing in the current dataset; AddressService validates existence and the ward-parent pair separately.

The WC state key, native billing_state/shipping_state properties, shipping-zone state code and canonical province metadata are **persistent integration contracts**. Do not rename the prefix/format after stores have configured zones, taxes or addresses without a separate explicit migration. Dataset name changes do not change identifiers; new requests obtain updated labels. Woo's per-request country-state cache is retained; no separate VNAP Woo state cache exists.

An old/free-text Woo customer state cannot be inferred safely. Checkout displays a safe unselected province/ward when it does not match registered keys and requires a new selection. Historical orders and shipping-zone rules are not migrated. Merchants with prior Vietnam state extensions must review their existing state identifiers, zones and tax rules when adopting this integration.

Ward identity is stored separately as `_vnap_billing_ward_code` / `_vnap_shipping_ward_code`; native city remains the ward's readable name. These ward codes are not WooCommerce states or district identifiers. There is no district field or provider-specific shipping mapping.
