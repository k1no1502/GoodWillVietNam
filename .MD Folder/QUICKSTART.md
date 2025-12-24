# 🚀 HƯỚNG DẪN NHANH - GOODWILL VIETNAM

## Cài đặt trong 5 phút ⏱️

### Bước 1: Cài XAMPP
```bash
1. Tải XAMPP: https://www.apachefriends.org/
2. Cài đặt vào C:\xampp
3. Khởi động Apache + MySQL trong XAMPP Control Panel
```

### Bước 2: Copy dự án
```bash
1. Copy thư mục dự án vào: C:\xampp\htdocs\Cap 1 - 2
2. Đảm bảo có đầy đủ file và thư mục
```

### Bước 3: Tạo Database
```bash
1. Truy cập: http://localhost/phpmyadmin
2. Tạo database: goodwill_vietnam (utf8mb4_unicode_ci)
3. Import file: database/schema.sql
4. Import file: database/update_schema.sql
```

### Bước 4: Hoàn tất
```bash
Truy cập: http://localhost/Cap%201%20-%202/
```

## 🔑 Đăng nhập Admin

```
Email: admin@goodwillvietnam.com
Password: password
```

## ✨ Chức năng chính

### 👤 Người dùng (User)
- ✅ Đăng ký / Đăng nhập
- ✅ Quyên góp vật phẩm (upload ảnh)
- ✅ Xem và lọc vật phẩm (Miễn phí / Giá rẻ)
- ✅ Thêm vào giỏ hàng
- ✅ Đặt hàng và thanh toán
- ✅ Theo dõi đơn hàng

### 👨‍💼 Quản trị viên (Admin)
- ✅ Dashboard với thống kê
- ✅ Duyệt quyên góp → Tự động thêm vào kho
- ✅ Quản lý kho hàng (thiết lập giá)
- ✅ Xử lý đơn hàng
- ✅ Quản lý người dùng
- ✅ Báo cáo với Chart.js

## 🎯 Tính năng đặc biệt

### Bán hàng giá rẻ / Miễn phí
1. Admin duyệt quyên góp
2. Vật phẩm tự động vào kho
3. Admin thiết lập:
   - **Miễn phí** (0 VNĐ)
   - **Giá rẻ** (< 100,000 VNĐ)
4. User lọc và mua/nhận

### Bộ lọc thông minh
- Theo **danh mục**: Quần áo, Điện tử, Sách vở...
- Theo **loại giá**: Miễn phí / Giá rẻ
- **Tìm kiếm** theo tên vật phẩm

## 📊 Cấu trúc Database

```
users → donations → inventory → orders
  ↓         ↓           ↓          ↓
roles   categories    cart    order_items
```

## 🛡️ Bảo mật

- ✅ Password hash với bcrypt
- ✅ PDO Prepared Statements
- ✅ Session management
- ✅ Input validation & sanitization
- ✅ File upload validation (MIME type)

## 📁 Cấu trúc quan trọng

```
C:\xampp\htdocs\Cap 1 - 2\
├── admin/              ← Trang quản trị
├── api/                ← API endpoints
├── assets/             ← CSS, JS, Images
├── config/             ← Database config
├── database/           ← SQL files
│   ├── schema.sql      ← Import trước
│   └── update_schema.sql ← Import sau
├── includes/           ← PHP functions
├── uploads/            ← User uploads
└── index.php           ← Trang chủ
```

## ❗ Troubleshooting

### Lỗi kết nối database?
```bash
✓ Kiểm tra MySQL đã chạy
✓ Kiểm tra config/database.php
✓ Tên DB: goodwill_vietnam
```

### Lỗi table not found?
```bash
✓ Import schema.sql trước
✓ Import update_schema.sql sau
✓ Đúng thứ tự!
```

### Upload file lỗi?
```bash
✓ Tạo thư mục: uploads/donations/
✓ Phân quyền Full Control
```

### CSS không load?
```bash
✓ Clear cache (Ctrl + F5)
✓ Kiểm tra thư mục assets/
```

## 🌐 URLs quan trọng

```
Trang chủ:    http://localhost/Cap%201%20-%202/
Đăng nhập:    http://localhost/Cap%201%20-%202/login.php
Admin Panel:  http://localhost/Cap%201%20-%202/admin/
phpMyAdmin:   http://localhost/phpmyadmin
```

## 📞 Liên hệ

Có vấn đề? Xem file `INSTALL.txt` để được hướng dẫn chi tiết hơn!

---

**Made with ❤️ by Goodwill Vietnam Team**
