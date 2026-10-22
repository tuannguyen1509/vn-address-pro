# Changelog

## Unreleased — Phase 10 (development of 2.0.0)

- Add migration-only administrative normalization, conservative candidate matching and confidence-based analysis.
- Add bounded customer/order scan and stateless signed dry-run previews with zero database writes.
- Add explicit selected CRUD apply with source/dataset stale checks, strictly local validation and per-record verification.
- Preserve historical order snapshots by default; require separately previewed explicit display conversion.
- Add protected Migration settings tab and bounded minimal non-autoload journals for future rollback.
- Add matching, database-write, security, storage, partial-failure and real admin-browser tests and operating documentation.


## Unreleased — Phase 9 (development of 2.0.0)

- Add native Woo My Account billing/shipping ward selection with current-dataset validation and canonical customer persistence.
- Enhance Order Data billing/shipping fields on HPOS and legacy edit screens, including native customer load and address copy.
- Reuse shared ward loading, state mapping, validation and CRUD writers without adding a second metadata schema.
- Preserve historical/obsolete order snapshots on rendering and unchanged saves; keep customer and order edits independent.
- Reject tampered selections before native persistence and report errors through Woo notices.
- Add account/admin status, source-flow documentation, parity, offline, race and browser regression tests.

## Unreleased — Phase 8 (development of 2.0.0)

- Add automatic province-dependent billing/shipping ward blocks through official Woo checkout registration and IntegrationInterface.
- Add namespaced Store API extension schema, strict raw-state/ward validation and canonical address persistence before payment.
- Preserve Phase 7 order/customer metadata, historical formatting and native state identifiers across both checkout types.
- Persist canonical codes for accounts created during Block checkout, including payment retry.
- Extract a shared same-origin ward data client for vanilla and React consumers, retaining cancellation, schema checks and page-local cache.
- Handle independent addresses, same-address mode, saved-address hydration, country switches, request races and billing-only virtual carts.
- Add offline Block browser, direct Store API, express-presence and CRUD regression coverage.
- Declare Cart/Checkout Blocks compatibility when supported public APIs are available, after the Block and Classic acceptance suites passed.

## Unreleased — Phase 7 (development of 2.0.0)

- Add an optional WooCommerce 8.2+ module and dynamic Vietnam province states with a stable VN-{code} contract.
- Enhance Classic billing/shipping through the existing vanilla address component and a thin Woo event adapter.
- Validate canonical province/ward pairs before order creation, resolve names server-side and preserve international checkout.
- Persist order/customer codes through Woo CRUD and historical province-name snapshots for readable native addresses/emails.
- Verify HPOS and legacy stores, declare HPOS compatibility and explicitly exclude Cart/Checkout Blocks.
- Add Woo system-status diagnostics without integration settings or mandatory Woo dependency.
- Add offline checkout, account, shipping/tax, AJAX, tampering and datastore regression tests.

## Unreleased — Phase 6 (development of 2.0.0)

- Add reusable server-rendered province/dependent ward fields and optional address line.
- Add vn_address_fields shortcode, namespaced HTML renderer and prefixed PHP helper.
- Add conditional, scoped frontend assets and a dependency-free vanilla JavaScript instance API.
- Support independent instances, initial selections, reset/destroy, accessible status/error states and custom events.
- Protect dependent requests with cancellation, timeout, sequence guards and a page-local successful-response cache.
- Add strict reusable server-side province/ward relationship validation without changing REST v1 coercion.
- Add PHP rendering/escaping/asset checks and real browser offline/race/lifecycle regression coverage.
- Preserve all dataset/schema/sync artifacts, existing REST fixture and legacy signatures.

## Unreleased — Phase 5 (development of 2.0.0)

- Add native Settings → VN Address tabs and capability/action-nonce protected admin-post operations.
- Add dataset provider abstraction and one-request canonical Province Open API sync adapter.
- Add manual update checks, sync and opt-in daily/weekly/30-day scheduled updates (disabled by default).
- Reuse the transactional importer for remote publication, verifying staged fields and content identity before commit.
- Add an independent ownership-safe 10-minute sync lease, bounded status history and post-publish updated hook.
- Add explicit integrity audits, safe repair and offline bundled reinstall with downgrade confirmation.
- Clear relevant remote active/negative/failure caches while preserving last-known-good data.
- Preserve local-first/offline lookups, public REST v1 fixture and legacy signatures.
- Add sync, concurrency, rollback, cron, repair, authorization and admin HTTP regression coverage.

## Unreleased — Phase 4 (development of 2.0.0)

- Bundle a provenance-documented Province Open API v2 snapshot and SHA-256 manifest.
- Add repository abstraction, indexed persistent local province/ward reads and a remote fallback adapter.
- Preserve phone_code and original ward order for REST v1 compatibility.
- Add independent schema/dataset versions, offline activation and automatic Phase 3 upgrade without reactivation.
- Validate before import and atomically replace InnoDB rows/metadata, rolling back failed imports.
- Keep ordinary lookups local without transient dependency, per-request JSON parsing or HTTP calls.
- Add local fixture contract checks, offline/fallback/rollback/version/query-count tests and build tooling.
- Preserve release branding, existing remote reliability, REST fixture and legacy signatures.

## Unreleased — Phase 3 (development of 2.0.0)

- Validate provider and cached payloads before use; validate remote data before active/LKG writes.
- Require nonempty, unique province records and matching province/ward relationships without fixed dataset totals.
- Add seven-day last-known-good fallback while preserving REST v1 response schemas.
- Add ownership-safe, expiring refresh leases in the existing WordPress options table.
- Add short detail-404 negative caching, failure backoff and bounded Retry-After handling.
- Allow at most one retry for fast transient failures, with no sleeps or doubled long timeouts.
- Add validated TTL/attempt filters and diagnostics without logging payloads or personal information.
- Isolate runtime state in existing tests, update superseded internal assertions and add 60 reliability tests.
- Preserve the frozen REST fixture, branding, release version, requirements and legacy facade.

## Unreleased — Phase 2 (development of 2.0.0)

- Extracted REST v1 controller and explicit legacy error mapping.
- Extracted address service, normalizer, validator and unchanged search logic.
- Added provider/cache contracts, Province Open API adapter and JSON HTTP client.
- Kept raw cache keys/TTL, request settings, legacy coercions, ordering and health values.
- Converted the legacy class into a delegating facade, preserving public signatures/constants and registration hook identity.
- Added safe handling of structurally invalid payloads and non-scalar direct-call searches.
- Added 42 offline core checks and an optional live HTTP smoke runner.
- Kept release version, requirements, entrypoint, autoloader and database lifecycle unchanged.

## 2.0.0

- Changed plugin display name to VN Address Pro and author metadata to Tunlamweb.
- Kept the existing `vn-address-api.php` entrypoint.
- Added a namespaced, single-boot foundation with an internal PSR-4 loader.
- Added PHP 8.1 / WordPress 6.3 requirement gates and translated admin notices.
- Derived release and requirement constants from the plugin header.
- Added `vnap_version`, activation/deactivation hooks and an upgrade checkpoint.
- Added textdomain loading on `init` and the `vnap_loaded` action.
- Preserved the legacy public class, REST implementation, routes, errors, health payload, provider and cache keys.
- Documented REST v1 and added baseline contract fixtures and foundation checks.
