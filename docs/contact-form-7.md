# Contact Form 7 address adapter

Optional adapter ID `contact-form-7`, automatically enabled only when compatible CF7 APIs exist. Minimum integration API/test baseline: **CF7 5.8.1** (tested WordPress 6.3.7/PHP 8.1). Latest tested: **CF7 6.2.3** (tested WordPress 7.1.3/PHP 8.3); that CF7 release itself requires WordPress 7.1/PHP 8.3. VN Address Pro core minimum remains WordPress 6.3/PHP 8.1. This is an API/runtime test matrix, not certification of untested versions or third-party CF7 add-ons.

## API review and design decision

Before implementation, both official plugin packages were inspected: modules/select.php, text.php, includes/form-tag.php, form-tags-manager.php, submission.php, validation.php, mail.php, rest-api.php, admin/includes/tag-generator.php and browser bundles. Registration uses wpcf7_init + wpcf7_add_form_tag(name-attr). Types ending `*` follow CF7 required convention. Values deliberately do **not** declare selectable-values: CF7 otherwise converts values to arrays/pipes, hiding scalar-vs-array input distinctions. Native wpcf7_validate_{type} + WPCF7_Validation::invalidate provide server validation. Current submission's get_posted_data preserves canonical codes and is never flattened by this adapter.

Individual tags were chosen over a compound tag so every submitted field actually exists in the CF7 template. No fake hidden names, $_POST modifications, synthesized companion submission fields, mail-header construction or private CF7 methods are used. Names are resolved in the public mail-tag replacement filter. Official references: [custom tags](https://contactform7.com/2015/01/10/adding-a-custom-form-tag/), [custom validation](https://contactform7.com/2015/03/28/custom-validation/), [DOM events](https://contactform7.com/dom-events/), [releases](https://contactform7.com/category/releases/).

## Form-tag contract

| Type | Native value | Required variant |
| --- | --- | --- |
| `vnap_province` | Positive canonical province code string | `vnap_province*` |
| `vnap_ward` | Positive canonical ward code string | `vnap_ward*` |
| `vnap_address` | CF7-sanitized street text, maximum 400 characters | `vnap_address*` |

Each ward requires **one** `province:FIELD_NAME` option pointing to a unique vnap_province tag in the same CF7 form. No proximity/first-select inference. Every field name must be unique across all native/ VN tags within that form. Reusing a saved form twice on one page is supported; separate rendered instances get independent controls/IDs. Explicit `id:` options remain CF7's native user responsibility: use unique IDs or omit them.

Province tags may stand alone. With a ward present: optional pair both empty is valid; selected province + empty ward is invalid; ward without province is invalid. Required province/ward cannot be empty. Unknown codes, arrays, malformed strings and cross-province wards are invalid. Missing/duplicate reference and duplicate field names render a configuration notice/disabled dependent selector and fail server validation. No guessing/manual override occurs.

## Example form

```text
<label>Name [text* contact_name]</label>
<label>Email [email* contact_email]</label>
<label>Province [vnap_province* home]</label>
<label>Ward [vnap_ward* home_ward province:home]</label>
<label>Street [vnap_address street placeholder "House number and street"]</label>
[submit "Send"]
```

Optional independent office group:

```text
<label>Office province [vnap_province office]</label>
<label>Office ward [vnap_ward office_ward province:office]</label>
<label>Office street [vnap_address office_street]</label>
```

Placeholders use native `placeholder "Choose province"` / `placeholder "Choose ward"`. Static defaults use CF7 values/default indices: `[vnap_province home default:1 "79"]` and `[vnap_ward home_ward province:home default:1 "CURRENT_WARD_CODE"]`. Replace the symbolic ward value with an actual current code belonging to 79. No historical/example code is assumed current. Only a validated current ward is restored. Advanced dynamic defaults are not certified in this phase.

Native generator buttons **VN Province / VN Ward / VN Address line** appear only in the CF7 editor through WPCF7_TagGenerator. Latest uses native v2 generator helpers/options; 5.8.1 uses its native v1 panel (no deprecated v1 registration on newer CF7). Ward generator has an explicit province-name control. Defaults and complex option editing can still be entered manually. No custom admin assets or global editor changes.

## Mail and downstream contract

```text
Province: [home]
Ward: [home_ward]
Street: [street]
Province code: [_vnap_code_home]
Ward code: [_vnap_code_home_ward]
```

Ordinary province/ward mail-tags output authoritative **readable names**. Special `_vnap_code_FIELD` tags output canonical codes for a declared province/ward field. Reserve that prefix for the adapter; do not create ordinary fields with those names. Both Mail and Mail (2), plain/HTML bodies, use the same server resolution; HTML names are escaped. Arbitrary browser fields such as home_name/home_ward_name are ignored as authority. Malformed/unresolved values never produce guessed names. No SMTP or submission persistence is provided by VN Address Pro.

`WPCF7_Submission::get_posted_data('home')` / home_ward remain **code strings** for Flamingo/custom consumers. The adapter does not add flattened name fields, store submissions or depend on Flamingo. Consumers needing names must use AddressService against validated codes. Flamingo/reCAPTCHA/spam/acceptance/mail failure hooks are not changed; a full external-service/add-on matrix is deferred.

## Lifecycle, accessibility and offline behavior

Native selects/input, labels supplied by form author, unique generated IDs, CF7 control/wrap/required classes, aria-required/invalid/describedby and native error association are retained. Dependent fields show live load/error feedback and retry. Initial HTML includes provinces plus only a ward placeholder/default code, never all ward options. Same-page fulfilled ward cache is reused; no localStorage. Abort plus shared sequence guards prevent old responses replacing newer selections even if cancellation is ignored.

DOMContentLoaded initializes rendered groups. Native form reset uses the shared component reset; CF7 successful reset returns defaults/clears stale ward. Invalid/spam/mail-failure responses do not clear a valid selection. CF7's wpcf7reset event permits scoped rediscovery without polling. For host-inserted forms call `VNAddressPro.initContactForm7(form)` after native CF7 initialization; unsupported popup builders do not receive automatic document observation. The method is idempotent. Existing `vnap:province-changed`, `vnap:ward-changed` and load/error/ready events bubble from the ward wrapper (data-name identifies the group). Street remains an independent CF7 text field.

Without JS the ward stays disabled; required/incomplete dependent addresses fail gracefully through native server validation. Province-only/empty optional groups remain valid. Healthy local data supports render → lookup → submission with zero upstream calls. When repository resolution fails, selects/validation report controlled errors and mail is blocked. Core fallback may apply when local data is unavailable; no adapter-specific upstream implementation exists.

No form UI, Woo address field, migration record, provider, dataset schema or REST contract is modified. See [validation](phase11-validation.md) for actual tested behavior and limits.
