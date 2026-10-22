# Phase 10 validation — 2026-10-22

Scope: explicit migration foundation only. Baseline `7072594` (Phase 9), initially clean. Earlier history remains intact. No version release, schema/dataset change, shipping/form integration, push or tag. New behavior is reachable only through protected explicit admin operations; no automatic migration.

## Environment and runtime/storage matrix

Disposable isolated Docker network/databases; synthetic records only; HTTP/mail blocked by temporary test-only fixtures. WordPress/Woo source, browser package, credentials, fixture JSON, logs and databases stay outside Git. PHP tests run in independent WP-CLI processes; browser uses external Playwright 1.64.0/Chromium. Existing unrelated containers/sites were not changed.

| Runtime | Storage | Migration matching | Migration integration | Migration browser | Existing account/admin PHP / assets / parity | Existing management browser |
| --- | --- | --- | --- | --- | --- | --- |
| WP 6.3.7 / PHP 8.1 / Woo 8.2.0 | Legacy | 51 PASS | 83 PASS | 23 PASS | 43 / 4 / PASS | 46 PASS |
| WP 6.3.7 / PHP 8.1 / Woo 8.2.0 | HPOS | 51 PASS | 83 PASS | 23 PASS | 43 / 4 / PASS | 46 PASS |
| WP 7.1.3 / PHP 8.3 / Woo 11.2.1 | Legacy | 51 PASS | 83 PASS | 23 PASS | 43 / 4 / PASS | 46 PASS |
| WP 7.1.3 / PHP 8.3 / Woo 11.2.1 | HPOS | 51 PASS | 83 PASS | 23 PASS | 43 / 4 / PASS | 46 PASS |

Matching is storage-independent and runs on both runtimes. Order storage switches use Woo's official `wc hpos sync` / older `wc cot sync` before enabling/disabling HPOS. No production code changes store configuration. Customers are WordPress user accounts represented through WC_Customer; scan does not assume a single customer role. Order allowlists use bounded CRUD reads because WC 8.2's CPT query ignores `include`; native ordinary scans use bounded wc_get_orders.

## New tests

`Migration/MatchingTest.php`: 51 assertions for whitespace, Unicode composed/decomposed, accent-insensitive names, case/punctuation, prefixes, safe HCM aliases, strict code parsing, unknown extension codes, bare-code MEDIUM, ward/province relationship, conflicting metadata, district-only city, duplicate normalized names, administrative type changes, original retention, canonical no-op, order snapshot/default versus explicit display policy, malformed/foreign/unknown-country statuses.

`Migration/IntegrationTest.php`: 83 assertions, including empty customer/order scopes; bounded pagination/date query with and without ID allowlist; last calendar day; malformed metadata redaction; zero SQL mutation statements during dry-run; identical full options rows and customer/order administrative database values before/after; explicit apply/strong order confirmation; exact saved target; billing/shipping and customer/order isolation; source fingerprint and dataset identity stale checks; signed token tamper/expiry/user/site; nonce/capability; HIGH-only default and explicit MEDIUM; no invalid/LOW apply; unhealthy dataset and active sync lease blocks; per-record injected save failure with unrelated success; exact minimal rollback journal; non-autoload, replay, audit index persistence failure blocking all address writes, and latest-20 retention; zero HTTP attempts. Historical orders contain actual product items when disposable Setup supplies a product, and retain native addresses, item/tax/shipping/fee/coupon data, total, status, payment method and customer association.

Dry-run uses a query observer to assert **no INSERT/UPDATE/DELETE/REPLACE/DDL**, plus full options snapshots and reloaded Woo administrative snapshots. It creates no plan option/transient/audit/lock. The observer is test-only and removed after the window. Fixture creation/cleanup occurs outside that window. The audit index is isolated/restored so retention tests never purge other fixture journals.

`Migration/BrowserTest.mjs`: 23 actual browser/HTTP assertions for protected tab/default Customers, no display conversion/default asset bundle, real customer/order preview, no preselection, HIGH-only, no unrelated PII, candidate and before/after visibility, real selected customer apply, stronger order confirmation, real confirmed order apply, invalid-nonce HTTP 403 and no browser exceptions. Setup/fixtures are disposable only. No production test-only routes or hooks are added.

## Existing regression suites

All existing runners and expectations remain unchanged. Both runtimes with Woo absent passed FoundationTest; 18 REST v1 baseline comparisons; 42 core; 60 reliability; 40 repository; 65 dataset sync/admin; four offline local routes; 32 frontend PHP; absent/unsupported Woo; standalone RequirementsTest. With Woo active, the 18 REST comparisons also passed on both runtimes.

