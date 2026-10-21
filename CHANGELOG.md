# Changelog

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
