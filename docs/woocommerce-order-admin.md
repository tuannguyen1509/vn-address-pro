# WooCommerce Order Admin address management

The module extends the existing Order Data billing/shipping sections, using Woo admin field filters and native labels/selects. It creates no parallel metabox and does not register Additional Checkout Fields. Phase 8 uses forced inner blocks, so Woo has no additional-field metadata to duplicate in this editor.

Both HPOS and legacy edit screens are detected with Woo screen APIs. The shared management script loads only on order editing screens, and uses the same ward data client as the generic component and Checkout Blocks. Only the currently valid ward is server rendered; other ward options are loaded for the selected province via public same-site REST. Native country/state controls remain authoritative and international city editing stays available.

## Persistence and validation

The native order form includes an order-bound VNAP nonce. Woo retains responsibility for its own authentication, permissions and form nonce. At `woocommerce_process_shop_order_meta` priority 39, immediately before native Order Data at 40 and after items/totals at 10, the adapter compares posted country/state/city/ward code against the stored order. This avoids consuming pending address changes during an earlier totals save. Changed selections must pass the existing AddressValidator/AddressService relationship checks and the additional nonce check. Invalid selections are restored to their prior native country/state/city before Woo processes its fields; a native admin error is recorded. Other order properties and an independently valid address may still save. The plugin never silently persists an invalid canonical combination.

Validated selections are held only for that order ID in the current request. `woocommerce_before_order_object_save` applies the existing canonical OrderAddress writer after native properties are set and before Woo's datastore save. This CRUD hook supports Woo 8.2 as well as the newer runtime. There is no direct order posts/postmeta access or separate save that could overwrite Woo's pending properties.

The existing four code metadata keys and province-name snapshots are reused. City is the resolved ward-name snapshot; state stays `VN-{code}`. Country changes away from VN clear only the edited address's canonical/snapshot metadata. Order editing never writes the customer's current address. Native explicit customer-update actions remain Woo's responsibility.

Woo 8.2 field filters provide only the field array; its renderer initializes the official `$theorder` context. Newer Woo passes the order and view/edit context directly. The adapter supports both. Old Woo also overwrites the shipping field value and has no field update_callback: a custom initial-code attribute seeds the browser control, and transport inputs are consumed before the native save loop. Thus no `_billing_vnap_*`, `_shipping_vnap_*` or admin-specific metadata is persisted. Registration avoids evaluating translated field labels before init.

## Native customer load and copy

`woocommerce_ajax_get_customer_details` adds validated `vnap_ward_code` transport values to Woo's existing permission/nonce-protected response. Native load billing/shipping loops populate the corresponding hidden `_billing_*` / `_shipping_*` inputs and emit changes. Native copy billing → shipping copies the same transport input. A coalesced, idempotent field refresh reads the completed native update and reloads ward options. No parallel customer AJAX endpoint, polling or global MutationObserver is used. Missing/invalid customer metadata stays unselected; text is never guessed.

## Historical snapshots

Opening an order does not write it. Rendering and an unchanged save preserve stored country/state/city/code/name snapshots, including missing or obsolete ward codes. They are not reconciled against today's dataset merely because an unrelated order field is saved. The editor explains when no current ward is selected. An intentional province/ward/country/city change requires current valid data; unchanged address lines can retain the old administrative snapshot. A changed selection receives fresh canonical names, while all other orders and the customer remain untouched.

Native formatted address display uses stored snapshots through the Phase 7 filters. Removed/renamed wards therefore remain readable historically. No bulk migration or automatic conversion is included.

Official source reviewed: [WC_Meta_Box_Order_Data](https://woocommerce.github.io/code-reference/classes/WC-Meta-Box-Order-Data.html), Woo's HPOS Edit class, WC_Data CRUD save hooks and native `meta-boxes-order.js` in Woo 8.2.0/11.2.1. See [validation](phase9-validation.md).