Existing frontend browser **41**, offline HTTP **4** with outbound counter **0**, and admin HTTP **30** passed on both runtimes. Generic frontend uses default Twenty Twenty-Five / Twenty Twenty-Three themes. Existing reversed-response race tests passed on Account/Admin on both runtimes with HPOS. Account/Admin browser saved and reloaded actual Classic and Store API orders with items on both storages/runtimes.

| Existing suite | Minimum legacy | Minimum HPOS | Newer legacy | Newer HPOS |
| --- | --- | --- | --- | --- |
| Classic PHP | 47 PASS | 47 PASS | 47 PASS | 47 PASS |
| Classic browser | 20 PASS | 20 PASS | 20 PASS | 20 PASS |
| Classic HTTP / account checkout | 20 PASS | 20 PASS | 20 PASS | 20 PASS |
| Block PHP | 23 PASS | 23 PASS | 23 PASS | 23 PASS |
| Block browser | 29 PASS | 29 PASS | 29 PASS | 29 PASS |
| Direct Store API | 23 PASS | 23 PASS | 23 PASS | 23 PASS |

Classic and management browser use Twenty Twenty-One. Block browser uses Twenty Twenty-Five on newer Woo and Twenty Twenty-One on minimum Woo, matching the previously validated matrix. No old test/theme/production checkout behavior was changed to accommodate the migration feature.

Plugin deactivate → activate succeeded on both runtimes without duplicate classes/routes/hooks or PHP fatal. All **104 PHP files** pass syntax under PHP 8.1. New browser JavaScript passes node syntax checking. No runtime/build dependency is added. Git whitespace/scope/secret/artifact checks are part of pre-commit validation.

## Frozen public contract

REST schema unchanged: **YES**. Baseline fixture changed: **NO**. Namespace, routes, parameters, success/error payloads, search, sort, health, legacy signatures and canonical four-key model remain unchanged. SHA-256 of `tests/Contract/fixtures/rest-v1.json`:

```text
436c9637f0444bacc719beab0057bdcbe8a5dc49af0ce4ea8a979b792bce5e62
```

No existing production controller/service/provider/cache/dataset/sync/checkout/account/order adapter was modified. Existing SettingsPage receives the protected tab, load preflight and availability row. Existing tests modified: README only; new migration tests are separate.

## Findings corrected during development

- Nonce rejection inside a rendered admin callback returned a 200 error page after headers. Capability/nonce preflight now runs at `load-settings_page_vn-address-pro`, before headers; browser requires HTTP 403.
- WC 8.2 legacy query silently ignored `include`, initially masked by an empty test store. A populated store exposed it. Bounded explicit order allowlists now load through CRUD rather than a storage-specific query argument. Existing public scans remain bounded Woo queries; tests cover both paths.
- Dry-run query tracking initially attempted a nonexistent wpdb `save_queries` property. The test uses the public query filter instead; production was unaffected.
- Initial browser asset assertion matched navigation links containing `migration`; it now checks actual plugin script/stylesheet URLs only. Scope assertions were not weakened.
- The initial minimum Block rerun used the wrong virtual product ID and correctly failed at the virtual-cart case. Reading the ID from Setup's option resolved it; the original runner then passed all 29 checks.
- Minimum setup initially lacked the Classic theme on the newer site; the standard theme was copied into the isolated test installation. Production was unaffected.

## Diagnostics and limitations

Final controlled migration, native lifecycle and browser validation on both runtimes produces no new PHP Warning, Notice, Deprecated, Fatal, Parse or database error. WP_DEBUG retains only safe existing logger diagnostics (batch UUID, classification/count), no contact/address text or tokens. Earlier development/test harness failures were corrected, then logs reset for controlled validation. Native initial WordPress theme-update connectivity warnings and previously documented Woo 8.2 offline setup/empty-cart hydration notices remain upstream; vendor code/error reporting were not patched/suppressed. See Phase 8/9 reports for baseline theme/vendor limitations; this phase does not certify additional combinations.

Known limits: synchronous bounded offset batches can shift under concurrent inserts/deletes; source concurrency is optimistic rather than atomic against arbitrary external writers; the dataset lease serializes only cooperating sync/apply operations. A crash or partial CRUD failure can leave PREPARED/FAILED_SAVE entries requiring inspection, not an assumed rollback. Latest 20 journals are a bounded rollback foundation, not a permanent backup. Rollback execution/UI, manual resolution, historical boundary/code crosswalks, export/CSV, queue, full multisite and external object-cache stress coverage remain deferred. Even exact current-name matching requires human historical review. No shipping/form/provider-specific behavior is implemented.

Recommended Phase 11: Shipping Provider Mapping Foundation **or** Form Integration Foundation after review. Neither is started. Commit only after all critical validation passes; local commit message `feat(migration): add legacy address migration foundation`. No push/tag/release.

## Final plugin file tree

