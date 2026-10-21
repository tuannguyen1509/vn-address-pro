# Dataset sync, integrity and recovery

## Sources and entry points

LocalAddressRepository remains the normal runtime source of truth. Province Open API v2 is an update source and the separate Phase 3 emergency lookup provider. The shipped bundle is an installation/recovery source. Public REST/legacy lookups never check updates, sync or run a full integrity audit. The existing Phase 4 missing-version installation checkpoint remains, using local files only; it is not a scheduled remote update or an automatic corruption repair tool.

DatasetSyncService exposes internal check(), sync(), repair() and reinstall() operations. Production callers are capability/nonce-protected admin-post actions and the configured WP-Cron callback. There is no public write REST route or provider URL setting. No provider filter or stable public sync SDK is introduced.

## Fetch and comparison

DatasetProviderInterface returns a complete canonical package (dataset + manifest) or WP_Error. get_remote_version() returns a validated manifest; the default adapter must download the full source to obtain it. Sync/check reuse fetch_dataset() once rather than issuing a second version request.

ProvinceOpenApiDatasetProvider uses one fixed HTTPS request to `https://provinces.open-api.vn/api/v2/?depth=2`: timeout 30 seconds, redirects disabled and response size limited to 16 MiB. Required nested collections must all be present; mismatched parents/global duplicate ward codes are rejected. Parents omitted by the nested source format are materialized from their containing province. There is no N+1 HTTP, partial merge or synchronous retry loop.

