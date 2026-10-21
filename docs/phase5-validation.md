# Phase 5 validation

Validated 2026-10-21 using disposable per-site databases in Docker, without WooCommerce/forms. Plugin remains 2.0.0, database schema remains 1, and the shipped bundle/manifest/generated constants are unchanged. Automatic updates default Disabled. No Phase 6 work, push, tag or release is included.

## Runtime matrix

| Runtime | WordPress | Database | Result |
| --- | --- | --- | --- |
| PHP 8.1.34 CLI | 6.3.7 | MariaDB 11.8, InnoDB | Full deterministic suites passed |
| PHP 8.3 CLI / Apache | 7.1.3 | MariaDB 11.8, InnoDB | Full suites and admin HTTP passed |
| PHP 7.4 | Standalone PHP requirement gate | None | Unsupported core not loaded, notice/activation rejection passed |
| PHP 8.3 | Unsupported WordPress 6.2 stub | None | Requirements rejection passed |

The minimum WordPress installation uses a separate non-default table prefix. Its core version was explicitly pinned/checked at 6.3.7 before the final matrix run, with test-site automatic core updates disabled. Full network multisite provisioning, MySQL variants and external persistent object-cache combinations were not tested.

## Existing tests

On **each** supported runtime: 18/18 REST baseline comparisons, FoundationTest, 42 CoreTest checks, 60 ReliabilityTest checks, 40 LocalRepositoryTest checks and the four-route offline local smoke passed. No existing test or frozen expected response was weakened or changed for Phase 5. Test documentation alone was extended.

The frozen `tests/Contract/fixtures/rest-v1.json` is unchanged, SHA-256 `436c9637f0444bacc719beab0057bdcbe8a5dc49af0ce4ea8a979b792bce5e62`. Namespace, routes, parameters, fields/types, health, search/sort, no-fallback errors and legacy public signatures remain covered. No v1 write route was added.

## New deterministic tests

DatasetSyncTest passes **65 checks on each runtime**, with HTTP mocked or blocked:

- Newer/same/older/version-content-conflict update checks and synchronization; check-only never publishes, unchanged sync performs zero data-row rewrites.
- Invalid schema/parent/collection/checksum, timeout, invalid JSON and provider exceptions preserve installed state.
- Failed ward INSERT, staged field corruption and failed COMMIT preserve prior rows/metadata. Pre-commit reconstruction verifies canonical field values, not only counts.
- A second database connection dispatches real REST during the writer's province/ward transaction and sees the old complete response.
- Exclusive sync acquisition, double-click rejection, success/failure release, expired lease recovery, ownership-safe release, and stale-worker publication rejection.
- Healthy/missing tables/empty wards/empty provinces/orphans/missing metadata integrity checks.
- Healthy repair no-op, same-version remote repair, remote-down bundled repair, invalid/unavailable bundle preservation, failed repair preservation and explicit-confirmation-only downgrade.
- One bounded default-provider full request, inferred nested parent context, stable unchanged observation version and no repeated cron rewrites.
- Daily/weekly/30-day schedules, frequency changes, repeated reconciliation without duplicates, disable/deactivate/reactivate and controlled cron failure.
- Anonymous/lower-capability denial despite valid nonce; admin invalid/action-mismatched nonce rejection; four tabs render without HTTP/scripts.
- Clear remote state preserves LKG; offline REST after failed sync uses zero remote calls.

## Actual admin and REST transport

AdminHttpSmokeTest passed **30 HTTP checks** on PHP 8.3 / WordPress 7.1.3: authenticated login, Plugins branding, Settings page, four tabs, seven forms/nonces/POST redirects/feedback, invalid-nonce 403 and anonymous denial. The test MU fixture returns deterministic complete provider data and denies unrelated HTTP. It removes only WordPress core admin update-check callbacks in the test environment so an intentional outbound block does not produce unrelated WordPress.org warnings. It is never loaded by production code and was removed after testing.

Actual HTTP GET requests to provinces, wards?province_code=79, province/79 and health all returned 200 with a separate server-side outbound blocker. The WordPress HTTP attempt counter remained **0**. No manual sync failure or enabled scheduling changed frontend lookup behavior. The blocker was removed afterwards.

Optional live upstream checks passed separately: complete full dataset download/validation (34 provinces, 3321 wards; no publication), Phase 3 provider list/wards/detail, and all four actual site REST URLs. These network checks are excluded from deterministic assertions about source availability.

## Syntax, debug and repository checks

All **58 PHP files** passed PHP 8.3 syntax checks. Production modules/admin rendering were exercised on PHP 8.1. Node smoke scripts use no packages beyond Node built-ins.

Final validation used WP_DEBUG and WP_DEBUG_LOG on both sites. Fresh final-run logs contained no PHP Warning/Notice/Deprecated/Fatal/Parse, uncaught exception/TypeError or WordPress database errors. Sanitized application diagnostics for intentional outage tests are expected. During development, a test-harness missing global declaration was corrected; unrelated WordPress.org warnings from the first offline-admin fixture run were resolved by isolating core update hooks in that test fixture. Neither required changing production error handling or hiding a public regression.

Bundle SHA-256 remains `fd620c46def82960832d7c1d3587f4b17aeb197dcbe74a753adbf897ab38e32f`; 849759 bytes, schema 1, version 20261021034454. Git whitespace validation and changed-file secret/scope review precede the local commit. Prior Phase 2/3/4 commits remain intact and are not amended.

## Known limits

The default source lacks an authenticated snapshot manifest/version. Update timestamps are local observations; unchanged content is identified with a normalized content checksum. A valid HTTP-200 source omitting an entire province cannot be proven incomplete without authoritative source metadata. No fixed province/ward totals are assumed. Clock skew can cause an older-version rejection. Byte checksums detect package corruption; they do not authenticate an upstream publisher.

Sync is synchronous: one request, at most 30 seconds network timeout, then local validation/import. WP-Cron needs traffic or an external runner; monthly means 30 days. Audit/repair are explicit tools, not per-request full integrity checks. LKG remains available for emergency fallback and may represent older remote data. No full historical dataset rollback archive, automatic corruption repair, custom queue, destructive uninstall or integration was added.

Recommended next phase: Reusable Frontend Address Component; implementation stops at Phase 5.
