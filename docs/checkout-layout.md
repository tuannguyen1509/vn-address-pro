# CSS trang thanh toán WooCommerce

**Từ 2.0.7:** CSS frontend mặc định tắt. Bật tại Cài đặt → VN Address → Thiết lập → Sử dụng CSS của plugin. Xem [checkout thu gọn và VAT](compact-checkout-vat.md).

Từ 2.0.6, `assets/css/woocommerce-checkout.css` cung cấp bố cục mặc định cho checkout thường và Checkout Block. CSS kế thừa font và màu từ theme, giữ kiểu nút thanh toán gốc và không đổi cách tính phí, thuế, thanh toán hay dữ liệu địa chỉ.

- Checkout thường: hỗ trợ khoảng cách trường, xuống hàng trên màn hình nhỏ, chiều rộng bộ chọn phường và ẩn vùng mã kỹ thuật không cần hiển thị.
- Checkout Block: trường phường có chiều rộng, khoảng cách và vùng bấm phù hợp nhóm địa chỉ; giữ vị trí tích hợp mà WooCommerce cung cấp.
- Stylesheet chỉ tải ở checkout thường hoặc khi Woo thực sự render Checkout Block. Không tải ở giỏ hàng, tài khoản, cửa hàng hoặc trang xác nhận đơn thông thường.

Các quy tắc bố cục dùng `:where()` để người dùng dễ ghi đè. Không dùng `!important`. Riêng quy tắc giữ phần tử `hidden` trong bộ địa chỉ thường có độ ưu tiên cao hơn để theme không vô tình làm hiện trường kỹ thuật.

## CSS bổ sung

```css
/* Khoảng cách trong bộ chọn phường của Checkout Block. */
.vnap-block-ward {
    gap: 12px;
}

/* Ví dụ tùy chỉnh riêng ô phường, không đổi các select khác. */
.vnap-block-ward .vnap-select {
    border-radius: 8px;
}

/* Khoảng cách giữa các trường checkout thường. */
.woocommerce-checkout form.checkout {
    --vnap-checkout-gap: 12px;
}
```

Theme có quy tắc riêng vẫn có thể ưu tiên cao hơn các mặc định này. Không có stylesheet nào bảo đảm phù hợp mọi theme tùy biến; thiết kế ở đây giữ phạm vi hẹp, kế thừa giao diện hiện tại và cho phép CSS của website điều chỉnh.

## Kiểm tra 17/11/2026

- WooCommerce 11.2.1, WordPress 7.1.3, PHP 8.3; theme N.K.Luck child.
- 47 kiểm tra tích hợp checkout thường, 23 kiểm tra Block và 7 kiểm tra phạm vi stylesheet đạt trên database riêng.
- Desktop và 390px: không tràn ngang, chọn tỉnh/phường hoạt động; trường mã ẩn giữ đúng trạng thái dù theme định dạng mọi select.
- CSS bổ sung `.vnap-block-ward { gap: 22px; }` ghi đè thành công, không dùng `!important`.
- Ảnh được chụp với sản phẩm, địa chỉ, phí giao hàng và thuế giả trên QA. Không thay đổi cấu hình của cửa hàng 8080.
- Trình duyệt ghi nhận cảnh báo có sẵn từ module `website.js` của theme N.K.Luck; không sửa theme trong đợt CSS này. Đây không phải chứng nhận mọi theme/plugin kết hợp.
