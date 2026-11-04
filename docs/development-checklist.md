# VN Address Pro — Checklist khi tiếp tục phát triển

> **Dự án đang tạm ngưng code theo yêu cầu của tác giả.**
> Ngày ghi chú: **27/10/2026**. Mốc hiện tại: **hoàn thành Phase 12**.
> File này là bàn giao để đọc khi quay lại; không phải yêu cầu tự động triển khai phase tiếp theo.

> Cập nhật 04/11/2026: đã tiếp tục theo yêu cầu của tác giả, bổ sung công cụ chuyển mã tỉnh trong cấu hình vận chuyển/cửa hàng theo scan → preview → selected apply. Xem [phạm vi, giới hạn và kiểm thử riêng](shipping-configuration.md). Các mốc Phase 12 bên dưới là ghi nhận lịch sử, không phải trạng thái của thay đổi mới này. Carrier mapping và historical crosswalk vẫn chưa triển khai.

## 1. Đọc nhanh trước khi code lại

- Plugin: **VN Address Pro**, tác giả **Tunlamweb**.
- Version: **2.0.0 — Unreleased**, chưa coi là bản đã phát hành.
- Main entrypoint vẫn là `vn-address-api.php`.
- Commit chức năng cuối: **`950173fa06fb0d6ea69e9c9974da4e748391ef7b`**.
- Commit message: `feat(forms): add fluent forms address integration`.
- Phase 1–12 đã hoàn thành theo các báo cáo validation trong `docs/`.
- Bước tiếp theo đã được đề xuất: **Phase 13 — WPForms Adapter**. Chưa có implementation và chưa có đặc tả Phase 13 được chốt trong source.
- Khi lập ghi chú này: working tree ban đầu sạch; chỉ đọc source/tài liệu, không chạy lại test và không sửa logic PHP/JS.
- Kết quả PASS gần nhất được ghi nhận ngày **22/10/2026**. Không hiểu các kết quả cũ là đã kiểm thử với những phiên bản dependency mới hơn sau ngày đó.

### Quy ước checkbox

- `[x]`: đã có implementation hoặc đã được xác nhận trong báo cáo tương ứng.
- `[ ]`: chưa hoàn thành, chưa được chứng nhận hoặc cần thực hiện khi tiếp tục.
- Các mục **mở rộng tùy chọn** không bắt buộc phải làm hết trước khi release; cần chốt phạm vi release trước.

## 2. Những phần đã làm — không triển khai lại từ đầu

| Phase | Trạng thái | Nội dung đã có | Tài liệu |
| --- | --- | --- | --- |
| 1 | [x] | Metadata, namespace, requirements, bootstrap, lifecycle/version foundation, bảo toàn REST v1 | [Validation](phase1-validation.md) |
| 2 | [x] | Tách Controller, Service, Repository, Provider, Cache, HTTP; legacy facade | [Architecture](architecture.md), [validation](phase2-validation.md) |
| 3 | [x] | Provider validation, LKG, refresh lock, negative cache, backoff/retry có giới hạn | [Reliability](cache-reliability.md), [validation](phase3-validation.md) |
| 4 | [x] | Bundled dataset/manifest, local database, indexed repository, local-first/offline lookup | [Local dataset](local-dataset.md), [validation](phase4-validation.md) |
| 5 | [x] | Dataset check/sync, admin status, repair/reinstall, optional cron, integrity/atomic import | [Sync](dataset-sync.md), [validation](phase5-validation.md) |
| 6 | [x] | Reusable frontend component, shortcode/PHP API, shared client, reset/cache/race protection | [Frontend](frontend-component.md), [validation](phase6-validation.md) |
| 7 | [x] | WooCommerce Classic Checkout, canonical codes, server validation, CRUD persistence | [WooCommerce](woocommerce.md), [validation](phase7-validation.md) |
| 8 | [x] | Checkout Block, Store API, billing/shipping độc lập, cùng canonical metadata | [Blocks](woocommerce-blocks.md), [validation](phase8-validation.md) |
| 9 | [x] | My Account và Order Admin; giữ snapshot đơn cũ, account/order không ghi chéo | [Account](woocommerce-my-account.md), [Order Admin](woocommerce-order-admin.md), [validation](phase9-validation.md) |
| 10 | [x] | Normalization phục vụ so sánh, matching có confidence, scan/dry-run, selected apply, audit | [Normalization](address-normalization.md), [migration](legacy-migration.md), [validation](phase10-validation.md) |
| 11 | [x] | Form Integration Foundation và Contact Form 7 adapter | [Forms](form-integrations.md), [CF7](contact-form-7.md), [validation](phase11-validation.md) |
| 12 | [x] | Fluent Forms native Province/Ward fields, validation, entry/notification formatting, shared lifecycle | [Fluent Forms](fluent-forms.md), [validation](phase12-validation.md) |

