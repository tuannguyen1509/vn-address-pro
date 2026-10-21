# VN Address Pro

## Overview

WordPress REST API cho địa chỉ Việt Nam hai cấp: Tỉnh/Thành phố → Phường/Xã/Đặc khu. Tác giả: **Tunlamweb** — [Author URI](https://tunlamweb.com). Plugin độc lập theme, không yêu cầu WooCommerce hoặc form plugin.

## Requirements

- PHP 8.1 trở lên.
- WordPress 6.3 trở lên.
- MySQL/MariaDB với InnoDB cho hai bảng địa chỉ và bảng options (để import/metadata rollback cùng nhau).
- Internet không bắt buộc khi activate hoặc lookup local; cần cho remote fallback, kiểm tra/cập nhật dataset hoặc build snapshot.
- mbstring không bắt buộc; nếu thiếu, tìm kiếm fallback về `stripos()` có hạn chế với chữ hoa/thường Unicode.

Các hàm REST, Transients và HTTP đang dùng đã có trước WordPress 6.3; bootstrap không thêm API yêu cầu bản cao hơn. Chọn mức hỗ trợ 6.3 vì WordPress xác nhận hỗ trợ đầy đủ PHP 8.1 từ phiên bản này: [PHP 8 support clarification](https://make.wordpress.org/core/2025/04/09/php-8-support-clarification/). Đây là minimum của plugin, không phải khuyến nghị giữ WordPress cũ.

## Current REST API

| Method | Path tương đối với `rest_url('vn-address/v1/')` |
| --- | --- |
| GET | `provinces?search=Hà` |
| GET | `wards?province_code=79&search=Phường` |
| GET | `province/79` |
| GET | `health` |

Các API công khai, không yêu cầu đăng nhập. Tìm kiếm substring có phân biệt dấu. Provider, timeout 15 giây, cache raw data 24 giờ và cache keys cũ được giữ nguyên. Xem [REST v1 contract](docs/rest-v1-contract.md) cho response, lỗi và các giới hạn hiện tại.

## Installation

1. Đưa toàn bộ thư mục plugin vào `wp-content/plugins/`, hoặc upload ZIP chứa đầy đủ `src/`, `includes/` và hai JSON files trong `data/`.
2. Giữ main file `vn-address-api.php`.
3. Kích hoạt **VN Address Pro** trong trang Plugins.

Không cần Composer hoặc bước cài dependency. Autoloader nội bộ chỉ load namespace `Tunlamweb\VNAddressPro` từ `src/`.

## Upgrade Notes

- Khi nâng cấp bản cũ, giữ **đúng folder đang cài** và file `vn-address-api.php`. Workspace hiện tên `vn-address-pro`; không đổi tên folder của bản đang active chỉ để khớp workspace.
- Không cài đồng thời bản cũ và bản mới như hai plugin riêng. Tên class và REST namespace được giữ để tương thích; không hỗ trợ hai implementation cùng sở hữu routes.
- Tên hiển thị mới không làm đổi REST namespace `vn-address/v1`.
- `/health` cố ý giữ `service: VN Address API 2025+` và `version: 1.0.0` theo contract cũ. Đây là giá trị compatibility của API v1, **không phải version release plugin**. Version plugin lấy từ header qua `VNAP_VERSION`.
- Release dùng `vnap_version`; schema dùng `VNAP_DB_VERSION`/`vnap_db_version`, dataset dùng `vnap_dataset_version` độc lập. Update Phase 3 lên Phase 4 tự cài local trên `init`, không cần reactivate.
- Deactivation và việc xóa plugin không tự xóa bảng địa chỉ hoặc metadata. Chưa có opt-in uninstall cleanup; persistent data được giữ lại.
- Multisite dùng marker riêng từng site, khởi tạo lười khi site chạy `init`; không duyệt toàn network lúc activate.

## Development Status

Phase 7 — WooCommerce Core Integration & Classic Checkout, tiếp tục development của version 2.0.0. Class legacy `VN_Address_API_2025`, constants và toàn bộ public methods vẫn tồn tại dưới dạng facade trong `includes/legacy-api.php`; mọi xử lý delegate sang core mới.

Bootstrap: entrypoint → requirements → autoloader → `Plugin::instance()->boot()` → legacy registration → `vnap_loaded`. Textdomain và version check chạy trên `init`. Hook `vnap_loaded` không có arguments, fire đúng một lần sau khi class/API registration callback đã sẵn sàng; routes thực tế được đăng ký tại `rest_api_init`. Integration muốn nghe hook phải đăng ký trước khi plugin boot; plugin tải sau có thể dùng `did_action('vnap_loaded')`.

Quy ước cho code mới: namespace `Tunlamweb\VNAddressPro`, PHP/options/hooks `vnap_`, CSS `.vnap-`, JavaScript `VNAddressPro`, text domain `vn-address-pro`. Chưa thêm CSS/JS runtime.

Đã có persistent local database, offline normal lookup, manual/scheduled dataset updates, integrity validation, safe repair/recovery và frontend fields dùng lại được. WooCommerce là module tùy chọn với Classic Checkout; chưa có Checkout Block hoặc form integrations. Xem [tests](tests/README.md) và [changelog](CHANGELOG.md).

## WooCommerce Classic Checkout

Module tự bật khi WooCommerce 8.2.0+ active; core vẫn chạy khi Woo không cài hoặc bị deactivate. Đã kiểm thử Woo 8.2.0/PHP 8.1 và Woo 11.2.1/PHP 8.3, với HPOS và legacy order storage. Minimum dựa trên mốc [HPOS ổn định của Woo 8.2](https://developer.woocommerce.com/2023/10/10/woocommerce-8-2-0-released/); không nâng requirements của address core.

Hỗ trợ checkout page cấu hình bằng `[woocommerce_checkout]`, billing/shipping hai cấp, server validation và lưu canonical codes qua Woo CRUD. Province dùng state identifier `VN-{code}`, city giữ tên ward dễ đọc. Lookup checkout dùng local DB, hoạt động khi provider bên ngoài mất kết nối. Non-VN checkout giữ native Woo behavior; postcode/address_2/phone/email không bị xóa.

**Không hỗ trợ Cart/Checkout Blocks hoặc Store API trong phase này.** Existing shipping zones/taxes dùng state keys từ plugin khác cần kiểm tra lại; không có tự động migration. Xem [WooCommerce integration](docs/woocommerce.md), [state code contract](docs/woocommerce-state-codes.md) và [Phase 7 validation](docs/phase7-validation.md).

## Frontend address fields

Thêm `[vn_address_fields]` vào Page/Post, hoặc dùng PHP API trả HTML:

```php
echo vnap_render_address_fields( array( 'show_address' => true ) );
```

Component có native province/ward selects, địa chỉ chi tiết tùy chọn, hidden display names, preselection và nhiều instance độc lập. Province render phía server; wards tải theo tỉnh qua REST v1 cùng website. Assets chỉ enqueue khi render; không cần jQuery, npm build hoặc browser gọi provider bên ngoài. Không có xử lý submit/lưu form. Consumer phải validate lại codes/quan hệ phía server bằng `AddressService::validate_selection()`.

Xem [frontend component](docs/frontend-component.md) cho shortcode args, PHP/JavaScript API, events, asset timing và accessibility. Kết quả: [Phase 6 validation](docs/phase6-validation.md).

## Architecture

REST v1 Controller → AddressService → AddressRepositoryInterface → local-first repository → hai bảng địa chỉ theo `$wpdb->prefix`. Local healthy không gọi remote, không đọc JSON dataset và không cần transient/LKG. Search/sort/Normalizer v1 được giữ nguyên. Chỉ khi local unavailable mới dùng remote adapter với reliability Phase 3; mã không tồn tại trong local healthy trả not-found mà không gọi HTTP.

Core trả `array` hoặc `WP_Error` có mã `vnap_`; REST boundary map về schema/lỗi cũ. Validator giữ default của field optional và cách ép mã v1, đồng thời chặn payload sai cấu trúc/kiểu để tránh warning và TypeError. Normalizer giữ `province_code: 0` khi ward thiếu parent, không tự suy ra mã khác.

`Plugin::instance()->address_service()`, `repository()` và `rest_controller()` là API nội bộ tạm thời. Xem [architecture](docs/architecture.md), [local dataset](docs/local-dataset.md) và [database schema](docs/database-schema.md).

## Provider resilience

Raw payload từ remote và cache được validate trước khi dùng. Chỉ remote data hợp lệ mới được ghi vào active cache và last-known-good (LKG). Các active keys cũ và TTL mặc định 24 giờ giữ nguyên; LKG mặc định 7 ngày, được dùng khi refresh lỗi hoặc đang bị lock/backoff. Không thêm field báo stale vào REST v1.

Refresh có lease lock trong bảng options sẵn có, negative cache ngắn cho detail HTTP 404 và failure marker cho lỗi tạm thời. Retry tối đa một lần, chỉ khi lỗi transient đầu tiên kết thúc dưới 0,5 giây và chưa có LKG. Không retry đồng bộ 429 hoặc timeout kéo dài; không sleep trong request.

Remote fallback vẫn dùng cache có thể bị evict sớm; local repository là nguồn persistent độc lập. Settings → VN Address có bốn tab General, Data, Tools và System Status. Automatic Dataset Updates mặc định Disabled; có thể chọn Daily, Weekly hoặc Monthly (30 ngày). Sync chỉ chạy qua admin action hoặc scheduled job, không chạy trong lookup frontend. Snapshot bundle vẫn được đóng gói theo release; sync cập nhật local DB mà không sửa file bundle.

TTL filters và diagnostics: [cache-reliability.md](docs/cache-reliability.md). Vận hành: [dataset sync](docs/dataset-sync.md), [admin settings](docs/admin-settings.md). Kết quả hiện tại: [Phase 5 validation](docs/phase5-validation.md).

Future Migration Recommendation: chỉ cân nhắc đổi folder/main filename khi có kế hoạch migration đã kiểm chứng bảo toàn activation identity. Phase 1 không rename.
