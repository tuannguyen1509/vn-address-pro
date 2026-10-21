# VN Address administration

Open **Settings → VN Address**. All page rendering and actions require `manage_options`; each POST also verifies a nonce scoped to its exact action. Requests go through the authenticated admin-post handler, with safe redirect and escaped one-time feedback. Nonce alone is insufficient. No public REST write route exists.

## General

Shows plugin version, Vietnam Two-Level Administration, installed dataset version and database schema version. Automatic Dataset Updates offers Disabled (default), Daily, Weekly and Monthly (30 days). Saving reconciles one WP-Cron event without downloading a dataset. Deactivation removes the event while preserving the configured option; reactivation restores it.

## Data

Shows metadata/source/generated UTC time/installed site-local time/counts/checksum, last operation, last success/failure and last observed provider status. Status text distinguishes Healthy, Update Available, Syncing, Error, Dataset Missing and Integrity Warning. Healthy is a lightweight availability status unless explicit validation has been run; no full integrity scan/network probe is run by opening a tab. Remote status is a past observation, not a live monitor.

Check for Updates downloads a validated candidate and compares it, without replacing local rows. Sync Now downloads, validates and atomically publishes only a newer complete candidate. These are synchronous operations; an active sync lease disables operation buttons. A second request returns a controlled already-running result. No polling/React/external framework is used.

## Tools

- Validate Dataset: read-only integrity audit; shows the latest report/time/issues.
- Repair Dataset: validates current state, uses a complete valid remote candidate or the known bundle, and never silently downgrades.
- Reinstall Bundled Dataset: offline recovery, using the bundled version. A newer installed dataset requires explicit downgrade acknowledgement.
- Clear Remote Cache: clears active/negative/failure state for the list and installed province codes; retains last-known-good data and active refresh leases.

Feedback after successful recovery identifies the selected source. No Delete All Data or clear-LKG operation is provided.

## System Status

Shows WordPress/PHP/plugin/schema/dataset versions, local availability, source/counts, last operation, recurrence/next scheduled execution, WP-Cron configuration, persistent object-cache presence and current engines for the two address tables plus options. Missing/non-InnoDB tables show Needs Repair. It does not alter engines.

Phase 7 also shows WooCommerce version/detection, supported/inactive integration state, configured checkout type, HPOS mode and unsupported Checkout Blocks status. No WooCommerce settings tab/toggle or global admin notice is added.

Operation times use the site's WordPress timezone. Snapshot generated_at is explicitly labeled UTC. Admin notices are capability restricted; frontend visitors receive no administrative debug information.

The page uses native WordPress navigation, tables, forms, labels and notices. It loads **no custom CSS or JS assets**, either on this page or elsewhere. New markup classes use vnap-. Server-rendered feedback requires no async accessibility mechanism.

See [sync design](dataset-sync.md) for version/checksum semantics, data safety and provider limitations; [validation results](phase5-validation.md) for the tested matrix.