**Lưu ý Phase 10:** có migration foundation và apply thật, nhưng **chưa có rollback execution/UI**, manual resolution hay historical mapping đã xác minh. Không đánh đồng “Phase 10 hoàn thành” với “toàn bộ bài toán migration đã hoàn thành”.

## 3. Việc đầu tiên khi quay lại

- [ ] Đọc file này, [README](../README.md), [architecture](architecture.md) và [Phase 12 validation](phase12-validation.md).
- [ ] Chạy `git status --short` và `git log -12 --oneline`; kiểm tra có thay đổi/commit mới sau mốc ghi chú không.
- [ ] Chọn **một mục tiêu cụ thể**: tiếp tục WPForms, hoàn thiện migration, hay chuẩn bị release. Không gom tất cả backlog vào một lần sửa.
- [ ] Đọc source module sẽ sửa và API chính thức của dependency tại thời điểm quay lại; xác định minimum/latest tested mới bằng chứng thực tế.
- [ ] Dựng WordPress/database dùng riêng cho test; cài plugin bản chính thức, chỉ dùng dữ liệu giả.
- [ ] Tạo lại môi trường browser test theo [tests/README.md](../tests/README.md). Container/database kiểm thử Phase 12 đã được dọn; không giả định môi trường cũ còn tồn tại.
- [ ] Chạy baseline liên quan trước khi sửa, ghi rõ lỗi có sẵn và lỗi mới.
- [ ] Sau khi sửa: cập nhật docs/changelog/validation, review diff, chỉ commit phạm vi đã PASS. Push/release là quyết định riêng.

## 4. Ưu tiên tiếp theo: WPForms Adapter — chưa viết

Đây là hướng tiếp theo đã đề xuất ở cuối Phase 12. Cần chốt prompt/acceptance criteria trước khi implementation.

- [ ] Review API/source chính thức WPForms, phân biệt khả năng **Lite/Pro**, custom fields, builder, validation, entry và notification.
- [ ] Xác nhận capability gate: có thể làm native Province/Ward field bằng API được hỗ trợ hay không. Nếu không thể, ghi limitation trước khi chọn giải pháp.
- [ ] Xác định version requirements từ API thực tế và test; WPForms phải là dependency tùy chọn.
- [ ] Thêm adapter riêng vào FormIntegrationRegistry; không thay core thành phụ thuộc WPForms.
- [ ] Dùng canonical Province/Ward codes, explicit Province reference, nhiều form/group độc lập.
- [ ] Reuse AddressService và shared AddressDataClient/frontend component; không tạo REST client, cache hoặc dataset riêng.
- [ ] Server validation: scalar/array attack, unknown code, mismatch, required/optional, cấu hình sai và native inactive-field semantics.
- [ ] Khảo sát entry/email formatter và native smart tags; giữ code nội bộ, resolve tên chuẩn phía server.
- [ ] Xử lý lifecycle, reset, validation failure và race qua cơ chế chính thức; assets chỉ tải khi cần.
- [ ] Test WPForms absent/unsupported, builder save/reload, submit/entry/email, offline provider calls = 0.
- [ ] Test cùng CF7 + Fluent Forms + shortcode và rerun các regression hiện có.
- [ ] Viết tài liệu hỗ trợ/version/giới hạn và báo cáo validation của phase mới.

## 5. Chức năng khác chưa implement

Các mục dưới đây là backlog đã được nhắc đến, **chưa có adapter/module hoàn chỉnh**. Thứ tự sau WPForms cần quyết định lại; không tự gán số phase.

### 5.1. Form integrations — mở rộng tùy chọn

- [ ] **Elementor Forms Adapter**: review official APIs, capability/version gate, field model, validation/storage/display và lifecycle.
- [ ] **Gravity Forms Adapter**: review official APIs và triển khai theo foundation dùng chung.
- [ ] Với mỗi adapter: native extension API, explicit grouping, server validation, canonical codes và kiểm thử coexistence/offline.
- [ ] Chỉ quảng cáo adapter sau khi implementation và acceptance tests PASS.

### 5.2. Shipping provider mapping — mở rộng tùy chọn

