# Bố cục và CSS cho biểu mẫu

**Từ 2.0.7:** CSS frontend mặc định tắt. Bật tại Cài đặt → VN Address → Thiết lập → Sử dụng CSS của plugin. Xem [checkout thu gọn và VAT](compact-checkout-vat.md).

Từ 2.0.5, CSS mặc định của bộ địa chỉ chỉ hỗ trợ bố cục. Màu sắc, nền, font, viền và kích thước ô nhập kế thừa từ plugin form hoặc theme. Không áp kiểu giao diện cho toàn bộ form.

- WPForms: nhóm tỉnh/phường hiển thị hai cột; chuyển sang một cột khi vùng chứa không quá 30rem hoặc màn hình không quá 640px.
- Contact Form 7: trường địa chỉ vừa vùng chứa; cách chia cột do mẫu form của bạn quyết định.
- Fluent Forms: giữ cách chia cột và giao diện của trình tạo form.
- Ninja Forms chưa có adapter địa chỉ của VN Address Pro; không áp CSS vào các trường Ninja Forms thông thường.

Các quy tắc bố cục dùng `:where()` với độ ưu tiên bằng 0. Không dùng `!important`, không gắn style trực tiếp vào trường. Quy tắc giữ phần tử `hidden` được tách riêng để nút thử lại không xuất hiện khi không cần.

## Ví dụ tùy chỉnh

Thêm vào CSS của theme con hoặc mục CSS bổ sung của website:

```css
/* Khoảng cách giữa các trường địa chỉ. */
.vnap-address {
    --vnap-gap: 12px;
    --vnap-field-gap: 6px;
}

/* Luôn xếp dọc nhóm địa chỉ trong WPForms. */
.wpforms-field-vnap-address .vnap-address {
    grid-template-columns: minmax(0, 1fr);
}

/* Chỉ đổi chiều rộng trường địa chỉ Contact Form 7. */
.wpcf7 .vnap-cf7-field {
    width: 100%;
}
```

CSS riêng của plugin form hoặc theme vẫn có thể có độ ưu tiên cao hơn. Khi đổi màu, font hoặc chiều cao, hãy dùng selector phù hợp với chính plugin form đó; không cần tăng độ ưu tiên chỉ để vượt CSS bố cục của VN Address Pro.

## Kiểm tra 17/11/2026

Đã chạy trên PHP 8.3, WordPress 7.1.3 với WPForms Lite 2.0.4 (22 kiểm tra), Contact Form 7 6.2.5 (32), Fluent Forms 6.2.17 (28), và bộ render frontend (32). Các lượt xử lý form dùng dữ liệu giả và chặn gửi email thật trong database riêng.

Trình duyệt ẩn kiểm tra ba form cùng trang, tải phường theo từng tỉnh độc lập, đổi tỉnh xóa lựa chọn phường cũ, hai cột trên desktop, một cột ở 390px và không tràn ngang. CSS riêng `.vnap-address { gap: 23px; }` ghi đè thành công mà không cần `!important`. Đây là kiểm tra bố cục và tích hợp địa chỉ, không chứng nhận toàn bộ tính năng hay mọi theme của các plugin form.
