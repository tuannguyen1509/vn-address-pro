# Phase 11 validation — 2026-10-22

Baseline: clean `72b5e69` (Phase 10). Scope: reusable form foundation and optional Contact Form 7 reference adapter only. Version remains **2.0.0 — Unreleased**. No dataset/schema/provider/cache/migration/Woo implementation changes, submission storage, other builder adapter, shipping integration, push, tag or release.

## API/source review

Official CF7 **5.8.1** and **6.2.3** packages were downloaded outside Git and inspected before implementation: form-tag manager/object, native select/text rendering, validation, posted-data setup, submission lifecycle, mail replacement, REST feedback, native tag generators and browser reset/events. Registration uses wpcf7_init/name-attr, native required `*`, wpcf7_validate_{type}, WPCF7_Validation, current submission get_posted_data, and the public mail-tag replacement filter. No private production API or raw $_POST manipulation is used. Native selectable-values conversion is deliberately not enabled so malicious arrays cannot become accepted scalar choices.

CF7 6.2.3 requires WP 7.1/PHP 8.3; 5.8.1 requires WP 6.2/PHP 7.4 and was tested within VN Address Pro's WP 6.3/PHP 8.1 floor. Minimum API baseline is 5.8.1, based on inspected/used APIs and actual tests. No claim covers an untested cross-product of old CF7/new PHP, future CF7 releases, or third-party add-ons. Official references are linked in [CF7 documentation](contact-form-7.md).

Individual form-tags are real CF7 fields; posted values remain canonical codes. Ordinary mail-tags resolve names; explicit `_vnap_code_FIELD` mail-tags expose codes. No fabricated companion posted fields, hidden-name authority or compound submission workaround. New generic contracts contain no CF7-specific types. The registry boots conditionally once, and System Status reports available/booted adapters without adding settings/dependencies.

## Disposable environment and new tests

Isolated Docker sites/network/volume, synthetic records only. Browser runs external Playwright 1.64.0/Chromium. Packages, WordPress/Woo installations, JSON fixtures, credentials, logs, databases and node_modules stay outside Git. HTTP/mail are intercepted in temporary test fixtures; no real SMTP delivery. The CF7 MU fixture disables spam only for controlled tests and returns in-memory composed mail in test responses; it is never loaded by production bootstrap and must never be installed on a merchant site. Production does not change spam/reCAPTCHA/acceptance hooks.

| Pairing | CF7 PHP | CF7 browser | Native HTTP | Native generator |
| --- | --- | --- | --- | --- |
| WP 6.3.7 / PHP 8.1 / CF7 5.8.1 | 32 PASS | 23 PASS | 14 PASS | 9 PASS (v1) |
| WP 7.1.3 / PHP 8.3 / CF7 6.2.3 | 32 PASS | 23 PASS | 14 PASS | 9 PASS (v2) |

Tests ran with Woo absent and present where relevant; CF7 render/AJAX/mail operate independently. With CF7 inactive after actual use, Forms/AbsentTest passes registry duplicate/single-boot, conditional availability, core and shortcode checks on both runtimes; Woo Classic PHP **47** and migration **83** also pass with CF7 inactive. Core/public routes do not depend on CF7. Adapter/API checks use real functions/classes/version, not plugin folder names.

`Forms/IntegrationTest.php` (32): actual type/required registration, native render and defaults, independent groups, placeholder-only initial wards, CF7 accessibility conventions, shared core resolution, forged-name rejection, optional/required values, malformed/array/unknown/mismatched codes, incomplete optional group, missing/duplicate province reference and duplicate field names, required street, escaping, native submission, captured Mail/Mail (2) plain/HTML, canonical posted values and **zero HTTP attempts** with healthy local data. It restores request state and deletes its owned synthetic form. Warnings/notices during the test become exceptions.

`Forms/BrowserTest.mjs` (23): two actual CF7 forms and a generic shortcode on the same page, single asset inclusion, default/current values, independent groups/forms/shortcode, same-origin dependent load, actual invalid AJAX preserving valid addresses, actual successful AJAX, Mail/Mail (2) content and raw code observations, successful/native reset, canonical default restoration, unique IDs, idempotent scoped init, deliberately abort-insensitive reversed response, zero external browser requests, no console errors/exceptions. Native CF7 successful reset is observed, not simulated by manually clearing fields.

