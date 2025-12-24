# 🏆 HƯỚNG DẪN HỆ THỐNG CHIẾN DỊCH

## ✨ TÍNH NĂNG ĐẦY ĐỦ

### 1. **User tạo chiến dịch**
- Đặt tên và mô tả chiến dịch
- Liệt kê vật phẩm cần thiết (áo, quần, sách...)
- Mỗi vật phẩm có: tên, danh mục, số lượng, đơn vị
- Upload hình ảnh chiến dịch
- Gửi yêu cầu → Status: "pending"

### 2. **Admin duyệt chiến dịch**
- Xem danh sách chiến dịch chờ duyệt
- Duyệt → Status: "active"
- Từ chối → Status: "cancelled" + lý do

### 3. **User quyên góp TRỰC TIẾP vào chiến dịch**
- Xem danh sách vật phẩm cần thiết
- Chọn nhanh vật phẩm từ danh sách
- Hoặc quyên góp vật phẩm khác
- Tự động cập nhật tiến độ chiến dịch

### 4. **User đăng ký tình nguyện viên**
- Điền kỹ năng có thể đóng góp
- Thời gian có thể tham gia
- Lời nhắn và động lực
- Tự động duyệt ngay

---

## 📂 CẤU TRÚC FILE

```
C:\xampp\htdocs\Cap 1 - 2\
├── campaigns.php                    ← Danh sách chiến dịch
├── campaign-detail.php              ← Chi tiết chiến dịch
├── create-campaign.php              ← Tạo chiến dịch mới
├── donate-to-campaign.php           ← Quyên góp vào chiến dịch
├── api/
│   ├── register-volunteer.php       ← API đăng ký nhanh
│   └── register-volunteer-detail.php ← API đăng ký chi tiết
└── database/
    └── campaigns_update.sql         ← SQL cập nhật
```

---

## 🗄️ DATABASE

### Bảng mới:

1. **`campaign_items`** - Vật phẩm cần cho chiến dịch
   - `item_name` - Tên vật phẩm
   - `quantity_needed` - Số lượng cần
   - `quantity_received` - Đã nhận được
   - `unit` - Đơn vị (cái, kg, cuốn...)

2. **`campaign_donations`** - Quyên góp vào chiến dịch
   - `campaign_id` - ID chiến dịch
   - `donation_id` - ID quyên góp
   - `campaign_item_id` - Vật phẩm tương ứng
   - `quantity_contributed` - Số lượng đóng góp

3. **`campaign_volunteers`** - Tình nguyện viên
   - `campaign_id` - ID chiến dịch
   - `user_id` - ID người đăng ký
   - `skills` - Kỹ năng
   - `availability` - Thời gian
   - `message` - Lời nhắn
   - `status` - pending/approved/rejected

### Views:

1. **`v_campaign_details`** - Thống kê chiến dịch
2. **`v_campaign_items_progress`** - Tiến độ vật phẩm

### Triggers:

1. **`after_campaign_donation_insert`** - Tự động cập nhật tiến độ
2. **`after_campaign_donation_delete`** - Trừ tiến độ khi xóa

---

## 🚀 HƯỚNG DẪN CÀI ĐẶT

### Bước 1: Chạy SQL
```bash
1. Mở phpMyAdmin
2. Chọn database: goodwill_vietnam
3. Import file: database/campaigns_update.sql
```

### Bước 2: Kiểm tra
```bash
Truy cập: http://localhost/Cap%201%20-%202/campaigns.php
```

### Bước 3: Test chức năng
1. Đăng nhập user
2. Tạo chiến dịch mới
3. Đăng nhập admin → Duyệt
4. User khác quyên góp vào chiến dịch
5. Đăng ký tình nguyện viên

---

## 📊 QUY TRÌNH HOẠT ĐỘNG

### **Luồng 1: Tạo và triển khai chiến dịch**

```
USER tạo chiến dịch
├─ Điền thông tin (tên, mô tả, thời gian)
├─ Thêm danh sách vật phẩm cần:
│  ├─ 50 áo sơ mi
│  ├─ 30 quần jeans
│  └─ 100 cuốn sách
└─ Gửi yêu cầu → Status: "pending"

↓

ADMIN duyệt
├─ Xem chi tiết chiến dịch
├─ Kiểm tra tính khả thi
└─ Duyệt → Status: "active"

↓

Chiến dịch hiển thị công khai
└─ User có thể:
   ├─ Quyên góp trực tiếp
   └─ Đăng ký tình nguyện viên
```

### **Luồng 2: Quyên góp vào chiến dịch**

```
USER xem chiến dịch
├─ Xem danh sách vật phẩm cần:
│  ├─ Áo sơ mi: Cần 50, Đã nhận 20, Còn 30
│  ├─ Quần jeans: Cần 30, Đã nhận 10, Còn 20
│  └─ Sách: Cần 100, Đã nhận 50, Còn 50
│
├─ Click "Quyên góp cho chiến dịch"
│
├─ Chọn nhanh từ danh sách HOẶC nhập mới
├─ Điền số lượng, upload ảnh
└─ Gửi quyên góp

↓

Hệ thống TỰ ĐỘNG:
├─ Tạo donation (status = approved)
├─ Link vào campaign_donations
├─ CẬP NHẬT tiến độ:
│  ├─ quantity_received += số lượng
│  └─ progress_percentage tính lại
└─ Thêm vào inventory (available)

↓

Chiến dịch cập nhật realtime
└─ Hiển thị tiến độ mới
```

### **Luồng 3: Đăng ký tình nguyện viên**

