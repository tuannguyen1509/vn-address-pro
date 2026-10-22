# Reusable form integration foundation

VN Address Pro 2.0.0 (Unreleased) keeps forms optional. Shared contracts/registry contain no vendor dependency, global input reads, HTTP implementation or submission database. Contact Form 7 and Fluent Forms are implemented in separate optional adapter modules. Elementor, WPForms and Gravity Forms are not implemented. See [Fluent Forms](fluent-forms.md) for native fields, version requirements and entry/notification behavior.

## Registry and availability

Plugin's composition root registers the built-in adapter, fires `vnap_form_adapters` with the `FormIntegrationRegistry`, then boots available adapters at `plugins_loaded` priority 20. `register(FormAdapterInterface)` rejects duplicate/invalid IDs. `boot_available()` boots each available adapter once. Availability is checked through actual API functions/classes/version, not folder names. Interface: `get_id(): string`, `is_available(): bool`, `boot(): void`. Adapter methods must be lightweight and avoid early translated labels or side effects when unavailable.

Future adapters can register with `add_action('vnap_form_adapters', function ($registry) { $registry->register(new Adapter()); });`. Register the listener while plugins load, before priority 20. No filesystem scanning or framework is required. System Status lists adapter availability/boot state. No global form settings or dependency is added.

Phase 12 optionally reads a public `status_details(): array` method for additive `detected` (boolean) and `version` (string) metadata. Existing adapters need no new method; required interface signatures and existing available/booted keys stay unchanged.

## Shared field and validation contract

`AddressFieldConfig` describes name, kind (`province`, `ward`, `address`), required, explicit group identifier, default, label and placeholder without form-tag syntax. `AddressSubmission` carries untrusted province/ward codes and delegates `resolve(AddressService)` directly to existing `validate_selection`. Result remains the core `['province'=>row,'ward'=>row]` or WP_Error; there is no second generic validator/result abstraction.

Adapters translate builder-native configuration/input/errors. They must reject arrays and malformed identifiers before validation, enforce optional/required semantics, scope groups deterministically, and resolve display names from authoritative data. Codes are the submission contract; names are display values. Do not parse street addresses or create a new normalization/matching/provider layer. Province-only fields may use core `get_provinces`; complete pairs always use `validate_selection`.

## Shared client and presentation

AddressDataClient owns same-origin REST, schema checks, timeout, abort and page-lifetime fulfilled-response caching. Shared `vn-address.js` AddressField owns dependent loading, invalidation, sequence/race guards, retry, reset and existing `vnap:*` events. `VNAddressPro.bindFields(container, {province,ward,provinceName,wardName})` provides an additive adapter entrypoint for explicitly linked native selects within the same form. Name mirrors must be INPUT elements and may remain detached/internal. Existing shortcode init/initAll/getInstance methods and markup remain unchanged.

Do not force every builder to use shortcode markup. The CF7 adapter uses native CF7 wrappers and native validation association; its thin JS discovers explicit linked fields and delegates to bindFields. No duplicate fetch/cache/race code, polling, document MutationObserver, jQuery, localStorage or form-storage layer is added.

Assets enqueue when a VNAP custom form-tag actually renders on the frontend. The shared scripts and stylesheet deduplicate through WordPress handles; adapter depends on shared AddressField. Ordinary CF7 forms and wp-admin do not enqueue adapter assets. When injecting new rendered forms, host code must provide unique native unit tags/control IDs, load required registered assets, initialize that form plugin using its supported API and call the scoped adapter initializer. Destroy old instances when replacing an active group. No arbitrary builder/popup lifecycle is claimed automatically supported.

Dataset data remains local-first through existing AddressService/REST v1. No CF7-specific persistent cache requires rebuild after sync; each new request uses current repository data. Existing fallback remains possible when local data is unavailable; adapters must show controlled errors rather than accept unresolved selections.

See [CF7 contract](contact-form-7.md) and [validation report](phase11-validation.md). Public REST and Woo/migration contracts are unchanged.