`Forms/HttpTest.mjs` (14): actual native multipart feedback requests reject required empty province/ward, unknown/malformed codes, wrong-parent ward, province[]/ward[] attacks, incomplete optional group and oversized street **before mail**. Valid codes + empty optional fields succeed; captured native emails contain repository names rather than forged browser names, and downstream posted values remain code strings. No JavaScript authority is required for these server checks.

`Forms/GeneratorTest.mjs` (9): native editor produces Province/Ward/Address tags, includes explicit province link and actually inserts each generated tag into the editor without saving. Latest v2 and minimum native v1 both pass; no deprecated v1 registration on latest CF7, no frontend adapter in admin, no uncaught editor exception. No custom generator JS/CSS is added.

`Forms/UnavailableTest.php`: local metadata and owned provider runtime state are temporarily isolated/restored. With local and remote data unavailable, rendering shows controlled feedback and validation rejects unresolved submission without fatal. Existing core fallback behavior is preserved; only healthy-local cases claim zero upstream attempts.

## Existing Phase 1–10 regression

Existing executable tests and frozen REST fixture are unchanged; tests/README gains instructions only. Both runtimes passed FoundationTest; **18** REST baseline comparisons; **42** core; **60** reliability; **40** local repository; **65** dataset sync/admin; four offline local routes; **32** frontend PHP; Woo absent/unsupported and standalone RequirementsTest. Remote-mock suites run after removing MU HTTP blockers so mocks are not overridden. Live upstream smoke probes were not needed or run for this offline integration phase.

With CF7 and Woo active, **18** REST comparisons pass again on both runtimes. Fixture changed: **NO**; schema/routes/parameters/search/sort/health/legacy signatures unchanged. SHA-256:

```text
436c9637f0444bacc719beab0057bdcbe8a5dc49af0ce4ea8a979b792bce5e62
```

| Existing PHP suite | Minimum HPOS | Minimum legacy | Newer HPOS | Newer legacy |
| --- | --- | --- | --- | --- |
| Classic Woo | 47 PASS | 47 PASS | 47 PASS | 47 PASS |
| Block Woo | 23 PASS | 23 PASS | 23 PASS | 23 PASS |
| My Account / Order Admin | 43 PASS | 43 PASS | 43 PASS | 43 PASS |
| Admin asset isolation | 4 PASS | 4 PASS | 4 PASS | 4 PASS |
| Cross-surface parity | PASS | PASS | PASS | PASS |
| Migration matching | 51 PASS | 51 PASS | 51 PASS | 51 PASS |
| Migration dry-run/apply/audit | 83 PASS | 83 PASS | 83 PASS | 83 PASS |

Migration matching is storage-independent and runs on both runtimes. Official `wc hpos sync` / older `wc cot sync` precedes each test storage switch. No production code changes a store's storage configuration.

Existing checkout/account/admin browser and HTTP runners passed on **both runtime pairings with HPOS**: Classic browser **20**, Classic HTTP/account checkout **20**, Block browser **29**, direct Store API **23**, Account/Admin browser **46**, and reversed-response race checks. Those native browser flows reuse actual checkout-created orders with items. Legacy CRUD/save/metadata coverage is the PHP matrix above; this phase does not claim an additional complete legacy browser matrix.

Existing migration admin browser **23** passes on both runtimes with CF7 active in legacy storage. Existing generic frontend browser **41**, offline HTTP **4** (outbound attempt counter **0**), and admin HTTP **30** pass on both runtimes with CF7 active and Woo inactive. Default FSE themes (Twenty Twenty-Five / Twenty Twenty-Three) are used for generic frontend, Twenty Twenty-One for Classic/management, and the previously validated Twenty Twenty-Five / Twenty Twenty-One pair for Blocks. No old expectations or production styles are changed to mask theme/vendor behavior.

## Syntax, lifecycle and WP_DEBUG