```
USER xem chiến dịch
├─ Click "Đăng ký tình nguyện viên"
├─ Điền thông tin:
│  ├─ Kỹ năng: "Có xe máy, biết văn phòng"
│  ├─ Thời gian: "Thứ 7, Chủ nhật"
│  └─ Lời nhắn: "Muốn giúp đỡ cộng đồng"
└─ Gửi đăng ký

↓

Hệ thống:
├─ Lưu vào campaign_volunteers
├─ Status: "approved" (tự động duyệt)
└─ Cập nhật số lượng tình nguyện viên

↓

Hiển thị trong danh sách
└─ User thấy tên mình trong "Tình nguyện viên"
```

---

## 🎯 TÍNH NĂNG CHI TIẾT

### **Trang `campaigns.php`**
- Danh sách tất cả chiến dịch đang hoạt động
- Hiển thị:
  - Tên, mô tả, hình ảnh
  - Tiến độ (X% hoàn thành)
  - Số tình nguyện viên
  - Số ngày còn lại
- Actions:
  - Xem chi tiết
  - Đăng ký tình nguyện
  - Quyên góp

### **Trang `campaign-detail.php`**
- Thông tin đầy đủ chiến dịch
- **Danh sách vật phẩm cần thiết** (bảng):
  - Tên vật phẩm | Cần | Đã nhận | Tiến độ | Trạng thái
  - Progress bar cho mỗi vật phẩm
- **Danh sách tình nguyện viên**:
  - Tên, avatar, vai trò
- **Sidebar**:
  - Tiến độ tổng thể
  - Nút quyên góp
  - Nút đăng ký tình nguyện
  - Chia sẻ

### **Trang `donate-to-campaign.php`**
- Thông tin chiến dịch
- **Alert vật phẩm cần thiết** (top 4)
- **Chọn nhanh** vật phẩm từ dropdown
  - Auto-fill form khi chọn
- Form quyên góp đầy đủ
- Upload ảnh
- Tự động link vào chiến dịch

### **Trang `create-campaign.php`**
- Form tạo chiến dịch
- **Dynamic add items**:
  - Thêm/xóa vật phẩm
  - Mỗi vật phẩm: tên, danh mục, số lượng, ghi chú
- Upload hình ảnh
- Validation

---

## 💡 TÍNH NĂNG NỔI BẬT

### ✅ Tự động cập nhật tiến độ
- Khi quyên góp → Tự động cộng vào `quantity_received`
- Tính % hoàn thành realtime
- Hiển thị màu sắc theo trạng thái

### ✅ Chọn nhanh vật phẩm
- Dropdown hiển thị vật phẩm cần thiết
- Chọn → Auto-fill toàn bộ form
- Tiết kiệm thời gian

### ✅ Validation thông minh
- Kiểm tra chiến dịch còn active
- Không cho đăng ký duplicate
- Validate số lượng > 0

### ✅ UI/UX tốt
- Progress bars trực quan
- Badges màu sắc
- Icons rõ ràng
- Responsive mobile

---

## 🔧 API ENDPOINTS

### 1. `api/register-volunteer.php`
```php
POST: { campaign_id: 1 }
Response: { success: true, message: "..." }
```

### 2. `api/register-volunteer-detail.php`
```php
POST: { 
  campaign_id: 1, 
  skills: "...", 
  availability: "...",
  message: "..."
}
Response: { success: true, message: "..." }
```

---

## 📈 ADMIN PANEL (TODO)

Cần tạo thêm trang admin:

### `admin/campaigns.php`
- Danh sách chiến dịch (tất cả status)
- Filter: pending/active/completed
- Actions:
  - Duyệt/Từ chối
  - Xem chi tiết
  - Chỉnh sửa
  - Kết thúc

### `admin/campaign-detail.php`
- Xem đầy đủ thông tin
- Danh sách quyên góp vào chiến dịch
- Danh sách tình nguyện viên
- Nút duyệt/từ chối

---

## 🎨 CUSTOMIZATION

### Thay đổi màu sắc chiến dịch:
```css
/* assets/css/style.css */
.campaign-card {
    border-left: 4px solid #ffc107; /* Vàng */
}
```

### Thay đổi số vật phẩm hiển thị:
```php
// donate-to-campaign.php line ~150
<?php foreach (array_slice($items, 0, 6) as $item): ?>
```

### Tự động duyệt chiến dịch:
```php
// create-campaign.php
// Đổi 'pending' thành 'active'
VALUES (..., 'active', ...)
```

---

## ✅ CHECKLIST HOÀN THÀNH

- [x] Database tables & views
- [x] Triggers tự động cập nhật
- [x] Trang danh sách chiến dịch
- [x] Trang chi tiết chiến dịch
- [x] Trang tạo chiến dịch
- [x] Trang quyên góp vào chiến dịch
- [x] Đăng ký tình nguyện viên
- [x] API endpoints
- [ ] Admin panel (cần làm)
- [ ] Email notifications (optional)

---

## 🐛 TROUBLESHOOTING

### Lỗi: "Table doesn't exist"
```sql
-- Chạy lại file SQL
SOURCE database/campaigns_update.sql;
```

### Lỗi: Tiến độ không cập nhật
```sql
-- Kiểm tra trigger
SHOW TRIGGERS LIKE '%campaign%';

-- Chạy lại trigger từ campaigns_update.sql
```

### Lỗi: Không thấy chiến dịch
```sql
-- Kiểm tra status
SELECT * FROM campaigns WHERE status = 'active';

-- Cập nhật status
UPDATE campaigns SET status = 'active' WHERE campaign_id = 1;
```

---

## 📞 SUPPORT

Nếu cần hỗ trợ:
1. Kiểm tra file `test-database.php`
2. Xem logs: `logs/php_errors.log`
3. Check console browser (F12)

---

**Made with ❤️ by Goodwill Vietnam Team**
