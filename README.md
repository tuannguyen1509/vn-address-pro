# VN Address Pro

## Overview

WordPress REST API cho địa chỉ Việt Nam hai cấp: Tỉnh/Thành phố → Phường/Xã/Đặc khu. Tác giả: **Tunlamweb** — [Author URI](https://tunlamweb.com). Plugin độc lập theme, không yêu cầu WooCommerce hoặc form plugin.

## Requirements

- PHP 8.1 trở lên.
- WordPress 6.3 trở lên.
- WordPress HTTP API có thể kết nối HTTPS tới Province Open API v2 khi cache chưa có.
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

1. Đưa toàn bộ thư mục plugin vào `wp-content/plugins/`, hoặc upload ZIP chứa một thư mục plugin với đầy đủ `src/` và `includes/`.
2. Giữ main file `vn-address-api.php`.
3. Kích hoạt **VN Address Pro** trong trang Plugins.

Không cần Composer hoặc bước cài dependency. Autoloader nội bộ chỉ load namespace `Tunlamweb\VNAddressPro` từ `src/`.

## Upgrade Notes

- Khi nâng cấp bản cũ, giữ **đúng folder đang cài** và file `vn-address-api.php`. Workspace hiện tên `vn-address-pro`; không đổi tên folder của bản đang active chỉ để khớp workspace.
- Không cài đồng thời bản cũ và bản mới như hai plugin riêng. Tên class và REST namespace được giữ để tương thích; không hỗ trợ hai implementation cùng sở hữu routes.
- Tên hiển thị mới không làm đổi REST namespace `vn-address/v1`.
- `/health` cố ý giữ `service: VN Address API 2025+` và `version: 1.0.0` theo contract cũ. Đây là giá trị compatibility của API v1, **không phải version release plugin**. Version plugin lấy từ header qua `VNAP_VERSION`.
- Option `vnap_version` được khởi tạo khi activate hoặc khi bản đã active được nâng cấp; không thêm schema/database riêng. Không ghi lại option khi version không đổi và không hạ marker của bản mới hơn.
- Deactivation giữ nguyên options và cache. Chưa có uninstall handler hoặc chính sách xóa dữ liệu mới.
- Multisite dùng marker riêng từng site, khởi tạo lười khi site chạy `init`; không duyệt toàn network lúc activate.

## Development Status

Phase 3 — Provider Reliability & Cache Resilience, tiếp tục development của version 2.0.0. Class legacy `VN_Address_API_2025`, constants và toàn bộ public methods vẫn tồn tại dưới dạng facade trong `includes/legacy-api.php`; mọi xử lý delegate sang core mới.

Bootstrap: entrypoint → requirements → autoloader → `Plugin::instance()->boot()` → legacy registration → `vnap_loaded`. Textdomain và version check chạy trên `init`. Hook `vnap_loaded` không có arguments, fire đúng một lần sau khi class/API registration callback đã sẵn sàng; routes thực tế được đăng ký tại `rest_api_init`. Integration muốn nghe hook phải đăng ký trước khi plugin boot; plugin tải sau có thể dùng `did_action('vnap_loaded')`.

Quy ước cho code mới: namespace `Tunlamweb\VNAddressPro`, PHP/options/hooks `vnap_`, CSS `.vnap-`, JavaScript `VNAddressPro`, text domain `vn-address-pro`. Chưa thêm CSS/JS runtime.

Chưa triển khai WooCommerce, form integrations, custom database, local dataset hoặc data sync. Xem [tests](tests/README.md) và [changelog](CHANGELOG.md).

## Architecture

REST v1 Controller → AddressService → ProvinceOpenApiProvider → Transients cache / HttpClient. Normalizer, Validator và Search được tách riêng. Provider interface cho phép thay dependency trong code hoặc test; chưa có UI/chức năng chuyển provider. HttpClient không biết URL nguồn cụ thể; chỉ provider dùng URL upstream cố định, không nhận URL từ request công khai.

Core trả `array` hoặc `WP_Error` có mã `vnap_`; REST boundary map về schema/lỗi cũ. Validator giữ default của field optional và cách ép mã v1, đồng thời chặn payload sai cấu trúc/kiểu để tránh warning và TypeError. Normalizer giữ `province_code: 0` khi ward thiếu parent, không tự suy ra mã khác.

`Plugin::instance()->address_service()` và `rest_controller()` là API nội bộ tạm thời, chưa có cam kết ổn định như developer facade chính thức. Xem [architecture](docs/architecture.md).

## Provider resilience

Raw payload từ remote và cache được validate trước khi dùng. Chỉ remote data hợp lệ mới được ghi vào active cache và last-known-good (LKG). Các active keys cũ và TTL mặc định 24 giờ giữ nguyên; LKG mặc định 7 ngày, được dùng khi refresh lỗi hoặc đang bị lock/backoff. Không thêm field báo stale vào REST v1.

Refresh có lease lock trong bảng options sẵn có, negative cache ngắn cho detail HTTP 404 và failure marker cho lỗi tạm thời. Retry tối đa một lần, chỉ khi lỗi transient đầu tiên kết thúc dưới 0,5 giây và chưa có LKG. Không retry đồng bộ 429 hoặc timeout kéo dài; không sleep trong request.

Đây là fallback bằng cache, **không phải offline database**. Transients có thể bị evict sớm; nếu không còn cache/LKG và nguồn ngoài lỗi thì API trả lỗi legacy. Không có cron refresh, sync engine hoặc admin settings mới.

TTL filters, diagnostics và giới hạn: [cache-reliability.md](docs/cache-reliability.md). Kết quả: [Phase 3 validation](docs/phase3-validation.md).

Future Migration Recommendation: chỉ cân nhắc đổi folder/main filename khi có kế hoạch migration đã kiểm chứng bảo toàn activation identity. Phase 1 không rename.
