# Changelog

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
