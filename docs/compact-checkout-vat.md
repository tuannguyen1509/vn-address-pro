# Checkout thu gọn, VAT và tùy chọn CSS — 2.0.7

Vào **Cài đặt → VN Address → Thiết lập → Giao diện và thanh toán**. Có ba tùy chọn độc lập:

- **Sử dụng CSS của plugin cho checkout và các form:** mặc định tắt. Chỉ khi được chọn, plugin mới tải hai stylesheet bố cục frontend. CSS quản trị không bị ảnh hưởng. Chức năng địa chỉ và VAT không phụ thuộc tùy chọn này.
- **Thu gọn checkout và gom cụm địa chỉ:** mặc định bật. Checkout thường giữ tên/họ, email, điện thoại theo yêu cầu gốc của cửa hàng; gom quốc gia, tỉnh, phường và số nhà vào nhóm Địa chỉ. Mã bưu điện, căn hộ và ghi chú vẫn mở được khi cần. Mặc định dùng chung địa chỉ thanh toán/giao hàng; khách có thể chọn địa chỉ giao hàng khác.
- **Cho phép khách thêm thông tin VAT:** mặc định bật. Chưa chọn VAT thì không hiển thị các ô hóa đơn; khi chọn, cần tên đơn vị, mã số thuế, địa chỉ xuất hóa đơn. Email nhận hóa đơn không bắt buộc. Đây là yêu cầu hóa đơn, không tự phát hành hóa đơn hay xác minh pháp lý mã số thuế.

## Checkout Block

Plugin giữ nhóm địa chỉ gốc của WooCommerce, không di chuyển các node React. Trong chế độ thu gọn, các trường căn hộ và mã bưu điện tùy chọn của Việt Nam được thu gọn theo locale. Nếu có vùng giao hàng dùng mã bưu điện, trường mã bưu điện được giữ. Quy tắc địa chỉ quốc tế không bị thay bằng quy tắc Việt Nam.

VAT sử dụng [Additional Checkout Fields API và điều kiện gốc của WooCommerce](https://developer.woocommerce.com/docs/block-development/cart-and-checkout-blocks/how-to-conditional-additional-fields/), cần **WooCommerce 9.9+** cho Checkout Block. Trên bản cũ hơn, VAT vẫn dùng được ở checkout thường; VAT của Block không đăng ký để tránh API không hỗ trợ. Nếu trang Block thiếu khối thông tin bổ sung, plugin thêm khối gốc vào bản render hiện tại, không ghi sửa nội dung trang trong database.

## Dữ liệu hóa đơn

Thông tin VAT được lưu theo đơn bằng metadata `_vnap_vat_requested`, `_vnap_vat_company`, `_vnap_vat_tax_id`, `_vnap_vat_address`, `_vnap_vat_email` và `_vnap_vat_source`. Block còn dùng metadata Additional Fields do WooCommerce quản lý. Địa chỉ giao hàng/thanh toán gốc không bị thay bằng địa chỉ hóa đơn.

Không chọn VAT thì bỏ qua dữ liệu VAT cũ trong lượt gửi đó. Đơn hàng đã có không được quét hay sửa khi bật/tắt tùy chọn. VAT hiển thị trong quản trị, chi tiết đơn và email theo luồng Classic/native Block tương ứng. Nếu dùng plugin hóa đơn riêng, có thể tắt VAT của VN Address Pro để tránh hai bộ trường yêu cầu hóa đơn.

## Theme tự code, Flatsome và theme khác

- Dùng WooCommerce gốc: giữ template/hook checkout và ID/name trường chuẩn. Plugin chỉ nhóm lại các node Classic hiện có, không sao chép template của theme hoặc sửa giá, phí, cổng thanh toán.
- Theme tự thiết kế CSS: để **CSS plugin tắt**, rồi định dạng `.vnap-checkout-address`, `.vnap-address-details`, `.vnap-vat`, `[data-vnap-vat-fields]` và `.vnap-block-ward` bằng theme. Trạng thái ẩn của trường kỹ thuật và VAT vẫn được xử lý độc lập.
- Theme thay thế toàn bộ checkout hoặc có field editor riêng: có thể tắt **Thu gọn checkout**, vẫn giữ chọn tỉnh/phường và VAT. Nếu theme bỏ hook `woocommerce_after_order_notes`, cần khôi phục hook này hoặc viết adapter trước khi dùng VAT Classic.
- Flatsome: dùng cơ chế tương thích checkout Classic chuẩn, không dò tên theme hoặc hard-code phiên bản. Chưa chứng nhận bản Flatsome cụ thể vì chưa có mã theme trong môi trường kiểm thử. Không thể bảo đảm mọi theme/plugin checkout tùy biến.

Các bộ lọc cho lập trình viên, phải trả về boolean:

```php
add_filter( 'vnap_checkout_css', '__return_false' );
// Tắt riêng phần gom/thu gọn khi theme đã có cách trình bày riêng.
add_filter( 'vnap_checkout_compact', '__return_false' );
// Tắt bộ trường yêu cầu VAT nếu đã dùng giải pháp hóa đơn khác.
add_filter( 'vnap_checkout_vat', '__return_false' );
```

## Kiểm tra

WordPress 7.1.3, WooCommerce 11.2.1, PHP 8.3, theme N.K.Luck child, database và thông tin giả. Đã kiểm tra đặt đơn cơ bản không VAT/CSS tắt, VAT Classic, VAT Block, nhập VAT rồi bỏ chọn. Metadata VAT và Additional Fields không giữ nội dung hóa đơn ở đơn đã bỏ chọn VAT. Các lượt gửi email được chặn trong QA.

Bộ kiểm tra: 25 trường hợp CSS/VAT/thu gọn; hồi quy Classic 47, Block 23, quản trị/tài khoản 43, frontend 32, phạm vi CSS 7 và REST v1 18. Các bài test cũ về stylesheet bật CSS rõ ràng; mặc định CSS tắt được kiểm tra riêng. Ảnh desktop/390px dùng dữ liệu giả. Theme N.K.Luck có cảnh báo module `website.js` riêng; không sửa vendor/theme trong đợt này.