```text
vn-address-pro/
  assets/
    blocks/
      billing/
        block.json
      checkout.asset.php
      checkout.js
      shipping/
        block.json
    css/
      vn-address.css
    js/
      address-data-client.js
      vn-address.js
      woocommerce-address-management.js
      woocommerce-classic.js
  CHANGELOG.md
  data/
    manifest.json
    vn-address.json
  docs/
    address-normalization.md
    admin-settings.md
    architecture.md
    cache-reliability.md
    database-schema.md
    dataset-sync.md
    frontend-component.md
    legacy-migration.md
    local-dataset.md
    phase10-validation.md
    phase1-validation.md
    phase2-validation.md
    phase3-validation.md
    phase4-validation.md
    phase5-validation.md
    phase6-validation.md
    phase7-validation.md
    phase8-validation.md
    phase9-validation.md
    rest-v1-contract.md
    store-api-integration.md
    woocommerce.md
    woocommerce-blocks.md
    woocommerce-my-account.md
    woocommerce-order-admin.md
    woocommerce-state-codes.md
  includes/
    functions.php
    legacy-api.php
  README.md
  src/
    Address/
      AddressService.php
      Normalizer.php
      Validator.php
    Admin/
      MigrationPage.php
      SettingsPage.php
    Cache/
      AddressCache.php
      RefreshLock.php
    Compatibility/
      Legacy.php
    Contracts/
      AddressProviderInterface.php
      AddressRepositoryInterface.php
      CacheInterface.php
      DatasetProviderInterface.php
    Data/
      BundledDataset.php
      DatasetInstaller.php
      DatasetIntegrityAuditor.php
      DatasetLoader.php
      DatasetMetadata.php
      DatasetPackage.php
      DatasetValidator.php
    Database/
      SchemaManager.php
      TableNames.php
    Frontend/
      AddressComponent.php
      Assets.php
      FieldRenderer.php
      Shortcode.php
    Http/
      HttpClient.php
    Integrations/
      WooCommerce/
        AddressEditor.php
        AddressFields.php
        AddressValidator.php
        Admin/
          OrderAddress.php
        Assets.php
        Blocks/
          BlocksIntegration.php
          StoreApi.php
        ClassicCheckout.php
        Compatibility.php
        CustomerAddress.php
        MyAccount/
          MyAccountIntegration.php
        OrderAddress.php
        States.php
        WooCommerceIntegration.php
    Migration/
      AddressNormalizer.php
      LegacyAddressAnalysis.php
      LegacyAddressAnalyzer.php
      MigrationAudit.php
      MigrationService.php
      NameMatcher.php
      PlanCodec.php
      ProvinceMatcher.php
      WardMatcher.php
      WooAddressSource.php
    Plugin.php
    Providers/
      ProvinceOpenApiDatasetProvider.php
      ProvinceOpenApiProvider.php
      ReliabilityPolicy.php
      Validation/
        ProvinceOpenApiValidator.php
    Repository/
      FallbackAddressRepository.php
      LocalAddressRepository.php
      RemoteAddressRepository.php
    Rest/
      V1/
        AddressController.php
        ErrorMapper.php
    Support/
      Logger.php
      Requirements.php
      Search.php
    Sync/
      DatasetScheduler.php
      DatasetSyncLock.php
      DatasetSyncResult.php
      DatasetSyncService.php
      RemoteCacheCleaner.php
  tests/
    AdminHttpSmokeTest.mjs
    Contract/
      fixtures/
        rest-v1.json
      RestV1ContractTest.php
    FoundationTest.php
    Frontend/
      BrowserTest.mjs
      RenderTest.php
      TestPage.php
    Integration/
      DatasetSyncTest.php
      LocalRepositoryTest.php
    LiveSmokeTest.php
    LocalRepositorySmokeTest.php
    Migration/
      BrowserTest.mjs
      IntegrationTest.php
      MatchingTest.php
      Setup.php
    OfflineHttpSmokeTest.mjs
    README.md
    RemoteDatasetSmokeTest.php
    RemoteProviderSmokeTest.php
    RequirementsTest.php
    Support/
      BlockHttp.php
      CacheState.php
      LocalState.php
      SyncHttpFixture.php
    Unit/
      CoreTest.php
      ReliabilityTest.php
    WooCommerce/
      AbsentTest.php
      Blocks/
        BrowserTest.mjs
        IntegrationTest.php
        OfflineFixture.php
        Setup.php
        StoreApiTest.mjs
      BrowserTest.mjs
      HttpCheckoutTest.mjs
      IntegrationTest.php
      Management/
        AssetsTest.php
        BrowserTest.mjs
        IntegrationTest.php
        ParityTest.php
        RaceTest.mjs
        Setup.php
      OfflineFixture.php
      Setup.php
  tools/
    build-dataset.php
  vn-address-api.php
```
