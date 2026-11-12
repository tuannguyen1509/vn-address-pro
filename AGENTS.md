# Quy ước làm việc

- Sau mỗi đợt thay đổi code hoàn tất, cập nhật version plugin trong header `vn-address-api.php` và ghi thay đổi tương ứng vào `CHANGELOG.md`. Không để code mới giữ nguyên version cũ.
- Mặc định tăng patch một lần cho mỗi đợt thay đổi (hiện tại: 2.0.2 → 2.0.3); nếu người dùng chỉ định version hoặc có quyết định release minor/major thì theo quyết định đó. Không tăng riêng cho từng file hay từng lần chạy test.
- Version header là nguồn duy nhất cho `VNAP_VERSION`. Gói ZIP phải mang đúng version đã kiểm tra. Khi được yêu cầu cập nhật bản cài, xác nhận header, runtime và marker `vnap_version` sau cập nhật.
- Không đổi version contract REST v1, schema database hoặc dataset chỉ để khớp version release: chúng có vòng đời độc lập.
- Plugin phải dùng lại được trên nhiều website. Không đưa domain, phí ship, ID dữ liệu hoặc cấu hình của một website vào logic mặc định.
- Theo yêu cầu hiện tại: địa chỉ mới dùng dataset API hai cấp; giữ nguyên dữ liệu khách hàng/đơn cũ, không tự động migration hay suy đoán quốc gia.
