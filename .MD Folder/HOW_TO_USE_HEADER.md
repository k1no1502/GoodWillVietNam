# 🎯 HƯỚNG DẪN SỬ DỤNG HEADER/FOOTER CHUNG

## ✅ Header đã được tạo chung cho TẤT CẢ các trang!

File: `includes/header.php` và `includes/footer.php`

---

## 📋 CÁCH SỬ DỤNG CHO MỌI TRANG

### **Template chuẩn cho MỌI trang:**

```php
<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Đặt tiêu đề trang (tùy chọn)
$pageTitle = "Tên trang của bạn";

// Include header CHUNG
include 'includes/header.php';
?>

<!-- NỘI DUNG TRANG CỦA BẠN Ở ĐÂY -->
<div class="container mt-5 pt-5">
    <h1>Xin chào</h1>
    <p>Nội dung trang...</p>
</div>

<?php
// Include footer CHUNG
include 'includes/footer.php';
?>
```

---

## 🎨 HEADER CHUNG CÓ GÌ?

Header tự động hiển thị:
- ✅ Logo Goodwill Vietnam
- ✅ Menu: Trang chủ | Quyên góp | Shop Bán Hàng | Chiến dịch | Giới thiệu
- ✅ Tự động đánh dấu trang hiện tại (active)
- ✅ Giỏ hàng (nếu đã đăng nhập)
- ✅ Menu user dropdown (Hồ sơ, Quyên góp của tôi, Đơn hàng...)
- ✅ Link Quản trị (nếu là Admin)
- ✅ Đăng nhập/Đăng ký (nếu chưa đăng nhập)

---

## 📂 CÁC FILE CẦN CẬP NHẬT

### ✅ Đã có header chung:
- ✅ `includes/header.php` - Header chung
- ✅ `includes/footer.php` - Footer chung
- ✅ `shop-simple.php` - Ví dụ sử dụng

### ❌ CẦN CẬP NHẬT các file sau:

1. **index.php** - Trang chủ
2. **donate.php** - Quyên góp  
3. **shop.php** - Shop bán hàng
4. **campaigns.php** - Chiến dịch
5. **create-campaign.php** - Tạo chiến dịch
6. **cart.php** - Giỏ hàng
7. **profile.php** - Hồ sơ
8. **login.php** - Đăng nhập (giữ nguyên vì layout khác)
9. **register.php** - Đăng ký (giữ nguyên vì layout khác)

---

## 🔄 HƯỚNG DẪN CHUYỂN ĐỔI

### **Từ code CŨ (mỗi trang 1 kiểu):**

```php
<!DOCTYPE html>
<html>
<head>
    <title>Trang</title>
    <link href="bootstrap.css">
</head>
<body>
    <nav>
        <a href="index.php">Trang chủ</a>
        <a href="donate.php">Quyên góp</a>
        <!-- Lặp lại 50+ dòng -->
    </nav>
    
    <div>Nội dung...</div>
    
    <footer>
        <!-- Lặp lại 30+ dòng -->
    </footer>
</body>
</html>
```

### **Sang code MỚI (dùng chung):**

```php
<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

$pageTitle = "Trang";
include 'includes/header.php';
?>

<div class="container mt-5 pt-5">
    Nội dung...
</div>

<?php include 'includes/footer.php'; ?>
```

**KẾT QUẢ:**
- ✅ Code ngắn hơn 80%
- ✅ Header/Footer đồng nhất 100%
- ✅ Thay đổi 1 lần → Áp dụng tất cả trang

---

## 💡 VÍ DỤ CỤ THỂ

### **1. Trang đơn giản (about.php):**

```php
<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

$pageTitle = "Giới thiệu";
include 'includes/header.php';
?>

<div class="container mt-5 pt-5">
    <h1>Về Goodwill Vietnam</h1>
    <p>Chúng tôi là tổ chức thiện nguyện...</p>
</div>

<?php include 'includes/footer.php'; ?>
```

### **2. Trang có xử lý dữ liệu (donate.php):**

```php
<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

requireLogin(); // Yêu cầu đăng nhập

// Xử lý form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Logic xử lý...
}

$pageTitle = "Quyên góp";
include 'includes/header.php';
?>

<div class="container mt-5 pt-5">
    <form method="POST">
        <!-- Form fields -->
    </form>
</div>

<?php include 'includes/footer.php'; ?>
```

### **3. Trang trong thư mục con (admin/):**

```php
<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireAdmin();

$pageTitle = "Admin";
$baseUrl = '../'; // QUAN TRỌNG!

include '../includes/header.php';
?>

<!-- Admin content -->

<?php include '../includes/footer.php'; ?>
```

---

## 🎯 LƯU Ý QUAN TRỌNG

### ✅ PHẢI CÓ:

1. **`session_start()`** - Bắt buộc ở đầu
2. **`require_once 'config/database.php'`** - Kết nối DB
3. **`require_once 'includes/functions.php'`** - Functions
4. **`include 'includes/header.php'`** - Header chung
5. **`include 'includes/footer.php'`** - Footer chung

### ✅ TÙY CHỌN:

- `$pageTitle` - Tiêu đề trang
- `$baseUrl = '../'` - Cho thư mục con
- `$includeChartJS = true` - Nếu dùng Chart.js
- `$additionalScripts` - Scripts bổ sung

### ❌ TRÁNH:

- ❌ Không viết lại HTML head/body
- ❌ Không copy/paste navigation
- ❌ Không tạo footer riêng
- ❌ Không quên `session_start()`

---

## 📊 SO SÁNH

| Tiêu chí | Cũ (Mỗi trang 1 kiểu) | Mới (Dùng chung) |
|----------|------------------------|------------------|
| **Số dòng code** | ~200 dòng/trang | ~30 dòng/trang |
| **Thời gian code** | 15 phút/trang | 2 phút/trang |
| **Maintain** | Sửa 10 file | Sửa 1 file |
| **Đồng nhất** | Khác nhau | 100% giống nhau |
| **Active menu** | Phải code thủ công | Tự động |

---

## 🚀 BƯỚC TIẾP THEO

1. **Mở file `test-database.php`** để kiểm tra database
   ```
   http://localhost/Cap%201%20-%202/test-database.php
   ```

2. **Chạy `database/check_and_fix.sql`** trong phpMyAdmin
   - Fix vấn đề quyên góp không hiện trong shop

3. **Test header chung:**
   ```
   http://localhost/Cap%201%20-%202/shop-simple.php
   ```

4. **Cập nhật các trang còn lại** theo template trên

---

## 📞 Kiểm tra nhanh

```bash
✅ Header có logo và menu?
✅ Menu tự động active?
✅ Giỏ hàng hiển thị số lượng?
✅ Dropdown user hoạt động?
✅ Footer có thông tin liên hệ?
✅ Responsive trên mobile?
```

---

**Made with ❤️ by Goodwill Vietnam**
