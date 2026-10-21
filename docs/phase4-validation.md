# Phase 4 validation

Validated on 2026-10-21 in disposable Docker WordPress installations, without WooCommerce or form plugins. No production database was used. Plugin release remains 2.0.0; database schema 1 and dataset snapshot 20261021034454 are separate versions.

## Matrix and lifecycle

| Runtime | WordPress | Database | Result |
| --- | --- | --- | --- |
| PHP 8.1 | 6.3.7 | MariaDB 11.8 / InnoDB | All suites passed |
| PHP 8.3 | 7.1.3 | MariaDB 11.8 / InnoDB | All suites passed |
| PHP 7.4 | Standalone requirements gate | None | Core not loaded; actionable notice |
| PHP 8.3 | Unsupported WP 6.2 stub | None | Core not loaded; actionable notice |

Both WordPress sites passed actual WP-CLI deactivate/activate and the foundation suite: metadata, single boot, public legacy signatures, version checkpoint, cache and cron preservation. The existing Phase 3 active installation imported on init without reactivation, even though the plugin version stayed 2.0.0. The minimum-version site activated fresh with all outgoing HTTP blocked; the HTTP attempt counter stayed zero. Its non-default table prefix was respected.

## Existing suites and public contract

On each supported matrix entry: **18 frozen REST comparisons, 42 core checks and 60 reliability checks passed**, plus FoundationTest. Old suites use LocalState to isolate the remote provider path; CoreTest dependency doubles implement the repository interface. Public expectations were not weakened.

`tests/Contract/fixtures/rest-v1.json` is unchanged. SHA-256:
`436c9637f0444bacc719beab0057bdcbe8a5dc49af0ce4ea8a979b792bce5e62`.

The four v1 routes retain parameters, public fields/types, health, search, sorting and legacy error mapping when remote fallback also fails. Eleven additional exact baseline response comparisons run through the local repository.

## New local checks

LocalRepositoryTest passed **40 checks on each runtime**: complete-file validation, checksum/count mismatch, malformed/empty/duplicate/orphan records, wrong schema/version, field limits, indexed reads, phone_code, ward position, local unknown codes without HTTP, idempotence, no automatic downgrade, newer snapshot replacement, rollback, independent version markers and upgrade without activation. Its simulated failed ward INSERT occurs after province writes and confirms old rows plus metadata survive.

Province listing is one data SELECT without wards; province detail uses one ordered LEFT JOIN, not N+1. Code lookup uses primary keys and parent order uses the composite index. Warm current-version installer checks issue zero database queries with the options cache populated and do not read JSON/run dbDelta. Missing table tests select the remote path; combined local/remote failure retains the frozen 502 response.

LocalRepositorySmokeTest passes all four real REST-dispatch routes with remote active/LKG/marker state cleared and all outgoing HTTP denied. Actual web requests to provinces, wards?province_code=79, province/79 and health also returned HTTP 200 with a server-side HTTP blocker and a zero outbound counter. The blocker was removed before remote mock tests. Optional direct RemoteProviderSmokeTest and live REST HTTP smoke passed separately.

## Bundle, syntax and debug

The shipped file is 849759 bytes: 34 provinces, 3321 wards (manifest observations, not fixed totals). Its SHA-256 is `fd620c46def82960832d7c1d3587f4b17aeb197dcbe74a753adbf897ab38e32f`. LF is pinned by .gitattributes. See local-dataset.md for provenance and schema.

All **44 PHP files** passed PHP 8.3 syntax checks; the supported-runtime suites also load production code on PHP 8.1. Both sites used WP_DEBUG and WP_DEBUG_LOG. Logs contained no PHP Warning, Notice, Deprecated, Fatal, Parse, uncaught exception/TypeError or WordPress database error. Expected sanitized application diagnostics from deliberate outage tests are separate from PHP runtime errors. Git whitespace validation passed.

The full integration stress process peaked at 62193664 bytes on PHP 8.3 and 81072128 bytes on PHP 8.1, including WordPress/CLI and repeated fixture/full imports. These are test-process measurements, not a frontend memory guarantee.

## Limits and deferred work

No automatic sync, cron, admin dataset UI, WooCommerce/forms or uninstall deletion was added. Snapshot freshness is limited to the documented source/build time. InnoDB is required for both owned tables and the WordPress options table; other engines fail safely. Full multisite network provisioning, alternative database engines and external object-cache combinations were not tested. Current-version metadata/probes are not a complete audit of manual row corruption. Phase 5 may add controlled dataset sync, status/repair and administration.

History retains the Phase 2 and Phase 3 commits. Only reviewed Phase 4 source, data, tests and documentation are intended for the local commit; no push, tag or release is part of this phase.
