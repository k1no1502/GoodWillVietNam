# 📐 CẤU TRÚC DỰ ÁN - GOODWILL VIETNAM

## 🎯 Header & Footer Chung

Để tránh lặp code, dự án sử dụng **header** và **footer** chung cho tất cả các trang.

### 📄 Cách sử dụng Header/Footer

#### **Cách 1: Sử dụng đơn giản (Recommended)**

```php
<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Set page title (optional)
$pageTitle = "Tên Trang";

// Include header
include 'includes/header.php';
?>

<!-- NỘI DUNG TRANG CỦA BẠN -->
<div class="container mt-5 pt-5">
    <h1>Nội dung trang</h1>
</div>

<?php
// Include footer
include 'includes/footer.php';
?>
```

#### **Cách 2: Với Chart.js và Scripts bổ sung**

```php
<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

$pageTitle = "Dashboard";
$includeChartJS = true; // Bật Chart.js

include 'includes/header.php';
?>

<!-- NỘI DUNG -->
<div class="container">
    <canvas id="myChart"></canvas>
</div>

<?php
// Script bổ sung cho trang này
$additionalScripts = "
<script>
// Your custom JavaScript here
const ctx = document.getElementById('myChart');
new Chart(ctx, { /* config */ });
</script>
";

include 'includes/footer.php';
?>
```

#### **Cách 3: Cho trang trong thư mục con (admin/)**

```php
<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

$pageTitle = "Admin Dashboard";
$baseUrl = '../'; // Quan trọng cho đường dẫn!

include '../includes/header.php';
?>

<!-- NỘI DUNG ADMIN -->

<?php
include '../includes/footer.php';
?>
```

---

## 🎨 Tính năng Header

### ✅ Navigation tự động active
Header tự động đánh dấu trang hiện tại là `active`:
- Kiểm tra `$current_page` từ `$_SERVER['PHP_SELF']`
- Thêm class `active` vào link tương ứng

### ✅ Giỏ hàng động
- Tự động load số lượng giỏ hàng khi đăng nhập
- Cập nhật realtime khi thêm sản phẩm

### ✅ Menu responsive
- Hiển thị đầy đủ trên desktop
- Collapse menu trên mobile
- Dropdown cho user menu

### ✅ Phân quyền
- Hiển thị link "Quản trị" nếu là Admin
- Hiển thị "Đăng nhập/Đăng ký" nếu chưa login
- Hiển thị menu user nếu đã login

---

## 🦶 Tính năng Footer

### ✅ Thông tin liên hệ
- Địa chỉ, email, số điện thoại
- Social media links
- Giờ làm việc

### ✅ Quick links
- Các trang chính (Trang chủ, Quyên góp, Shop, Chiến dịch)
- Trang hỗ trợ (Giới thiệu, Liên hệ, Trợ giúp, FAQ)
- Chính sách (Bảo mật, Điều khoản)

### ✅ Copyright năm tự động
- Hiển thị năm hiện tại `<?php echo date('Y'); ?>`

---

## 📋 Biến có thể truyền vào Header/Footer

| Biến | Mô tả | Giá trị mặc định | Bắt buộc |
|------|-------|-----------------|----------|
| `$pageTitle` | Tiêu đề trang | '' | Không |
| `$includeChartJS` | Include Chart.js | false | Không |
| `$baseUrl` | Base URL cho thư mục con | '' | Không (cần cho admin/) |
| `$additionalScripts` | Script bổ sung | '' | Không |

---

## 🗂️ Cấu trúc thư mục

```
C:\xampp\htdocs\Cap 1 - 2\
│
├── includes/
│   ├── header.php          ← Header chung
│   ├── footer.php          ← Footer chung
│   └── functions.php       ← Functions
│
├── admin/
│   ├── includes/
│   │   └── sidebar.php     ← Sidebar riêng admin
│   └── dashboard.php
│
├── index.php               ← Sử dụng header/footer
├── shop-simple.php         ← Ví dụ sử dụng
├── donate.php
├── campaigns.php
└── ...
```

---

## 🔄 Chuyển đổi trang cũ sang header/footer mới

### Before (Cũ):
```php
<!DOCTYPE html>
<html>
<head>
    <title>Page Title</title>
    <!-- 50+ lines of repeated code -->
</head>
<body>
    <nav>...</nav>
    
    <!-- Content -->
    
    <footer>...</footer>
</body>
</html>
```

### After (Mới):
```php
<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

$pageTitle = "Page Title";
include 'includes/header.php';
?>

<!-- Content only -->

<?php include 'includes/footer.php'; ?>
```

**Lợi ích:**
- ✅ Code ngắn gọn hơn 80%
- ✅ Dễ maintain
- ✅ Thay đổi 1 lần, áp dụng toàn bộ
- ✅ Đồng nhất 100% giữa các trang

---

## 🎯 Best Practices

### ✅ Luôn set session_start() trước include
```php
session_start();  // Bắt buộc!
include 'includes/header.php';
```

### ✅ Require functions trước header
```php
require_once 'includes/functions.php';  // Trước
include 'includes/header.php';          // Sau
```

### ✅ Sử dụng $baseUrl cho thư mục con
```php
// Trong admin/dashboard.php
$baseUrl = '../';
include '../includes/header.php';
```

### ✅ Thêm class mt-5 pt-5 cho content
```php
<!-- Tránh bị che bởi fixed navbar -->
<div class="container mt-5 pt-5">
    <!-- Content -->
</div>
```

---

## 🐛 Troubleshooting

### ❌ Lỗi: "Cannot modify header information"
```
Warning: Cannot modify header information - headers already sent
```
**Giải pháp:** Đảm bảo không có output (echo, HTML) trước `session_start()`

### ❌ Lỗi: CSS/JS không load
```
Failed to load resource: net::ERR_FILE_NOT_FOUND
```
**Giải pháp:** Set `$baseUrl` đúng cho thư mục con
```php
$baseUrl = '../';  // admin/
$baseUrl = '../../';  // admin/sub/
```

### ❌ Lỗi: Navigation không active
**Giải pháp:** Header tự động xử lý, không cần làm gì

### ❌ Lỗi: Cart count không update
**Giải pháp:** Đảm bảo đã login và file `api/get-cart-count.php` tồn tại

---

## 📚 Ví dụ thực tế

### Trang đơn giản
```php
<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

$pageTitle = "Giới thiệu";
include 'includes/header.php';
?>

<div class="container mt-5 pt-5">
    <h1>Về chúng tôi</h1>
    <p>Nội dung giới thiệu...</p>
</div>

<?php include 'includes/footer.php'; ?>
```

### Trang với xử lý form
```php
<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Xử lý form
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

### Trang admin
```php
<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireAdmin();

$pageTitle = "Dashboard";
$baseUrl = '../';
$includeChartJS = true;

include '../includes/header.php';
?>

<!-- Admin sidebar -->
<?php include 'includes/sidebar.php'; ?>

<!-- Admin content -->

<?php
$additionalScripts = "<script>/* Chart code */</script>";
include '../includes/footer.php';
?>
```

---

## ✅ Checklist khi tạo trang mới

- [ ] `session_start()` ở đầu file
- [ ] `require_once 'config/database.php'`
- [ ] `require_once 'includes/functions.php'`
- [ ] Set `$pageTitle` (optional)
- [ ] Set `$baseUrl` nếu ở thư mục con
- [ ] `include 'includes/header.php'`
- [ ] Content với `mt-5 pt-5`
- [ ] `include 'includes/footer.php'`

---

**Made with ❤️ by Goodwill Vietnam Team**
