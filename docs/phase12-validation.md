# Phase 12 validation — Fluent Forms

Date: 2026-10-22. VN Address Pro **2.0.0 — Unreleased**. Phase 12 only; no Woo, migration, provider, dataset or REST implementation changes.

## Capability gate and tested matrix

Official developer documentation and distributed Fluent Forms source were reviewed before production edits: BaseFieldManager catalog/editor customization, render wrappers, slash validation/response filters, entry response persistence, native `.value` SmartCodes, conditional essential parsing, `fluentform_init`, success and native reset. Supported APIs cover this adapter without builder DOM manipulation or a vendor patch. Street uses native Simple Text.

| Runtime | Minimum | Current |
| --- | --- | --- |
| WordPress | 6.4.7 | 7.1.3 |
| PHP | 8.1.34 | 8.3.35 |
| Fluent Forms Free | 6.2.0 | 6.2.16 |
| Contact Form 7 | 5.8.1 | 6.2.3 |
| WooCommerce | 8.2.0 | 11.2.1 |

A 5.0.0 compatibility probe passed basic fields but exposed the older validation pipeline: custom validators can run for conditionally inactive fields. The supported minimum is 6.2.0, with a capability check for native `getEssentialInputs`; native Fluent metadata requires WordPress 6.4+. Core VN Address Pro still supports WordPress 6.3. The unsupported 5.0.0 adapter test passed without fatal, hooks or assets. No claim is made that every intermediate release, Pro or conversational rendering has been certified.

## New tests

| Runner | Result |
| --- | --- |
| FluentForms/IntegrationTest.php | **28 PASS**, both supported runtimes |
| FluentForms/TransferTest.php | Native whole-form duplication, native JSON importer and imported field rendering PASS, both runtimes |
| FluentForms/BrowserTest.mjs | **24 PASS**, both runtimes |
| FluentForms/BuilderTest.mjs | **6 PASS**, both runtimes |
| FluentForms/StatusTest.mjs | CF7 + Fluent Active, detected version, admin asset isolation/exception checks PASS, both runtimes |
| FluentForms/EntryTest.php | Actual stored code strings, readable native entry view, captured native notification name/code values, provider HTTP=0 PASS, both runtimes |
| FluentForms/AvailabilityTest.php | Actual Fluent inactive and actual unsupported 5.0.0 probe PASS; core/CF7 unaffected |

Browser tests use real Fluent frontend and native AJAX endpoints, not a simulated form. They cover initial Province options/no Ward preload, Ward dependency, independent forms/groups, conditional show/hide and hidden required submission, CF7/shortcode coexistence, unrelated validation preserving selection, native successful submission/reset, manual reset, abort-insensitive A-after-B response ordering, direct unknown/array/mismatch submissions, shared asset deduplication and no browser external requests/exceptions. The test follows native blur UX before resubmission; older Fluent AJAX response envelopes are parsed separately from the VN Address REST contract.

Builder tests add native fields, set Province Field through the official custom setting, save and reload, verify retained reference and confirm no frontend adapter asset in admin. No production builder JS is added. Transfer smoke tests exercise native service duplication/import and render the imported form; the test's synthetic JSON uses the native export shape. A real file-download export/import UI round trip was not automated.

Notifications are composed by Fluent's real submission pipeline and intercepted by `pre_wp_mail`; no SMTP is required or delivered. Native `{inputs.FIELD}` yields names, `{inputs.FIELD.value}` retains raw code. Browser-supplied labels are never used for canonical resolution. Standard CSV follows Fluent's formatted inputs; raw additional SmartCode CSV columns/file download were source-reviewed, not claimed as an automated CSV test.

## Existing tests and compatibility

