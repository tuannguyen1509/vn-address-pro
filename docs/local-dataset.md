# Bundled local address dataset

Phase 4 provides a persistent WordPress database repository, not a transient cache. Once installed, ordinary lookups work with Internet blocked and all remote caches absent. No automatic remote update, cron or Sync Now UI is included.

## Source and provenance

The shipped snapshot was generated at **2026-10-21T03:44:54Z** from [Province Open API v2](https://provinces.open-api.vn/), using `https://provinces.open-api.vn/api/v2/?depth=2`. The upstream [project by Nguyễn Hồng Quân](https://github.com/hongquan/vn-open-api-provinces) identifies v2 as the post-July-2025 dataset. These are source/build facts, not an independent claim of legal or real-time completeness.

The root endpoint nests wards under a province and omits their `province_code`; the build tool materializes that parent from the source nesting, without inventing names or codes. It retains source order, codes, names, division types, codenames and province phone_code; unused source fields are omitted. Runtime Normalizer algorithms are unchanged.

Snapshot metadata (descriptive, not validation constants):

- Dataset version: `20261021034454`.
- Dataset format/schema: `1`.
- Province count: `34`; ward count: `3321`.
- Dataset bytes: `849759`, UTF-8 without BOM.
- SHA-256: `fd620c46def82960832d7c1d3587f4b17aeb197dcbe74a753adbf897ab38e32f`.

## Canonical format

`data/vn-address.json` contains `schema_version`, `dataset_version` and a nonempty `provinces` list. Each province has code/name, optional division_type/codename/phone_code and a required `wards` list. Each ward has globally unique code/name and the containing province_code; division_type/codename are optional. Canonical codes are positive JSON integers. Names retain Vietnamese accents. Empty wards are valid; an empty province dataset is invalid.

`data/manifest.json` contains matching schema/version, source and provenance URLs, generated_at, actual province/ward counts and the checksum. Counts are compared to the file's contents for integrity, not to fixed political totals. Future snapshots with different totals remain valid.

Version is a validated **14-digit UTC build timestamp, YYYYMMDDHHMMSS**. It identifies this project's source snapshot/build, not an official upstream version or plugin release. Fixed-width chronological comparison (`strcmp` after datetime validation) is unambiguous. Automatic bundled upgrades accept only a newer snapshot; they do not downgrade a newer installed dataset. Controlled internal repair/testing can explicitly request replacement.

`Data\BundledDataset` holds generated VERSION/CHECKSUM constants paired with the two JSON files. It allows lightweight ordinary checks without reading/decoding the dataset or manifest on every request. Keep these three artifacts together in a release ZIP.

## Checksum representation and build

Checksum is SHA-256 over the **exact bytes** of `vn-address.json`: PHP JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES, UTF-8, followed by one LF. `.gitattributes` fixes `data/*.json` to LF on Windows checkouts so Git line-ending conversion cannot invalidate the bundle. This detects corruption/truncation/modification; it is an integrity checksum, not a signature/authentication mechanism.

Development/build-time command, never called from activation or frontend:

```sh
wp eval-file wp-content/plugins/vn-address-pro/tools/build-dataset.php
# Optional output root (useful for a read-only source mount):
wp eval-file wp-content/plugins/vn-address-pro/tools/build-dataset.php /tmp/vnap-build
```

The tool fetches the current source once, validates the complete canonical snapshot and writes JSON/manifest/generated PHP constants. Review/source-check/test generated changes before release; this is not a production remote sync engine. Standard source URL stays fixed, not supplied by a REST caller. Filesystem and network access are build-tool responsibilities, not runtime installation requirements.

## Load, install and upgrade

DatasetLoader reads local files only (16 MiB dataset / 64 KiB manifest bounds), checks JSON/schema/version/provenance fields/date, compares counts and verifies SHA-256. DatasetValidator checks required fields/types, unique province/global ward codes, nested parent relationships, UTF-8 and database field limits before any row replacement.

Activation and the existing `init` upgrade checkpoint call DatasetInstaller. A Phase 3 installation with no schema/dataset markers creates two tables and imports the bundled file without reactivation or HTTP. Current schema/snapshot metadata makes subsequent checkpoints return immediately, without JSON decoding, full counts or dbDelta. Failures use a short local retry marker/admin notice; frontend can continue through the remote fallback. Fresh activation fails with a sanitized admin error when installation cannot complete.

Import/metadata replacement is transactional; previous valid data survives checksum/schema/write failures. Only verified complete rows/counts/relationships are published. Installation is idempotent and automatically rejects downgrade; interrupted requests can retry after the expiring lease. See [schema](database-schema.md) for storage requirements and transaction details.

## Selection and error semantics

AddressService depends on AddressRepositoryInterface. FallbackAddressRepository selects LocalAddressRepository when its installed metadata and lightweight table probes are usable. It does not probe HTTP to check remote availability. SQL statement errors/missing tables are classified as `vnap_dataset_unavailable` and may fallback to RemoteAddressRepository/Phase 3 reliability.

Local not-found is authoritative: it maps to the existing `vn_address_not_found` HTTP 404 and never causes outbound lookup. The old remote HTTP-404-to-502 error mapping is still preserved on the remote path. Public routes/parameters/response fields/types, search/sorting and legacy health are unchanged; no DB/source metadata is added to v1.

`get_ward(code)` is an indexed local internal method, with no new REST route. The current remote provider has no single-ward contract; its adapter returns a controlled unavailable error rather than scanning all provinces or adding an unplanned remote endpoint.

Local usability is not a full integrity audit on every request. Arbitrary manual row edits/truncations beyond the lightweight probes require controlled repair/future integrity tools. The default installer does not repeatedly reload a healthy current snapshot. InnoDB/database failure can force remote fallback, which can still fail if its caches/network are also unavailable.

No data is deleted on deactivation/uninstall in this phase. Full multisite provisioning, automatic update provenance/version policies, admin status/repair and sync belong to a later phase.
