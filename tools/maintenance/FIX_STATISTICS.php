<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

echo "<h1>🔍 KIỂM TRA VÀ FIX STATISTICS</h1>";

try {
    echo "<h2>1. KIỂM TRA KẾT NỐI DATABASE</h2>";
    Database::fetch("SELECT 1");
    echo "<p style='color:green;font-size:18px;'>✅ Database connection OK</p>";

    echo "<h2>2. THỐNG KÊ SỐ BẢNG HIỆN CÓ</h2>";
    $tables = ['users', 'categories', 'donations', 'inventory', 'campaigns'];
    foreach ($tables as $table) {
        try {
            $data = Database::fetch("SELECT COUNT(*) AS total FROM $table");
            $total = $data['total'] ?? 0;
            echo "<p><strong>$table</strong>: $total bản ghi</p>";
        } catch (Exception $tableError) {
            echo "<p style='color:red;'><strong>$table</strong>: " . $tableError->getMessage() . "</p>";
        }
    }

    echo "<h2>3. KIỂM TRA HÀM getStatistics()</h2>";
    echo '<pre>' . print_r(getStatistics(), true) . '</pre>';

    echo "<h2>4. THÊM DỮ LIỆU MẪU NẾU THIẾU</h2>";

    // Seed users
    $userCount = Database::fetch("SELECT COUNT(*) AS total FROM users")['total'] ?? 0;
    if ($userCount == 0) {
        echo "<p>➕ Thêm người dùng mẫu...</p>";
        Database::execute(
            "INSERT INTO users (name, email, password, phone, address, role_id, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'active', NOW())",
            [
                'Administrator',
                'admin@goodwill.com',
                hashPassword('123456'),
                '0123456789',
                'Hà Nội',
                1
            ]
        );
        echo "<p style='color:green;'>✅ Đã thêm admin (email: admin@goodwill.com / pass: 123456)</p>";
    }

    // Seed categories
    $categoryCount = Database::fetch("SELECT COUNT(*) AS total FROM categories")['total'] ?? 0;
    if ($categoryCount == 0) {
        echo "<p>➕ Thêm danh mục mẫu...</p>";
        $categorySeeds = [
            ['Quần áo', 'bi bi-bag', 1],
            ['Đồ điện tử', 'bi bi-cpu', 2],
            ['Sách vở', 'bi bi-book', 3]
        ];
        foreach ($categorySeeds as [$name, $icon, $sort]) {
            Database::execute(
                "INSERT INTO categories (name, description, icon, status, sort_order, created_at) VALUES (?, ?, ?, 'active', ?, NOW())",
                [$name, $name . ' quyên góp', $icon, $sort]
            );
        }
        echo "<p style='color:green;'>✅ Đã thêm danh mục mẫu</p>";
    }

    $admin = Database::fetch("SELECT user_id FROM users ORDER BY user_id ASC LIMIT 1");
    $adminId = $admin['user_id'] ?? null;
    $category = Database::fetch("SELECT category_id FROM categories ORDER BY category_id ASC LIMIT 1");
    $categoryId = $category['category_id'] ?? null;

    // Seed donations
    if ($adminId && $categoryId) {
        $donationCount = Database::fetch("SELECT COUNT(*) AS total FROM donations")['total'] ?? 0;
        if ($donationCount == 0) {
            echo "<p>➕ Thêm donation mẫu...</p>";
            Database::execute(
                "INSERT INTO donations (user_id, item_name, description, category_id, quantity, unit, condition_status, estimated_value, images, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'approved', NOW())",
                [
                    $adminId,
                    'Áo ấm mùa đông',
                    'Quyên góp 10 chiếc áo ấm cho trẻ em miền núi',
                    $categoryId,
                    10,
                    'chiếc',
                    'good',
                    1500000,
                    json_encode([])
                ]
            );
            echo "<p style='color:green;'>✅ Đã thêm donation mẫu</p>";
        }
    }

    // Seed inventory dựa trên donation đầu tiên
    $donation = Database::fetch("SELECT donation_id, category_id FROM donations ORDER BY donation_id ASC LIMIT 1");
    $donationId = $donation['donation_id'] ?? null;
    if ($donationId) {
        $inventoryCount = Database::fetch("SELECT COUNT(*) AS total FROM inventory")['total'] ?? 0;
        if ($inventoryCount == 0) {
            echo "<p>➕ Thêm inventory mẫu...</p>";
            Database::execute(
                "INSERT INTO inventory (donation_id, name, description, category_id, quantity, unit, condition_status, estimated_value, actual_value, images, location, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'available', NOW())",
                [
                    $donationId,
                    'Áo ấm mùa đông',
                    '10 chiếc áo ấm đã nhập kho',
                    $donation['category_id'],
                    10,
                    'chiếc',
                    'good',
                    1500000,
                    0,
                    json_encode([]),
                    'Kho Hà Nội'
                ]
            );
            echo "<p style='color:green;'>✅ Đã thêm inventory mẫu</p>";
        }
    }

    // Seed campaigns
    if ($adminId) {
        $campaignCount = Database::fetch("SELECT COUNT(*) AS total FROM campaigns")['total'] ?? 0;
        if ($campaignCount == 0) {
            echo "<p>➕ Thêm campaign mẫu...</p>";
            Database::execute(
                "INSERT INTO campaigns (name, description, image, start_date, end_date, target_amount, current_amount, target_items, current_items, status, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, NOW())",
                [
                    'Chiến dịch Áo ấm vùng cao',
                    'Kêu gọi ủng hộ áo ấm cho trẻ em vùng cao',
                    null,
                    date('Y-m-d'),
                    date('Y-m-d', strtotime('+30 days')),
                    15000000,
                    5000000,
                    500,
                    120,
                    $adminId
                ]
            );
            echo "<p style='color:green;'>✅ Đã thêm campaign mẫu</p>";
        }
    }

    echo "<h2>5. KẾT QUẢ SAU CÙNG</h2>";
    echo '<pre>' . print_r(getStatistics(), true) . '</pre>';
    echo "<h3 style='color:green;'>🎉 Hoàn tất! Hãy quay lại trang chủ và refresh để xem thống kê.</h3>";

} catch (Exception $e) {
    echo "<p style='color:red;font-size:18px;'>Lỗi: " . $e->getMessage() . "</p>";
}
?>
