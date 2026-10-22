# WooCommerce My Account

Available with the optional WooCommerce 8.2+ integration. Billing and shipping use Woo's native edit-address routes, country/state fields, nonce/authentication, notices and save flow. There is no replacement account form or mandatory checkout type.

`woocommerce_address_to_edit` adds a native select and a separate canonical-code transport input. JavaScript activates the ward selector for VN, hides the native city row while retaining its readable value, and restores native city editing for other countries. Province remains the single native `billing_state` / `shipping_state` field with `VN-{code}` values. No extra province control or all-ward payload is rendered. Only the current valid ward is included initially; the shared `VNAddressProData` client loads the selected province from same-site REST v1, using page-lifetime cache, abort and sequence protection. Assets load only on authenticated account edit-address requests.

Woo's process-myaccount-city filter resolves the canonical name before native required-field validation. `woocommerce_after_save_address_validation` independently validates the posted state/code through the existing AddressValidator and AddressService. Names from the browser are never trusted. Hostile country/state/city arrays are rejected before Woo's string-oriented processing. After all notices remain clear, the existing CustomerAddress writer updates the exact WC_Customer object Woo is about to save. The post-save hook is not used for a second write; its arguments differ between old and newer Woo releases.

The same Phase 7/8 metadata is used: `_vnap_{billing|shipping}_province_code` and `_vnap_{billing|shipping}_ward_code`. Native city contains the ward name, state contains `VN-{code}`. Foreign saves clear only that address's VN codes. Billing does not update shipping or vice versa. Customer changes never write existing orders. Address overview rendering uses Woo's native formatter and the existing VN format/state list.

Legacy, missing, obsolete or mismatched ward metadata is not guessed from city text. The province can remain selected while the ward stays unselected, requiring a current valid choice before resaving VN. Rendering does not migrate metadata. Dataset changes are picked up on the next request/page reload; there is no persistent Woo ward cache. Healthy local data needs zero external provider calls.

Woo's Users → Profile fields are unchanged; this module scopes its changes to My Account and Order Admin. Bulk conversion, fuzzy matching and customer migration are deferred.

Official source reviewed: [WC_Form_Handler](https://woocommerce.github.io/code-reference/classes/WC-Form-Handler.html), `WC_Shortcode_My_Account::edit_address`, and native `form-edit-address.php` in Woo 8.2.0/11.2.1. See [Phase 9 validation](phase9-validation.md).
