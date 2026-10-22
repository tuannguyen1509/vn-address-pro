# Fluent Forms address adapter

VN Address Pro 2.0.0 — Unreleased. Optional adapter ID: `fluent-forms`.

## Supported versions and APIs

Requires Fluent Forms **6.2.0+**, WordPress **6.4+**, and the core plugin's PHP **8.1+**. Minimum tested: Fluent Forms 6.2.0 / WordPress 6.4.7 / PHP 8.1. Latest tested: 6.2.16 / WordPress 7.1.3 / PHP 8.3. Fluent Forms Free was tested; Pro/conversational forms are not certified.

The minimum is based on native essential-input parsing (`Services\Parser\Form::getEssentialInputs`): inactive fields and containers must be excluded before custom validation. A 5.0.0 probe exposed the older validation path calling custom validators for inactive fields. That version is deliberately unsupported; the adapter does not reimplement Fluent's condition engine. Detection also checks native field-manager/parser APIs. No plugin-folder detection or vendor patch is used.

Official references: [BaseFieldManager](https://developers.fluentforms.com/api/classes/base-field-manager/), [validation filter](https://fluentforms.com/docs/fluentform_validate_input_item_input_text/), [response formatter](https://fluentforms.com/docs/fluentform_response_render_element/), [submission lifecycle](https://developers.fluentforms.com/submission-lifecycle/). Distributed official 6.2.0 and 6.2.16 sources were reviewed for builder settings, AJAX submission, conditions, SmartCodes and entry/export formatting before implementation.

## Add and link fields

1. In the Fluent Forms editor, expand **Advanced Fields** and add **VN Province**.
2. Give it a unique **Name Attribute**, for example `billing_province`.
3. Add **VN Ward**. Set its Name Attribute to `billing_ward`.
4. In its native **Input Customization**, set **Province Field** to exactly `billing_province`.
5. Configure native label, admin label, placeholder, required and container class controls, then save.

The reference is a deterministic field name, not a DOM selector or adjacent-field inference. Native text customization is used rather than an unsupported field-picker component. When renaming a Province, update its Ward references. Missing references, duplicate names or a reference to another field type disable the control with feedback and reject active submissions safely. Names follow `[a-zA-Z][a-zA-Z0-9_]*`.

Use Fluent's **Simple Text** for the optional street/address line (label: “Số nhà, tên đường”). There is no redundant custom VN Address field. Native text-field validation/storage remains Fluent's responsibility.

## Required and optional groups

Required Province/Ward cannot be empty. When both optional, both empty is valid. A selected Province with an empty linked active Ward is invalid; a Ward without its Province is invalid. Configure required flags through native controls. Native inline validation presents errors. An active Ward cannot depend on an inactive Province; apply coherent conditions to the pair or their container.

Native conditional logic is supported through BaseFieldManager and native rendering wrappers. Server validation runs only for Fluent's active essential fields, including conditional containers. Unrelated validation failure preserves selections; successful native reset clears Province/Ward and the component's name mirrors. No extra submission lifecycle or custom REST write endpoint is added.

## Multiple groups and forms

For `home_province`/`home_ward`, `office_province`/`office_ward`, or `shipping_province`/`shipping_ward`, give each Ward its explicit Province reference. Native form boundaries isolate repeated names across separate forms. CF7, Fluent Forms and `[vn_address_fields]` can share a page; WordPress deduplicates the shared assets.

## Entry values, notifications and canonical codes

Posted and stored field values are **code strings**, never browser labels. Fluent stores its normal submission response; this adapter owns no submission table. Server validation uses AddressService and rejects arrays, malformed/unknown codes, mismatches and unavailable data. Browser-supplied name fields are not authoritative.

The official `fluentform/response_render_vnap_province` / `_vnap_ward` filters resolve names for Entries UI and normal notification values. Standard Fluent SmartCodes work:

```text
{inputs.billing_province}        readable Province name
{inputs.billing_ward}            readable Ward name
{inputs.billing_province.value}  raw stored Province code
{inputs.billing_ward.value}      raw stored Ward code
```

These are Fluent's native syntax, not a new parser. Native entry JSON retains raw codes. Historical codes no longer resolvable in the current dataset display their code rather than disappearing; no historical name snapshot is created.

Native CSV exports use Fluent's formatted `user_inputs`, so ordinary address columns contain readable names. To export raw codes as well, use native additional SmartCode export columns with the `.value` forms where the installed Fluent export UI offers them, or consume the raw entry response through Fluent's API. The adapter does not add or rewrite CSV columns. Native form JSON carries the field type, name and Province reference through export/import and whole-form duplication. Renaming individual duplicated fields requires updating their references; no automatic identifier-remapping promise is made.

## Client lifecycle, assets and offline operation

The thin adapter uses `VNAddressPro.bindFields` and **AddressDataClient**, sharing page-lifetime cache, cancellation, sequence protection and native reset handling. Only Provinces render initially; Ward lists load for the selected Province. No all-wards payload, browser storage, polling or global observer is added. Assets enqueue only when a VNAP Fluent field renders; builder integration is PHP registration and uses Fluent's own editor components/styles.

DOMContentLoaded initializes static forms; Fluent's native `fluentform_init` event initializes dynamically initialized forms. A host inserting forms outside that lifecycle must load required assets, initialize Fluent through its own API and call `VNAddressPro.initFluentForms(root)`. Destroy old shared instances before replacing an active group. Arbitrary popup builders and conversational forms are not automatically supported.

With a healthy local dataset, rendering, selected-Province Ward loading, validation, entry display and captured notification composition work with outgoing HTTP blocked and **zero provider calls**. Changes from dataset sync are read through the existing service; no Fluent-specific dataset copy exists. An already open page retains its page-lifetime client cache until reload.

## Status

VN Address → System Status lists the adapter as Active, Unsupported or Not installed, plus detected version. Missing/unsupported Fluent never becomes a core dependency and enqueues no Fluent adapter assets. CF7 availability/status remains independent.