| Existing suite | Result |
| --- | --- |
| Foundation, requirements and core lifecycle | PASS; activate/deactivate/reactivate PASS |
| REST v1 | **18/18 PASS**, both runtimes; final current-runtime repeat PASS |
| Unit core / reliability | **42 / 60 PASS** |
| Local repository / offline smoke | PASS; four REST routes, zero outbound lookup HTTP |
| Dataset sync/admin PHP | **65 PASS** |
| Frontend render / browser | **32 / 41 PASS**, both runtimes |
| CF7 PHP / unavailable data | **32 PASS** and controlled unavailable handling, both runtimes |
| CF7 browser / direct HTTP / native generator | **23 / 14 / 9 PASS**, both runtimes |
| Woo Classic PHP / browser / HTTP-account | **47 / 20 / 20 PASS**, both runtimes |
| Woo Blocks PHP / browser / Store API | **23 / 29 / 23 PASS**, both runtimes |
| Woo account/order admin PHP / assets / parity | **43 / 4 / PASS**, both runtimes |
| Woo account/order admin browser / races | **46 PASS** and reversed-response account/admin races PASS, both runtimes |
| Migration matching / integration | **51 / 83 PASS**; dry-run, apply and audit protected |
| Migration browser | **23 PASS** on current runtime |

Woo Classic/Blocks/account/admin/migration PHP suites were run with **HPOS=yes and HPOS=no** on both versions, using Woo's official synchronization before changing storage. Full browser checkout/account/admin runs used HPOS on both runtimes; a complete browser matrix with legacy order storage was not repeated. CF7 was also tested while Fluent was absent. Core/shortcode/REST were tested while Woo and CF7 were absent and Fluent remained optional.

No existing executable Phase 1–11 test was edited. `tests/README.md` only adds instructions. REST baseline fixture changed: **NO**. SHA-256:

```text
436c9637f0444bacc719beab0057bdcbe8a5dc49af0ce4ea8a979b792bce5e62
```

## Offline, debug and syntax

Outbound WordPress HTTP and mail are blocked in the disposable browser fixtures. Address-provider calls are counted separately from ambient Woo/WordPress update, telemetry and loopback requests. The final measured runs recorded **0 address-provider calls**, current runtime 1 other blocked HTTP attempt, minimum runtime 6 other blocked attempts. No request was allowed through. Browser external requests were zero. This distinction avoids attributing Woo background traffic to address lookup.

Final controlled Fluent browser/submission/entry validation under `WP_DEBUG=true`, `WP_DEBUG_LOG=true`, `WP_DEBUG_DISPLAY=false` produced **0-byte debug logs on both runtimes**. PHP integration/transfer runners convert warnings/notices to exceptions. Initial harness experiments (early translation, builder selectors, older Fluent APIs, native blur/response differences and an incorrect virtual-product fixture ID) were corrected and passing runs repeated. Vendor source was not patched. Older Woo's known empty-cart warnings and installation-time WordPress connectivity checks are separate from the clean controlled Fluent runs; they are not represented as new VN Address warnings.

All **131 PHP files** passed syntax checks on PHP 8.1 and 8.3. New production JavaScript and all three new browser runners passed Node syntax checks. No new database schema, vendor package, Composer/npm dependency, global JS polling, browser persistent dataset cache, all-wards payload or public write route was introduced.

## Remaining limits

- Only the tested native Free/classic Fluent renderer is certified; arbitrary popup lifecycle and Pro/conversational/repeater fields are outside this phase.
- Renaming an individual Province requires updating its explicit Ward references. Whole-form duplication/import preserves names and references naturally.
- Name display resolves the current dataset; removed historical codes remain visible as codes. No historical snapshot or new migration logic is added.
- CSV uses native formatted columns; add raw `.value` SmartCode columns through Fluent's supported export UI where available. No custom CSV engine was added.
- An already open page retains its page-lifetime Ward cache until reload; new requests read the current shared dataset.

Only synthetic fixtures were used. All official plugin downloads, temporary WordPress/database, captured mail, credentials, logs and browser dependencies stay outside the repository. No push, tag, release or next-phase implementation is authorized by this validation.
