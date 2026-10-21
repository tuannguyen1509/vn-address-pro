# Reusable frontend address component

The component renders fields only. It does not submit/save addresses, create users, send mail or integrate with WooCommerce/forms. Normal data reads use the existing AddressService/local-first repository; no dataset, schema or sync behavior is changed.

## Shortcode

```text
[vn_address_fields]

[vn_address_fields province_name="customer_province" ward_name="customer_ward" address_name="customer_address" show_address="yes" required="yes"]

[vn_address_fields default_province="79" show_address="no" province_label="Province" ward_label="Ward"]
```

Each instance supports:

| Argument | Default / meaning |
| --- | --- |
| province_name, ward_name, address_name | Code/text input names: vnap_province, vnap_ward, vnap_address |
| province_hidden_name, ward_hidden_name | Display-name input names: vnap_province_name, vnap_ward_name |
| province_label, ward_label, address_label | Translatable Vietnamese field labels |
| province_placeholder, ward_placeholder, address_placeholder | Translatable Vietnamese prompts |
| default_province, default_ward | Positive numeric codes, empty by default |
| default_address | Initial address line, empty by default |
| show_address | yes (default) or no |
| required | no (default); yes requires all visible fields |
| province_required, ward_required, address_required | Override required individually; yes/no or PHP booleans |
| class | Whitespace-separated class tokens; sanitized and prefixed vnap- |

Field-name arguments change **input names**, not the canonical identifier: province and ward select values are always codes. Bracketed names such as customer[province] are supported. Use distinct names, including hidden-name arguments, when submitting multiple components in one form; default names repeat by design. Invalid attribute types/names fall back safely. Labels/placeholders are plain text, not an HTML slot.

There is no enclosing form or submission handler. The consuming application owns form structure, permissions, CSRF protection for writes, requiredness and server validation.

## PHP API

```php
use Tunlamweb\VNAddressPro\Frontend\AddressComponent;

echo AddressComponent::render( array(
    'province_name' => 'customer[province]',
    'ward_name' => 'customer[ward]',
    'province_hidden_name' => 'customer[province_name]',
    'ward_hidden_name' => 'customer[ward_name]',
    'show_address' => false,
) );

// Equivalent prefixed convenience helper, also returns a string:
echo vnap_render_address_fields( array( 'required' => true ) );
```

AddressComponent obtains provinces through AddressService; FieldRenderer contains no SQL/provider calls. Province options are server rendered without wards. Initial ward code is passed separately; JavaScript fetches only that province and selects the ward if it belongs to the response. A province that cannot be rendered is disabled with a translated error. Reload after restoring server availability; the retry button specifically retries ward requests.

`vnap_frontend_component_config($args)` is the single configuration filter, applied before sanitization. It must return an argument array. No renderer action hook or separate per-label hooks are introduced without a concrete need.

## Assets and HTML