The upstream [v2 API](https://provinces.open-api.vn/) describes the post-July-2025 administration model. The consumed response has no official snapshot version/checksum contract. The default adapter therefore uses a **local UTC observation timestamp**, not a purported upstream release date. Unchanged content retains its installed version. Changed content receives the current UTC YmdHis timestamp. Check for Updates consequently downloads/validates data without importing it; it cannot perform a lightweight metadata-only upstream request.

DatasetValidator validates timestamps by exact UTC datetime round trip; only fixed-width valid strings are compared with strcmp. Newer candidates can publish; older candidates return REMOTE_DATASET_OLDER. Same version and same content return NO_UPDATE without rewriting rows. Same version with changed content returns VERSION_CHECKSUM_CONFLICT. A changed payload observed in the same second is a conflict rather than an implicit overwrite. A site clock behind the installed snapshot may reject a legitimate update as older; correct the clock, do not bypass version protection.

There are two distinct hashes:

- `checksum`: SHA-256 of exact canonical JSON bytes including version, in the existing Phase 4 pretty UTF-8/LF representation. Package validation checks this before SQL.
- `content_checksum`: SHA-256 of normalized persistent fields, excluding version. Provinces are sorted by numeric code; optional fields receive the exact database defaults; ward array order is retained. This allows comparison after SQL round-trip and avoids treating irrelevant JSON property order or absent/default fields as a new dataset. Same-version content conflicts are never overwritten by ordinary sync.

Existing Phase 4 metadata without a content hash is compared against the verified bundle when version and bundle checksum match. Unknown older metadata without that identity requires validation/controlled recovery, rather than silently trusting the current database as an integrity baseline.

Without an upstream authenticated manifest/count/version, a syntactically valid HTTP-200 response omitting an entire province cannot be proven incomplete from the response alone. The one-request strategy avoids multi-request partial-success publication but does not invent fixed geographic totals or claim authoritative freshness/rollback detection. A versioned trusted manifest provider is future work.

## Atomic import and locks

Both bundled and remote packages use DatasetInstaller::install_package(). No second importer exists. It validates the complete package, checks InnoDB/schema, acquires the existing installation lease, starts a transaction and verifies sync ownership plus the expected installed version before replacing rows. Schema DDL is outside the replacement transaction.

The existing transaction acts as staging: uncommitted replacement rows are invisible to other connections. Prepared batches populate provinces and wards; counts, orphan relationships, canonical fields and a reconstructed content checksum must all match before COMMIT. Versions, source, hashes, counts and installed_at metadata commit with rows. No TRUNCATE, partially published metadata or table swap is used. The concurrency test reads real REST through a second database connection while the writer is between province and ward INSERTs; it receives the previous complete dataset.

DatasetSyncLock uses a separate `vnap_dataset_sync_lock` option row with a random ownership handle and a 600-second TTL. INSERT IGNORE and compare-and-swap reclaim prevent double clicks/concurrent cron. An expired worker cannot release a new worker's lease. Before import, its row is locked FOR UPDATE until publication completes; a stale or replaced worker cannot publish. All normal failure/exception paths release the lease. The existing shorter installation lease still coordinates bundled installation and remote publication.

Import/verification/COMMIT failures roll back old rows and installed metadata. Result statuses are defined by DatasetSyncResult, with sanitized translated messages. Errors do not include provider exception text, request bodies, headers, credentials or paths. Logger receives bounded classification/status values, not datasets. Post-commit observer failures do not pretend that committed data was rolled back.

After successful publication, `vnap_dataset_updated($old_version, $new_version)` fires with committed metadata available. It also fires for explicit same-version repairs/reinstalls. The prior `vnap_dataset_installed($manifest)` action remains. Failed/no-update/check-only operations do not fire the updated hook.

## Scheduling and metadata

Automatic updates default to **Disabled**. Daily/weekly use WordPress recurrence; monthly means a fixed **30 days**, not a calendar month. First execution is one hour after enabling. Repeated saves retain one event; changing cadence removes the old event. Disable/deactivate clears only `vnap_dataset_sync`. Reactivation restores the configured recurrence, retaining data and settings. WP-Cron needs traffic or an external runner; see the [WordPress scheduling reference](https://developer.wordpress.org/reference/functions/wp_schedule_event/). It runs in the scheduled job request, not the visitor's lookup callback. No remote check is registered on frontend init.

DatasetMetadata centralizes dataset and sync option names. `vnap_dataset_sync_state` holds at most last_check, last_attempt, last_success and last_failure (no growing history). Each has UTC Unix timestamp, status, source and old/new versions. Dataset metadata stores installed_at as UTC Unix time. generated_at remains the canonical UTC ISO string. Admin formats operation/installation times with wp_date() in the WordPress timezone. Phase 4 installations without installed_at show Not recorded until the next successful installation.

After publish, remote active/negative/failure caches for old/new installed province codes and the province list are invalidated. Manual Clear Remote Cache covers currently installed codes. LKG and in-flight refresh leases are retained; unknown historical codes are not wildcard-deleted. Local reads have no additional query cache. A retained LKG can be older during emergency fallback; this is an explicit resilience tradeoff, not the current local dataset.

## Audit and repair

DatasetIntegrityAuditor runs only when explicitly requested by a tool/repair, never on each page view. It reads current tables, checks engines/schema, nonempty/valid records, counts, duplicate codes, orphan wards, ward positions, metadata and reconstructed content identity. Its structured report contains healthy, issues, counts, orphan_wards, duplicate_issues, engines, checksum and timestamp. The manual action stores only the latest report for display; auditing does not alter dataset rows.

Repair returns HEALTHY without HTTP when no issue exists. Damaged data can be replaced by a validated same/newer remote candidate. If remote fetch/validation fails, it tries the known shipped bundle. Ordinary repair cannot downgrade to an older bundle. Reinstall Bundled Dataset makes no HTTP request, uses the same atomic importer and requires an explicit checked downgrade warning when the installed version is newer. Failed validation/repair preserves whatever recoverable rows existed before the attempt. There is no destructive Delete All Data, automatic frontend repair or LKG deletion action.

InnoDB is required for both address tables and WordPress options. Full multisite network scheduling/provisioning and external object-cache combinations remain untested; all table/options/cron state is per-site. No uninstall deletion, integration, mail notification or background queue was added.
