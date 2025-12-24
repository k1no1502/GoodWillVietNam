# 📊 HƯỚNG DẪN IMPORT DATABASE - TỪNG BƯỚC

## ⚠️ QUAN TRỌNG: IMPORT ĐÚNG THỨ TỰ!

---

## 🎯 BƯỚC 1: MỞ phpMyAdmin

```
1. Mở XAMPP Control Panel
2. Start Apache + MySQL
3. Mở trình duyệt
4. Truy cập: http://localhost/phpmyadmin
```

---

## 🎯 BƯỚC 2: TẠO DATABASE

```
1. Click tab "Databases" (hoặc "Cơ sở dữ liệu")
2. Tên database: goodwill_vietnam
3. Collation: utf8mb4_unicode_ci
4. Click "Create" (hoặc "Tạo")
```

---

## 🎯 BƯỚC 3: IMPORT FILE SQL (ĐÚNG THỨ TỰ!)

### **File 1️⃣: schema.sql** (BẮT BUỘC)
```
1. Click vào database "goodwill_vietnam"
2. Click tab "Import" (hoặc "Nhập")
3. Click "Choose File"
4. Chọn: database/schema.sql
5. Click "Go" (hoặc "Thực hiện")
6. Đợi... → Thành công ✅
```

**Kết quả:** Tạo các bảng cơ bản: users, donations, categories...

---

### **File 2️⃣: update_schema.sql** (BẮT BUỘC)
```
1. Vẫn trong database "goodwill_vietnam"
2. Click tab "Import"
3. Choose File: database/update_schema.sql
4. Click "Go"
5. Đợi... → Thành công ✅
```

**Kết quả:** Thêm bảng shop: cart, orders, order_items + cập nhật inventory

---

### **File 3️⃣: campaigns_simple.sql** (BẮT BUỘC CHO CHIẾN DỊCH)
```
1. Vẫn trong database "goodwill_vietnam"
2. Click tab "Import"
3. Choose File: database/campaigns_simple.sql
4. Click "Go"
5. Đợi... → Thành công ✅
```

**Kết quả:** Thêm bảng chiến dịch: campaign_items, campaign_volunteers...

---

### **File 4️⃣: check_and_fix.sql** (TÙY CHỌN - Chỉ khi có lỗi)
```
1. Chỉ chạy KHI quyên góp không hiện shop
2. Click tab "SQL"
3. Copy nội dung file check_and_fix.sql
4. Paste vào ô SQL
5. Click "Go"
```

**Kết quả:** Sync quyên góp đã duyệt vào kho hàng

---

## ✅ BƯỚC 4: KIỂM TRA

### **Kiểm tra các bảng đã tạo:**

```
1. Click vào database "goodwill_vietnam"
2. Xem danh sách tables:
```

**Phải có các bảng sau:**

✅ Bảng cơ bản:
- users
- roles  
- donations
- categories
- inventory
- feedback
- activity_logs
- system_settings

✅ Bảng shop:
- cart
- orders
- order_items

✅ Bảng chiến dịch:
- campaigns
- campaign_items
- campaign_donations
- campaign_volunteers

**Tổng cộng: 15+ bảng**

---

## 🧪 BƯỚC 5: TEST

### **Test 1: Kiểm tra database**
```
Truy cập: http://localhost/Cap%201%20-%202/test-database.php
```

Kết quả mong đợi:
- ✅ Kết nối thành công
- ✅ Tất cả bảng hiển thị
- ✅ Không có lỗi

### **Test 2: Test website**
```
Truy cập: http://localhost/Cap%201%20-%202/
```

Kết quả mong đợi:
- ✅ Trang chủ hiển thị
- ✅ Menu hoạt động
- ✅ Không có lỗi

---

## 🐛 XỬ LÝ LỖI

### ❌ Lỗi: "USE goodwill_vietnam syntax error"

**NGUYÊN NHÂN:** Đã chọn database rồi, không cần USE

