# Persistent address schema — version 1

`VNAP_DB_VERSION` aliases `Database\SchemaManager::VERSION` (integer **1**). The installed marker is `vnap_db_version`, independent of plugin 2.0.0 and dataset snapshot version. Dataset JSON format also currently has schema 1; it is a separate version/concept.

Table names are generated only by `Database\TableNames`, using the current site's `$wpdb->prefix`, never a hard-coded `wp_` or `$wpdb->base_prefix`. Both tables use `ENGINE=InnoDB` and `$wpdb->get_charset_collate()` for the site's charset/collation. The WordPress options table must also be InnoDB so rows and published metadata can commit/rollback together. Unsupported transactional storage is rejected safely, not silently converted.

## `{prefix}vnap_provinces`

| Column | Type | Meaning |
| --- | --- | --- |
| code | bigint unsigned, primary key | Canonical administrative identifier |
| name | varchar(191), not null | Original accented source name |
| division_type | varchar(191), default empty | Source type, preserved for canonical records |
| codename | varchar(191), default empty | Source codename |
| phone_code | int unsigned, nullable | Required to reproduce the existing public field/default |

Index: `PRIMARY KEY(code)`, which also enforces uniqueness. Province list is one SELECT without wards.

## `{prefix}vnap_wards`

| Column | Type | Meaning |
| --- | --- | --- |
| code | bigint unsigned, primary key | Canonical ward identifier |
| province_code | bigint unsigned, not null | Parent province code |
| name | varchar(191), not null | Original accented name |
| division_type | varchar(191), default empty | Ward/commune/special-zone source type |
| codename | varchar(191), default empty | Source codename |
| position | int unsigned, not null | Original position within its province snapshot |

Indexes: `PRIMARY KEY(code)` and `KEY province_position(province_code,position)`. The composite index supports parent-scoped reads and legacy detail order. Single-ward lookup uses the primary key.

There is no SQL foreign key dependency; DatasetValidator/import verification enforce the relationship and ward codes are globally unique. A province detail is one LEFT JOIN statement, not N+1, and sees a consistent committed view of both tables. `/wards` still applies the old natural-name sorting in AddressService; `/province/{code}` preserves `position` order.

## Intentional omissions

No auto-increment surrogate ID is required; the administrative code is the stable key. `short_name`/province display type are derived by the unchanged Normalizer. No `normalized_name` or indexes on codename/name are added because Phase 4 has no corresponding SQL lookup/search. Legacy accent-sensitive search remains in the service. Installation time/version/counts are stored once as dataset metadata, not duplicated on every row.

## Schema and metadata lifecycle

`dbDelta()` creates/updates the owned schema only during installation/upgrade/explicit internal repair. It does not run on ordinary current-version requests. Engine/column checks precede DML. The schema marker is set only after the table structure is verified.

Import uses prepared batches of at most 100 rows. After acquiring an ownership-safe installation lease, it begins an InnoDB transaction, locks/rechecks the lease and authoritative installed version, replaces provinces/wards, verifies counts/orphans and writes `vnap_dataset_version` plus `vnap_dataset_metadata` in the same transaction. No TRUNCATE or DDL occurs inside replacement. Failed ward writes roll back the earlier province writes and metadata. Readers see the prior committed dataset until commit.

Metadata is written without exposing uncommitted values through WordPress option cache; option caches are invalidated after commit/rollback. The public `vnap_dataset_installed` action receives the manifest after successful commit. Local records have no separate transient cache to invalidate. Remote active/LKG/negative state is deliberately retained because it belongs to a different source and is used only when local is unavailable.

Per-site installation is lazy on `init` and activation. Full multisite network provisioning/switch-to-blog workflows are not claimed as tested. Deactivation and plugin deletion retain tables/options; Phase 4 does not install an automatic destructive uninstall handler.
