# WPForms Lite address field

Version 2.0.2 adds an optional **Địa chỉ Việt Nam** standard builder field. It is
one address group containing dependent Province and Ward selects, both using the
existing AddressComponent, REST API, data client and authoritative AddressService.
Add multiple independent groups by adding the field multiple times. Add WPForms'
native Single Line Text field separately for a street address.

## Support and setup

- WPForms **Lite 2.0.3.1+**, detected through native APIs and version checks.
- Tested on Lite 2.0.3.1, WordPress 7.1.4 and PHP 8.3.36. Older WPForms versions
  are not advertised. The core requirements remain unchanged.
- Pro is intentionally unavailable for this adapter pending certification of its
  conditional/repeater/conversational/entry behavior. No paid package is bundled.
- In the native builder, add **Địa chỉ Việt Nam**, edit its label/description and
  Required toggle, save, and embed the form normally. No custom HTML, field-ID
  references or CSS-class configuration is needed.
- Lite does not provide native entry storage. This adapter does not add an entry
  database, enable Lite Connect, or send entries to a cloud service.
- Popup/dynamically inserted forms outside the initial native render are not yet
  certified. No polling or global observer is introduced to guess host lifecycles.

## Validation and values

The server validates scalar province/ward codes and their relationship. Optional
groups may be completely empty; partial groups, array attacks, unknown codes and
wrong-parent selections are rejected in WPForms' native error bag. Submitted hidden
display names are ignored. Generic shortcode/PHP consumers must still validate their
own submissions; rendering a component is not a general form-submission handler.

The formatted field has a readable `value` for native notifications/Smart Tags,
`province_code` and `ward_code` string properties and JSON `value_raw` containing
those codes. This implementation uses the native field class' `validate` and
`format` lifecycle. It does not claim Pro export/storage certification.

## Shared search and mobile UI

The shortcode/PHP component now renders search inputs with accessible labels and
44px touch targets. Search matches accent-insensitively, including đ/d. Selected
values remain selected even when the query does not match; filtering never chooses
a different code. Changing province clears ward and ward search. Reset and destroy
restore full option lists. Multiple instances remain independent; loading/error/
retry and abort-insensitive response guards are retained.

Use `[vn_address_fields searchable="no"]` or PHP `array('searchable' => false)` to
keep only the native selects. Search inputs have no form names and are not submitted.
The REST v1 search behavior remains accent-sensitive for backward compatibility.
This release adds search to the reusable component (including WPForms), not a new
search widget for WooCommerce Blocks or third-party form dropdowns.

## Validation evidence

`tests/WPForms/IntegrationTest.php` runs 22 checks using real Lite registration,
builder helpers, form save/render, independent groups, optional/required/malformed
input, relationship validation, canonical formatting and zero lookup HTTP.
Use a disposable database only. Run with `wp eval-file` on an installed plugin.

Additional isolated browser verification: add the field using the native builder,
save/reload (three groups retained), perform an actual non-AJAX form submission,
assert canonical 79/27163 properties and readable Hòa Hưng notification content
intercepted via `pre_wp_mail`, confirm optional empty groups, accent-insensitive
search, selected-value preservation, province reset and independent instances.
At 390px viewport, search/select targets were 44px high with 16px text. No Pro,
AJAX submit, popup, repeater, conversational or full gateway certification is implied.

Official reference reviewed alongside the installed WPForms source:
[WPForms field formatting lifecycle](https://wpforms.com/developers/wpforms_process_filter/).
