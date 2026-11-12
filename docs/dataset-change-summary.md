# Dataset change summaries (2.0.2)

Settings → VN Address → Data displays counts from the **last update check**:
installed/candidate province and ward totals, additions, removals, renames and wards
whose parent province changed. The check timestamp is displayed separately.

Only a structurally valid local snapshot and validated candidate are compared. If
the local snapshot cannot be compared, no counts are invented. Failed checks do not
reuse a previous candidate's summary. Administrative counts do not contain customer
or order information, names, emails or street addresses.

Check still downloads the complete remote payload because the provider does not
supply an authenticated versioned manifest. It records diagnostic status and counts
but does not publish rows. Sync uses the existing lease, version/downgrade checks,
atomic import and integrity verification. Neither operation migrates customer/order
addresses. The table is historical observation, not an authenticated upstream
completeness guarantee or a binding preview of a future Sync response.

`tests/Unit/DatasetDiffTest.php` has 15 assertions for additions/removals/renames/
parent changes, unchanged data and result serialization compatibility. The existing
65-check dataset sync suite also passes. An isolated native check against a synthetic
validated API response reported one renamed province while installed content checksum
remained unchanged; the admin Data tab displayed those counts correctly.