- [ ] Thiết kế module mapping riêng; canonical code của plugin không bị thay bằng code riêng của hãng vận chuyển.
- [ ] Chọn provider cụ thể khi có yêu cầu; review official API/schema và quy tắc version/provenance của mapping.
- [ ] Xử lý unmapped/ambiguous/obsolete codes bằng kết quả rõ ràng; không tự đoán.
- [ ] Test billing/shipping, checkout và đơn cũ; không ghi đè snapshot ngoài phạm vi được chọn.
- [ ] Không gắn core vào một shipping company hoặc cấu hình riêng của một website.

### 5.3. Stable developer API — chưa chốt

- [ ] Thiết kế/document public PHP facade và các extension points muốn cam kết lâu dài.
- [ ] Phân biệt public API với internal services; chốt signatures, errors và compatibility policy.
- [ ] Nếu thực sự cần provider switcher/public sync SDK: thiết kế và test riêng, không expose tùy tiện URL từ input.

Hiện `Plugin::instance()->address_service()`, `repository()` và `rest_controller()` vẫn là **provisional internal accessors**, chưa phải SDK ổn định.

## 6. Migration/normalization: phần còn thiếu

Đọc [legacy-migration.md](legacy-migration.md) trước khi sửa. Các giới hạn sau là thiết kế có chủ đích, không phải lỗi để bỏ qua kiểm tra an toàn.

### 6.1. Rollback execution/UI — đã có journal foundation, chưa có chức năng rollback

- [ ] Thiết kế preview rollback từ before/expected-after journal.
- [ ] Chỉ cho rollback khi dữ liệu hiện tại vẫn khớp after-values; không ghi đè thay đổi mới của người dùng.
- [ ] Bảo toàn sự khác biệt giữa metadata không tồn tại và metadata có giá trị rỗng.
- [ ] Dùng Woo CRUD, capability/nonce, lease và verification tương ứng.
- [ ] Có xử lý riêng PREPARED/FAILED_SAVE/partial failure; không suy ra record chưa thay đổi chỉ từ status.
- [ ] UI xác nhận rõ ràng, báo cáo kết quả và tests rollback/replay/stale/audit failure.

### 6.2. Manual resolution — chưa có

- [ ] UI cho admin chọn Province/Ward khi record ambiguous/unresolved.
- [ ] Preview lại và validate cặp code bằng local dataset trước apply.
- [ ] Audit quyết định thủ công; không biến candidate thấp thành auto-apply.

### 6.3. Historical/legacy mappings — chưa có nguồn đã xác minh

- [ ] Thu thập mapping có nguồn, version và lịch sử thay đổi đáng tin cậy.
- [ ] Phân biệt đổi tên, đổi mã, sáp nhập và thay đổi ranh giới; có thể có quan hệ một-nhiều/nhiều-một.
- [ ] Chỉ thêm strategy `LEGACY_MAPPING` sau khi có dữ liệu xác minh và bộ test.
- [ ] Mapping state keys/shipping zones từ extension khác phải có bước review riêng; hiện chưa tự động chuyển.

### 6.4. Các mở rộng migration khác — tùy nhu cầu

- [ ] CSV/export báo cáo migration với capability, escaping và giới hạn dữ liệu cá nhân phù hợp.
- [ ] Queue/background workers nếu cần migration lớn; giữ idempotency, stale checks, lease và resume/recovery.
- [ ] Nghiên cứu phối hợp với external customer/order writers nếu cần bảo đảm mạnh hơn optimistic concurrency hiện tại.
- [ ] Fuzzy matching chỉ xem xét như đề xuất có review, nếu cần; chưa có scorer và không được tự động apply confidence thấp.
- [ ] Khi thay đổi ngữ nghĩa matching/plan: tăng revision `PlanCodec::VERSION` để vô hiệu preview cũ.

## 7. Dataset/provider: phần chưa giải quyết đầy đủ

- [ ] Bổ sung nguồn **trusted/versioned/authenticated manifest** nếu có nguồn phù hợp.
- [ ] Xác minh completeness/freshness/rollback của upstream bằng bằng chứng từ nguồn; không hard-code tổng số đơn vị hành chính để thay thế manifest.
- [ ] Nếu upstream hỗ trợ: cân nhắc metadata-only update check. Hiện Check for Updates phải tải và validate payload đầy đủ.
- [ ] Cập nhật bundled snapshot khi chuẩn bị release từ nguồn được kiểm chứng, qua `tools/build-dataset.php` và validator; giữ dataset version độc lập plugin version.
- [ ] Kiểm thử dữ liệu version mới xuyên suốt repository → REST → frontend → Woo/forms, bao gồm obsolete codes.