Rendering enqueues vnap-address script/CSS handles once, versioned with VNAP_VERSION, with no jQuery dependency. Nothing is globally enqueued from wp_enqueue_scripts, and wp-admin does not receive these assets. The script is printed in the footer; WordPress [prints late styles](https://developer.wordpress.org/reference/functions/print_late_styles/) for components rendered after wp_head.

For a template that knows it will render fields, call `Frontend\Assets::enqueue()` from its early wp_enqueue_scripts hook to place CSS in the head and avoid an initial unstyled moment. The theme must call the normal wp_head/wp_footer hooks. Render before footer scripts print. An AJAX fragment cannot enqueue already-missed host-page assets: the consuming integration must first enqueue Assets on its host page, insert the HTML and call init(). No global MutationObserver is installed.

The root is `.vnap-address[data-vnap-address]`. Descendants use data-vnap-province, data-vnap-ward, data-vnap-province-name, data-vnap-ward-name and optional data-vnap-address-line. Unique wp_unique_id-generated IDs connect labels/status/error descriptions; JavaScript queries within each container, not global field IDs. Custom classes use vnap-. CSS is minimal, stacked, responsive and scoped; `--vnap-gap` customizes spacing. Native select/input appearance remains theme controlled. No fonts, brand palette or !important rules are imposed.

Native labels/selects support keyboard/mobile controls. required/aria-required and validation aria-invalid are provided; loading uses a polite status region and errors use role=alert. Loading/error clears stale ward selections and disables the ward. Retry is a type=button control; it never submits a form. Focus is not forcibly moved. Without JavaScript, province options remain available, but dependent ward selection requires JavaScript and has a noscript explanation.

## JavaScript API

```javascript
VNAddressPro.initAll(); // Idempotent; accepts an optional root subtree.
const instance = VNAddressPro.init({ container: element });
const same = VNAddressPro.getInstance(element);
await instance.ready; // Initial selection finished, including controlled failure.
await instance.setProvince('79'); // Promise<boolean>; clears old ward immediately.
instance.setWard(wardCode); // boolean, only accepts a ward in the current loaded list.
instance.getProvince(); // { code, name }
instance.getWard(); // { code, name, provinceCode }
instance.getAddress(); // { province_code, province_name, ward_code, ward_name, address }
instance.validate(); // { valid, errors: [{ field, message }] }
await instance.reset(); // Restore this instance's original rendered defaults.
instance.destroy(); // Abort, unbind, remove registry entry and release DOM references.
```

Codes returned by JavaScript are strings; empty selection uses ''. init(element) is also accepted. Invalid/unmarked/incomplete containers return null. Repeated initialization returns the existing instance. initAll runs automatically at DOM readiness. Dynamic integrations call init explicitly after insertion and destroy before removal. Destroyed instances are terminal; initialize the DOM again for a replacement. Native form reset restores the original selections after the browser resets controls, unless the reset event is cancelled.

Client validate() checks required code/address fields and known ward membership, updates accessible error state and returns errors; it does not intercept the consumer's form submission. Disabled native selects are not successful form controls and are skipped by native constraint validation. Consumers must call validate() as appropriate and always validate server-side, especially if making ward required independently of province. Hidden names are convenience display values, never trusted identifiers.

The browser uses only the same-origin wards URL generated by PHP rest_url(). URLSearchParams preserves query-style rest_route URLs and subdirectory installations. Public GET needs no nonce. HTTP status, envelope/count, province, ward parent, code uniqueness and name types are checked. Remote exception texts are not shown. Invalid JSON/network/schema errors produce translated UI messages and vnap:error, without uncaught rejection. Option text uses DOM text APIs, never innerHTML.

Every new province selection aborts the previous request and increments a sequence number. Even a response from a fetch implementation that ignores abort cannot replace newer state or seed the cache. Requests time out after 20 seconds. Successful ward arrays are shared in a page-local Map keyed by full REST URL/province. There is no localStorage/sessionStorage or persistent browser dataset. Concurrent uncached instances may make independent requests; in-flight Promise deduplication is intentionally omitted to keep cancellation independent. Reload clears the map; a dataset updated while the page stays open becomes visible after reload.

## Events

All events are bubbling CustomEvents dispatched on the component container. Register listeners before init if initial lifecycle events matter. Initial preselection also emits selection/loading events; ready fires once after initialization settles.

| Event | detail | Timing |
| --- | --- | --- |
| vnap:ready | Address snapshot with five fields | Initial load/preselection settles |
| vnap:province-changed | { code, name } | Province selected/reset, old ward cleared |
| vnap:wards-loading | { provinceCode } | Current ward load starts, including cache lookup |
| vnap:wards-loaded | { provinceCode, count } | Valid wards populated, including empty list |
| vnap:ward-changed | { code, name, provinceCode } | Ward selected or cleared, including parent changes |
| vnap:error | { code, message, provinceCode } | Controlled request or invalid-ward error |

```javascript
element.addEventListener('vnap:ward-changed', event => {
    const { code, name, provinceCode } = event.detail;
    // Consumer-owned behavior; codes remain canonical.
});
```

## Server selection validation

```php
$selection = \Tunlamweb\VNAddressPro\Plugin::instance()
    ->address_service()->validate_selection( $province_code, $ward_code );
if ( is_wp_error( $selection ) ) {
    // Reject the submitted selection; render an escaped consumer-owned error.
} else {
    $province = $selection['province'];
    $ward = $selection['ward'];
    // Names are resolved by the server, never copied from hidden submitted inputs.
}
```

The additive method accepts positive integer codes or digit strings (including leading zeroes), rejects malformed/overflow/negative/boolean/fractional inputs, verifies province existence, ward existence and parent relationship, and returns normalized authoritative records or WP_Error. It does not change permissive v1 parameter coercion or any public REST response. It performs read-only lookup, not sync/import/submission handling.

See tests/README.md and [Phase 6 validation](phase6-validation.md) for the verified browser/PHP matrix. Phase 7 adds an optional [Classic WooCommerce adapter](woocommerce.md) around this same instance API; the generic component remains vanilla and independent. Form adapters and new REST endpoints are not included.