**CÁCH SỬA:**
```
KHÔNG dùng: campaigns_update.sql (có USE)
DÙNG: campaigns_simple.sql (không có USE)
```

### ❌ Lỗi: "Table already exists"

**NGUYÊN NHÂN:** Đã import rồi, import lại

**CÁCH SỬA:**
```
CÁCH 1: Bỏ qua lỗi này (không sao)

CÁCH 2: Xóa và tạo lại
1. Drop database
2. Create lại
3. Import lại từ đầu
```

### ❌ Lỗi: "Foreign key constraint fails"

**NGUYÊN NHÂN:** Import sai thứ tự

**CÁCH SỬA:**
```
1. Drop tất cả tables
2. Import lại ĐÚNG THỨ TỰ:
   ① schema.sql
   ② update_schema.sql
   ③ campaigns_simple.sql
```

### ❌ Lỗi: "Column already exists"

**NGUYÊN NHÂN:** Đã có column rồi

**CÁCH SỬA:**
```
Bỏ qua lỗi này - Không ảnh hưởng
(SQL dùng IF NOT EXISTS nên an toàn)
```

---

## 📝 CHECKLIST IMPORT

```bash
☐ 1. Mở phpMyAdmin
☐ 2. Tạo database: goodwill_vietnam (utf8mb4_unicode_ci)
☐ 3. Chọn database vừa tạo
☐ 4. Import schema.sql → Thành công
☐ 5. Import update_schema.sql → Thành công
☐ 6. Import campaigns_simple.sql → Thành công
☐ 7. Kiểm tra có 15+ tables
☐ 8. Test: http://localhost/Cap%201%20-%202/test-database.php
☐ 9. Login admin: admin@goodwillvietnam.com / password
☐ 10. Test website hoạt động
```

---

## 🎯 THỨ TỰ IMPORT (QUAN TRỌNG!)

```
1️⃣ schema.sql          ← Tạo cấu trúc cơ bản
   ↓
2️⃣ update_schema.sql   ← Thêm shop, cart, orders
   ↓
3️⃣ campaigns_simple.sql ← Thêm chiến dịch, tình nguyện
   ↓
4️⃣ check_and_fix.sql   ← (Optional) Fix sync
```

**KHÔNG được đảo thứ tự!**

---

## 💡 MẸO IMPORT

### **Cách 1: Import từng file (RECOMMENDED)**
```
✅ Dễ kiểm soát
✅ Dễ debug nếu lỗi
✅ Thấy được file nào lỗi
```

### **Cách 2: Copy/Paste SQL**
```
1. Mở file .sql bằng Notepad
2. Copy toàn bộ nội dung
3. Vào phpMyAdmin → tab "SQL"
4. Paste vào
5. Click "Go"

⚠️ Lưu ý: Bỏ dòng "USE goodwill_vietnam;"
```

### **Cách 3: Command line (Advanced)**
```bash
cd C:\xampp\mysql\bin
mysql -u root -p goodwill_vietnam < "C:\xampp\htdocs\Cap 1 - 2\database\schema.sql"
```

---

## 🎉 SAU KHI IMPORT XONG

### **Kiểm tra:**
```sql
-- Đếm tables
SELECT COUNT(*) as total_tables 
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = 'goodwill_vietnam';
-- Kết quả: 15+

-- Đếm users
SELECT COUNT(*) as total_users FROM users;
-- Kết quả: 1 (admin)

-- Xem categories
SELECT * FROM categories;
-- Kết quả: 8 danh mục
```

### **Test login:**
```
Email: admin@goodwillvietnam.com
Password: password
```

---

## 📞 HỖ TRỢ

Nếu vẫn gặp lỗi:

1. **Screenshot lỗi** trong phpMyAdmin
2. **Copy thông báo lỗi** đầy đủ
3. **Kiểm tra:** File nào đang import
4. **Xem log:** C:\xampp\mysql\data\*.err

---

**Chúc bạn import thành công! 🚀**