**Giới hạn hiện tại:** provider remote không công bố manifest/count/version đã xác thực; package version cập nhật dùng thời điểm quan sát UTC tại local. Payload HTTP 200 có cấu trúc hợp lệ nhưng thiếu hẳn một Province chưa thể được chứng minh là thiếu từ response đơn lẻ. Remote single-Ward lookup cũng chưa có contract; không scan/tải mọi Province để giả lập.

## 8. Đã có chức năng nhưng cần mở rộng kiểm thử

Không đánh dấu các mục này là “chưa code module”. Implementation chính đã có; phần chưa hoàn thành là phạm vi chứng nhận/test.

### 8.1. Fluent Forms

- [ ] Chứng nhận Pro/conversational/repeater khi có nhu cầu và API phù hợp.
- [ ] Test popup/dynamic insertion ngoài lifecycle native với integration cụ thể; không thêm global polling/observer để đoán mọi popup.
- [ ] Tự động hóa export file → import UI round trip; hiện đã test native duplication/import service và render form import.
- [ ] Kiểm tra CSV download thực tế, formatted names và native `.value` export columns ở các version hỗ trợ.
- [ ] Mở rộng test rename/duplicate từng field và việc cập nhật reference; hiện đổi tên Province cần sửa reference thủ công.

### 8.2. Contact Form 7

- [ ] Mở rộng matrix với Flamingo, CAPTCHA/spam/acceptance/mail-failure và các add-on/dịch vụ cụ thể nếu muốn công bố hỗ trợ.
- [ ] Test host-inserted/popup forms trong môi trường integration thực tế; giữ scoped init và native lifecycle.

### 8.3. WooCommerce

- [ ] Chạy đầy đủ browser matrix với **legacy order storage**; Phase 12 chạy PHP cả HPOS/legacy nhưng full browser chạy HPOS.
- [ ] Chứng nhận gateway/express wallet thực tế khi có yêu cầu. Hiện COD/BACS và express-button coexistence không tương đương payment-network certification.
- [ ] Test external checkout replacements/field editors và shipping extensions theo từng module cụ thể.
- [ ] Phân biệt warning upstream Woo cũ với lỗi mới của plugin; không sửa vendor hay tắt debug để làm test PASS.

### 8.4. Hạ tầng/runtime

- [ ] Full multisite matrix: network activation, site mới, per-site dataset/options, scheduling và lifecycle.
- [ ] Stress với persistent external object cache, eviction và concurrent requests/workers.
- [ ] Chạy lại supported WordPress/PHP/Woo/form-plugin matrix tại thời điểm tiếp tục; kiểm tra cả dependency absent/unsupported.
- [ ] Kiểm tra concurrency/lease recovery và dataset sync trên hạ tầng giống môi trường phát hành.

## 9. Checklist trước khi phát hành 2.0.0

Đây là **release gate cần rà/chốt**, không khẳng định mọi mục dưới đây đều thiếu hoàn toàn.

- [ ] Chốt scope release: có thể phát hành các module đã PASS mà không cần đợi mọi adapter trong backlog.
- [ ] Rà requirements và tested versions; kiểm tra API/deprecation mới qua nguồn chính thức.
- [ ] Rà tất cả docs để bỏ câu trạng thái cũ/mâu thuẫn; không sửa báo cáo validation lịch sử thành kết quả mới.
- [ ] Chốt data retention/uninstall policy. Hiện deactivate/uninstall giữ persistent data; chưa có opt-in cleanup. Chỉ implement cleanup nếu đã thống nhất, không xóa dữ liệu tự động.
- [ ] Rà translation coverage/textdomain và accessibility của các luồng sẽ phát hành.
- [ ] Rà security/capability/nonce, input arrays, escaping, logs/PII và dependency isolation của diff release.
- [ ] Build ZIP tự chứa source/autoload và dataset/manifest cần thiết; không yêu cầu người dùng chạy Composer/npm.
- [ ] Loại secrets, logs, temp WP/DB, downloaded plugins, mail dumps, browser profiles và node_modules khỏi ZIP/repository.
- [ ] Test install ZIP sạch, upgrade từ bản cũ, activate → deactivate → activate, giữ đúng entrypoint/folder identity.
- [ ] Chạy regression PHP/REST/browser tương ứng phạm vi release; kiểm tra WP_DEBUG và console.
- [ ] Cập nhật CHANGELOG/version/release notes theo những gì thực sự phát hành. Chưa tự bump version chỉ vì thêm ghi chú này.
- [ ] Commit khi PASS; push/tag/publish chỉ khi có yêu cầu riêng.