All **119 PHP files** pass PHP 8.1 syntax. Updated shared JS, new adapter JS and new browser/HTTP/generator runners pass node syntax. Plugin deactivate → activate and CF7 deactivate → activate after use pass without duplicate classes/hooks or fatal on both runtimes. Version, physical entrypoint, requirements and dataset schema remain unchanged.

Final controlled lifecycle, CF7 PHP/unavailable, actual AJAX/mail, native generator and REST checks on both runtimes are checked with WP_DEBUG/WP_DEBUG_LOG enabled. No new PHP Warning, Notice, Deprecated, Fatal, Parse or database error. Browser console/exception checks pass. Existing safe provider-failure/migration diagnostics are expected; no form contact/mail content is logged by the adapter.

Early harness issues were corrected before final checks: WordPress volume population was not ready at the first CLI install attempt; CLI needed the same readonly plugin bind mount as Apache; an offline theme API request needed a local theme package/activation; generator v1 uses Thickbox's inlineId link rather than the modern dialog selector. A default-value assertion now accepts native attribute ordering, and the initial ward-payload assertion is scoped to the ward select because a ward code can numerically equal a province code. A final native v2 insertion check also needed to await the queued dialog close event (verified in current CF7 source), rather than assert the textarea immediately after click. The exact generated-tag insertion assertion is preserved. Public expectations were not weakened. Initial development/setup errors were cleared before controlled validation. Vendor code/error reporting were not patched or suppressed; previously documented Woo native offline/empty-cart limitations remain outside the new form code.

## Scope, security and remaining limitations

No new REST/AJAX write endpoint, form storage/options/table, cron, external library, jQuery dependency, localStorage, whole-document MutationObserver or polling. Only declared tags are handled; scalar/type/code checks precede core relationship validation. Native CF7 sanitized submission APIs are used, and canonical repository names are escaped for HTML output/mail. No raw $_POST access in new production modules. Adapter assets load only on actual VNAP tag render, not ordinary forms/admin. Shared AddressField owns races/reset/error/retry; the adapter contains no independent HTTP/cache implementation.

Remaining limits: additional builders, third-party popup/async-mail integrations, full Flamingo/CAPTCHA service matrix, broader multisite/external-cache and browser/theme combinations are not certified. Host-inserted forms require their native unique CF7 unit tag/IDs, required assets, native CF7 init and explicit scoped VNAP init; no automatic document observation is claimed. Static defaults are tested; advanced dynamic defaults and full native-text option parity are deferred. Street remains a separate CF7 text field, not part of the shared pair's getAddress result. No manual legacy resolution/mapping, shipping behavior or submission persistence is added.

Technical debt removed: there is now one builder-neutral adapter boundary, canonical code/mail policy, explicit grouping, shared native-control entrypoint and tested conditional CF7 reference adapter instead of per-form fetch/validation duplication. Remaining work is additional native adapters and documented compatibility breadth, not another provider/cache/schema. Recommended Phase 12: **Additional Form Adapters**, after review; not implemented.

Pre-commit checks inspect intended scope, previous history, whitespace, frozen fixture and secrets/artifacts. Only Phase 11 files may be staged. Required local message: `feat(forms): add contact form 7 address integration`. No push/tag/release or Phase 12 work.

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
      contact-form-7.js
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
    contact-form-7.md
    database-schema.md
    dataset-sync.md
    form-integrations.md
    frontend-component.md
    legacy-migration.md
    local-dataset.md
    phase10-validation.md
    phase11-validation.md
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
      Forms/
        AddressFieldConfig.php
        AddressSubmission.php
        ContactForm7/
          ContactForm7Integration.php
          FormTags.php
          MailTags.php
          Renderer.php
          TagGenerator.php
          Validator.php
        Contracts/
          FormAdapterInterface.php
        FormIntegrationRegistry.php
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
    Forms/
      AbsentTest.php
      BrowserTest.mjs
      GeneratorTest.mjs
      HttpTest.mjs
      IntegrationTest.php
      OfflineFixture.php
      Setup.php
      UnavailableTest.php
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
