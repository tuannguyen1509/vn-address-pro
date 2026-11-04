# Shipping configuration conversion

Settings → VN Address → Shipping Configuration is available to users with both
`manage_options` and `manage_woocommerce`, with supported WooCommerce active.
It is optional and never runs on activation, upgrade, checkout or dataset sync.

## Workflow

1. Back up the database and review current geographic shipping coverage.
2. Scan configuration (dry run). No preview is stored and no configuration is changed.
3. Review each current/suggested identifier, select individual changes, and confirm Apply.
4. Verify shipping fees, overlapping zone priority and store location on the merchant site.

The tool suggests a canonical Woo state identifier (`VN:VN-{province_code}`) for
legacy uppercase compact names (`VN:HOCHIMINH`, for example) only when removing
accents, spaces and the administrative prefix yields exactly one matching name
in the healthy local dataset. This is a **name-based suggestion**, not an
authenticated historical crosswalk or proof of equivalent boundaries. Unknown,
ambiguous, numeric, foreign and already-canonical identifiers are not converted.
There is no merchant/domain/theme-specific code, shipping price or hard-coded
province-count mapping. The old checkout plugin does not need to be installed.

## Scope and preservation

- Selected Vietnamese state entries in existing Woo shipping zones.
- The province part of `woocommerce_default_country` (store country/state).
- No changes to rates, method settings, zone priority, postcodes, foreign locations,
  tax rules, customers or orders. Unselected/unresolved entries remain intact.
- Overlapping zones remain overlapping: this tool does not combine zones or move
  local pickup methods between them. Review those business rules separately.
- No shipping-carrier API mapping or conversion of customer/order addresses.

For bounded synchronous previews, a scan refuses sites with more than 99
Vietnamese state-location rows, zones with over 1,000 location rows, or previews
over the existing signed-plan size limit. It does not silently truncate them.
Larger installations need a separately designed pagination/batch workflow.

## Safety and concurrency

Preview signatures expire after 20 minutes and are bound to user, site, dataset
identity and a separate configuration-purpose marker. Customer/order migration
tokens cannot be used here, and configuration tokens cannot be used for address
migration. POSTs require command-specific nonces and both management capabilities.

Apply acquires the shared dataset/migration lease, verifies local dataset integrity,
then locks and compares selected location rows and each selected zone's full location
context. Changed store values, zone name/order/location coverage, dataset or invalid
selections require a fresh scan. InnoDB is required for options and shipping tables.
All selected changes and the before/after journal commit in one transaction. A source
conflict or database/journal failure rolls back the complete batch. Updates use the
existing WordPress table prefix, and refresh option and Woo shipping caches after
the transaction. This narrow atomic SQL operation intentionally avoids invoking
per-row Woo save observers inside an uncommitted batch.

The latest 20 batches are retained in non-autoloaded `vnap_shipping_config_history`.
Each records the actor, timestamp, original identifiers and proposed results; no
customer PII is included. Replaying a retained successful preview returns the prior
batch. Replays after retention expires fail source comparison for already-converted
rows. Automatic rollback is not provided. Other writers that bypass these locks may
change configuration after commit; merchants must verify actual shipping behavior.

## Validation (2026-11-04)

A fresh, separate Docker WordPress/database installation was used, not merchant
shipping configuration: PHP 8.3.36, WordPress 7.1.4, WooCommerce 11.2.1, default
theme, no old checkout plugin or merchant plugins. No port was published for the
test containers. Synthetic shipping zones and store settings were restored by the
test runner. This is current-runtime coverage, not a new minimum-version or full
multisite/external-object-cache certification.

Run on a disposable installation:

```sh
wp eval-file wp-content/plugins/vn-address-pro/tests/Migration/ShippingConfigurationTest.php
```

33 checks: matching/ambiguity, scan writes=0, offline data, selected/unresolved input,
nonce/capability, signature/expiry/user/site isolation, cross-purpose tokens, stale
dataset/source/geographic coverage, full rollback on late journal failure, canonical
zone matching, option-cache refresh, unchanged fees/priority/postcodes/foreign values,
before/after journal and replay behavior.

Regression: existing 51 matching, 83 address-migration integration, 18 REST contract,
42 core, 60 reliability checks and foundation suite passed on this isolated runtime.