## 10. Những contract phải giữ khi tiếp tục

- [x] Core độc lập theme; Woo và form plugins là integration tùy chọn.
- [x] Address model hai cấp **Province → Ward**, không đưa District trở lại core hiện tại.
- [x] REST namespace `vn-address/v1`: `GET /provinces`, `/wards`, `/province/{code}`, `/health`.
- [x] Giữ parameters, schemas, error mapping, search/sort và legacy public signatures của v1.
- [x] Không sửa fixture chỉ để làm regression PASS: `tests/Contract/fixtures/rest-v1.json`.
- [x] `/health` có tên/version legacy có chủ đích; không “dọn tên cũ” bằng cách phá contract.
- [x] Canonical codes là giá trị lưu/submit; display names resolve phía server, không tin browser labels.
- [x] Healthy local dataset → không gọi remote provider; không preload toàn bộ Ward lên frontend.
- [x] Shared AddressService/client/lifecycle; không duplicate validation/provider/cache cho mỗi adapter.
- [x] Account edit không sửa đơn cũ; order edit không sửa địa chỉ customer; migration phải explicit preview/apply.
- [x] Namespace `Tunlamweb\VNAddressPro`, prefix `vnap_`, CSS `.vnap-`, JS `VNAddressPro`, text domain `vn-address-pro`.
- [x] Không hard-code website/domain/theme/store/shipping/payment/form cụ thể trong core.

REST fixture SHA-256 ở mốc Phase 12:

```text
436c9637f0444bacc719beab0057bdcbe8a5dc49af0ce4ea8a979b792bce5e62
```

## 11. Bản đồ source để tìm đúng chỗ sửa

| Mục tiêu | Source/tài liệu bắt đầu đọc |
| --- | --- |
| Bootstrap/version/requirements | `vn-address-api.php`, `src/Plugin.php`, `src/Support/` |
| REST contract | `src/Rest/`, `docs/rest-v1-contract.md`, `tests/Contract/` |
| Address service/normalization v1 | `src/Address/` |
| Local/remote lookup | `src/Repository/`, `src/Contracts/`, `src/Providers/`, `src/Cache/`, `src/Http/` |
| Dataset install/sync/admin | `src/Data/`, `src/Database/`, `src/Sync/`, `src/Admin/`, `data/`, `tools/build-dataset.php` |
| Shared frontend | `src/Frontend/`, `assets/js/address-data-client.js`, `assets/js/vn-address.js` |
| Thêm form adapter | `src/Integrations/Forms/Contracts/`, `FormIntegrationRegistry.php`, adapters `ContactForm7/` và `FluentForms/` |
| Woo Classic/Block/account/admin | `src/Integrations/WooCommerce/` và các submodules |
| Migration/rollback foundation | `src/Migration/`, `docs/legacy-migration.md`, `docs/address-normalization.md` |
| Test setup/lệnh chạy | `tests/README.md`, các `docs/phase*-validation.md` |

## 12. Prompt gợi ý khi quay lại

```text
Tôi tiếp tục dự án VN Address Pro đang tạm ngưng sau Phase 12.

Đọc docs/development-checklist.md, README.md, docs/architecture.md,
docs/phase12-validation.md và source liên quan trước khi sửa.
Kiểm tra git status/log để xác định trạng thái thực tế mới nhất.

Mục tiêu lần này: [điền một mục checklist hoặc gửi prompt Phase 13].

Không triển khai lại Phase 1–12, không tự mở rộng sang các backlog khác.
Giữ REST v1/legacy compatibility và canonical Province/Ward contract.
Dựng môi trường test dùng riêng, không dùng dữ liệu khách hàng thật.
Kiểm thử trước/sau, cập nhật checklist và báo cáo rõ phần còn thiếu.
Không push/tag/release nếu chưa có yêu cầu riêng.
```

## 13. Cách cập nhật ghi chú về sau

- Khi xong một mục, tick checkbox và ghi commit + báo cáo validation tương ứng.
- Nếu chỉ mới có implementation mà test chưa đủ, giữ mục kiểm thử ở trạng thái chưa xong.
- Nếu đổi scope/version requirements, sửa phần “Đọc nhanh” và tài liệu module, giữ nguyên lịch sử test cũ.
- Khi có phiên bản phát hành thật, cập nhật mốc Unreleased và release notes theo kết quả thực tế.
