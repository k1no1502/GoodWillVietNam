# ?? Goodwill Vietnam – N?n t?ng thi?n nguy?n s?

Goodwill Vietnam là website thi?n nguy?n tr?n v?n, k?t n?i ngu?i t?ng – ngu?i nh?n – ban v?n hành ch? v?i **PHP 8 + MySQL + HTML/CSS/JS + Bootstrap 5**. Giúp các t? ch?c phi l?i nhu?n qu?n lý quyên góp, kho v?t ph?m, chi?n d?ch và tình nguy?n viên trên m?t h? th?ng duy nh?t.

## ?? M?c l?c
- [? Tính nang n?i b?t](#-tính-nang-n?i-b?t)
- [?? Công ngh? s? d?ng](#-công-ngh?-s?-d?ng)
- [??? Yêu c?u h? th?ng](#?-yêu-c?u-h?-th?ng)
- [?? Hu?ng d?n cài d?t nhanh](#?-hu?ng-d?n-cài-d?t-nhanh)
- [?? C?u trúc thu m?c](#-c?u-trúc-thu-m?c)
- [?? Tài kho?n m?u](#-tài-kho?n-m?u)
- [?? Chi ti?t ch?c nang](#-chi-ti?t-ch?c-nang)
- [??? B?o m?t & tuân th?](#?-b?o-m?t--tuân-th?)
- [?? Quy trình v?n hành](#-quy-trình-v?n-hành)
- [?? L? trình phát tri?n](#-l?-trình-phát-tri?n)
- [?? H? tr? & tài li?u](#-h?-tr?--tài-li?u)
- [?? Gi?y phép](#-gi?y-phép)

## ? Tính nang n?i b?t
- **Form quyên góp thông minh**: t?o nhi?u v?t ph?m, upload ?nh/link, nh?p hàng lo?t t? Excel/CSV (.xlsx, .xls, .csv).
- **Theo dõi quyên góp gi?ng don hàng**: trang donation-tracking.php hi?n th? ti?n trình duy?t, nh?p kho, phân ph?i b?ng timeline & ph?n tram hoàn thành.
- **Shop thi?n nguy?n**: l?c danh m?c/lo?i giá, gi? hàng, thanh toán COD, tra c?u tr?ng thái giao hàng.
- **Admin Insight**: dashboard Chart.js, th?ng kê ngu?i dùng, kho hàng, quyên góp, chi?n d?ch, nh?t ký ho?t d?ng.
- **Chi?n d?ch + tình nguy?n viên**: dang ký tr?c tuy?n, c?p nh?t ti?n d? chi?n d?ch, s? lu?ng v?t ph?m dã nh?n.
- **Kho v?t ph?m**: duy?t quyên góp vào kho, d?nh giá (mi?n phí, giá r?, giá thu?ng), g?n chi?n d?ch, qu?n lý t?n.

## ?? Công ngh? s? d?ng
| T?ng            | Công ngh? |
|-----------------|-----------|
| Frontend        | HTML5, CSS3, Bootstrap 5, JavaScript, Chart.js |
| Backend         | PHP 8.x (PDO, session) |
| Database        | MySQL 8.x (utf8mb4) |
| Thu vi?n khác   | Bootstrap Icons, ZipArchive, SimpleXML |
| Ki?n trúc       | MVC don gi?n + module Admin/API |

## ??? Yêu c?u h? th?ng
- Apache/Nginx (XAMPP, WAMP/LAMP ho?c Laragon d?u phù h?p).
- PHP = 8.0, b?t pdo_mysql, mbstring, zip, iconv.
- MySQL = 8.0, charset utf8mb4.
- Trình duy?t hi?n d?i: Chrome, Edge, Firefox.

## ?? Hu?ng d?n cài d?t nhanh
1. **Clone mã ngu?n**
   `ash
   cd C:\laragon\www
   git clone <repo-url> "Cap 1 - 2"
   `
2. **T?o database**
   - phpMyAdmin ? t?o DB goodwill_vietnam (utf8mb4).
   - Import database/schema.sql (và database/update_schema.sql n?u có).
3. **C?u hình** (config/database.php)
   `php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'goodwill_vietnam');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   `
4. **C?p quy?n**: thu m?c uploads/ ph?i cho phép ghi.
5. **Truy c?p**: http://localhost/Cap%201%20-%202/

> ?? *Ch? c?n b?t thêm ZipArchive n?u mu?n d?c file .xlsx.*

## ?? C?u trúc thu m?c
`
Cap 1 - 2/
+-- admin/                # Qu?n tr?: dashboard, donations, inventory...
+-- api/                  # Endpoint AJAX/REST nh?
+-- assets/               # CSS, JS, hình, template Excel
+-- config/               # database.php
+-- database/             # schema, seed
+-- includes/             # header/footer/functions
+-- uploads/              # ?nh quyên góp, chi?n d?ch
+-- donation-tracking.php # trang theo dõi quyên góp
+-- donate.php            # form quyên góp
+-- my-donations.php      # l?ch s? quyên góp
+-- order-tracking.php    # theo dõi don hàng
+-- ...
`

## ?? Tài kho?n m?u
| Lo?i  | Email                      | M?t kh?u |
|-------|----------------------------|----------|
| Admin | admin@goodwillvietnam.com | password |
| User  | T? dang ký ho?c import    | –        |

> ?? Ð?i m?t kh?u admin ngay sau khi kh?i ch?y.

## ?? Chi ti?t ch?c nang
### Ngu?i dùng
- **Quyên góp**: nh?p tay ho?c t?i Excel/CSV, gi?i h?n 5 ?nh/v?t ph?m, d?t l?ch nh?n, theo dõi ti?n trình.
- **Shop**: l?c danh m?c, lo?i giá, khuy?n mãi; gi? hàng, thanh toán COD, xem l?ch s? don – tracking theo t?ng don.
- **Chi?n d?ch & thi?n nguy?n**: xem nhu c?u, dóng góp nhanh, dang ký tình nguy?n viên.
- **Tài kho?n**: qu?n lý h? so, d?i m?t kh?u, xem l?ch s? quyên góp (my-donations.php).

### Qu?n tr? viên
- Duy?t/T? ch?i quyên góp, ghi chú n?i b?.
- Qu?n lý kho: d?nh giá, tr?ng thái v?t ph?m, v? trí luu tr?, liên k?t chi?n d?ch.
- Qu?n lý don hàng, chi?n d?ch, danh m?c, ngu?i dùng, ph?n h?i.
- Dashboard tr?c quan (Chart.js) + nh?t ký ho?t d?ng.

## ??? B?o m?t & tuân th?
- M?t kh?u bam b?ng password_hash.
- PDO Prepared Statements ch?ng SQL Injection.
- Ki?m tra session & phân quy?n trên m?i trang.
- Ki?m tra MIME type tru?c khi luu ?nh.
- Chu?n hóa UTF-8 khi x? lý Excel/CSV (h?n ch? l?i mã hóa ti?ng Vi?t).

## ?? Quy trình v?n hành
1. **Quyên góp**: g?i don ? admin duy?t ? nh?p kho ? phân ph?i ? ngu?i t?ng theo dõi.
2. **Mua hàng**: ch?n s?n ph?m ? gi? hàng ? COD ? admin giao/ c?p nh?t tr?ng thái.
3. **Chi?n d?ch**: t?o chi?n d?ch ? kêu g?i v?t ph?m/tình nguy?n ? theo dõi ti?n d? trên dashboard.

## ?? L? trình phát tri?n
- [ ] Tích h?p thanh toán tr?c tuy?n (VNPay/MoMo).
- [ ] Thông báo realtime / push notification.
- [ ] Xu?t báo cáo PDF/Excel 1-click.
- [ ] API RESTful công khai.
- [ ] ?ng d?ng mobile, social login, email marketing.

## ?? H? tr? & tài li?u
1. **Log l?i**: pache/logs/error.log, mysql/data/*.err.
2. **Uploads**: d?m b?o uploads/ du?c quy?n ghi.
3. **C?u hình DB**: ki?m tra config/database.php.
4. **Ph? l?c**: file INSTALL.txt mô t? chi ti?t hon (kèm checklist tri?n khai, script seed d? li?u).

## ?? Gi?y phép
D? án ph?c v? m?c dích giáo d?c và c?ng d?ng, không s? d?ng cho m?c dích thuong m?i n?u chua có s? d?ng ý c?a Goodwill Vietnam Team (2024).

---
**?? Chúc b?n tri?n khai n?n t?ng thi?n nguy?n thành công!**
