# 📊 DATABASE - HƯỚNG DẪN IMPORT

## 🎯 THỨ TỰ IMPORT (BẮT BUỘC)

### **Bước 1: Tạo database**
```sql
CREATE DATABASE goodwill_vietnam 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;
```

### **Bước 2: Chọn database**
```
Click vào "goodwill_vietnam" trong phpMyAdmin
```

### **Bước 3: Import các file (ĐÚNG THỨ TỰ!)**

#### **File 1️⃣: `schema.sql`** (BẮT BUỘC)
```
Import tab → Choose File → schema.sql → Go
```
**Tạo:** users, roles, donations, categories, inventory...

---

#### **File 2️⃣: `update_schema.sql`** (BẮT BUỘC)
```
Import tab → Choose File → update_schema.sql → Go
```
**Thêm:** cart, orders, order_items + cập nhật inventory (price_type, sale_price)

---

#### **File 3️⃣: `campaigns_simple.sql`** (BẮT BUỘC CHO CHIẾN DỊCH)
```
Import tab → Choose File → campaigns_simple.sql → Go
```
**Thêm:** campaign_items, campaign_donations, campaign_volunteers

---

#### **File 4️⃣: `check_and_fix.sql`** (TÙY CHỌN)
```
Chỉ chạy KHI quyên góp không hiện trong shop
Tab SQL → Copy/Paste → Go
```
**Fix:** Sync quyên góp đã duyệt vào inventory

---

## 📋 CÁC FILE TRONG THƯ MỤC

| File | Mô tả | Bắt buộc? |
|------|-------|-----------|
| `schema.sql` | Cấu trúc cơ bản | ✅ BẮT BUỘC |
| `update_schema.sql` | Thêm shop, giỏ hàng | ✅ BẮT BUỘC |
| `campaigns_simple.sql` | Thêm chiến dịch | ✅ BẮT BUỘC |
| `check_and_fix.sql` | Fix sync quyên góp | ⚠️ Khi cần |
| `campaigns_update.sql` | ❌ KHÔNG DÙNG | ❌ Có lỗi USE |
| `import_all.sql` | ℹ️ Tham khảo | ℹ️ Chỉ xem |

---

## ✅ KIỂM TRA SAU KHI IMPORT

### **Đếm tables:**
```sql
SELECT COUNT(*) as total_tables 
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = 'goodwill_vietnam';
```
**Kết quả:** Phải có 15+ tables

### **Kiểm tra tables chính:**
```sql
SHOW TABLES;
```

**Phải có:**
- ✅ users, roles
- ✅ donations, inventory
- ✅ cart, orders, order_items
- ✅ campaigns, campaign_items, campaign_volunteers
- ✅ categories, feedback
- ✅ activity_logs, system_settings

---

## 🐛 XỬ LÝ LỖI

### ❌ Lỗi: "Duplicate column name"
**Nghĩa là:** Cột đã tồn tại
**Cách fix:** Bỏ qua lỗi này (không ảnh hưởng)

### ❌ Lỗi: "Table already exists"
**Nghĩa là:** Bảng đã có rồi
**Cách fix:** Bỏ qua lỗi này (SQL dùng IF NOT EXISTS)

### ❌ Lỗi: "USE goodwill_vietnam syntax error"
**Nghĩa là:** Dùng sai file
**Cách fix:** 
- ✅ DÙNG: campaigns_simple.sql
- ❌ KHÔNG dùng: campaigns_update.sql

### ❌ Lỗi: "Foreign key constraint fails"
**Nghĩa là:** Import sai thứ tự
**Cách fix:** 
```
1. Drop all tables
2. Import lại từ đầu ĐÚNG THỨ TỰ
```

---

## 🧪 TEST DATABASE

Sau khi import xong:

```
http://localhost/Cap%201%20-%202/test-database.php
```

**Kiểm tra:**
- ✅ Kết nối thành công?
- ✅ Có đủ 15+ tables?
- ✅ Có dữ liệu mẫu?
- ✅ Có vật phẩm trong shop?

Nếu thiếu vật phẩm:
- Click nút "🔄 Sync vật phẩm vào kho"

---

## 📞 HỖ TRỢ

### Nếu gặp lỗi không giải quyết được:

1. **Xóa database và tạo lại:**
```sql
DROP DATABASE IF EXISTS goodwill_vietnam;
CREATE DATABASE goodwill_vietnam 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;
```

2. **Import lại từ đầu:**
```
1. schema.sql
2. update_schema.sql
3. campaigns_simple.sql
```

---

**Made with ❤️ by Goodwill Vietnam**
