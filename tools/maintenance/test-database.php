<?php
/**
 * Script kiểm tra database
 * Chạy file này để test kết nối và dữ liệu
 */

require_once 'config/database.php';
require_once 'includes/functions.php';

echo "<!DOCTYPE html>
<html lang='vi'>
<head>
    <meta charset='UTF-8'>
    <title>Test Database - Goodwill Vietnam</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
    <style>
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
    </style>
</head>
<body>
<div class='container mt-5'>
    <h1>🔍 Kiểm tra Database</h1>
    <hr>
";

echo "<h2>1. Kết nối Database</h2>";
try {
    $pdo = Database::getConnection();
    echo "<p class='success'>✅ Kết nối thành công!</p>";
} catch (Exception $e) {
    echo "<p class='error'>❌ Lỗi kết nối: " . $e->getMessage() . "</p>";
    exit;
}

echo "<h2>2. Kiểm tra các bảng</h2>";
$tables = ['users', 'donations', 'inventory', 'categories', 'orders', 'cart', 'campaigns'];
foreach ($tables as $table) {
    try {
        $count = Database::fetch("SELECT COUNT(*) as count FROM $table")['count'];
        echo "<p class='success'>✅ Bảng <strong>$table</strong>: $count bản ghi</p>";
    } catch (Exception $e) {
        echo "<p class='error'>❌ Bảng <strong>$table</strong>: " . $e->getMessage() . "</p>";
    }
}

echo "<h2>3. Kiểm tra cột inventory</h2>";
try {
    $columns = Database::fetchAll("SHOW COLUMNS FROM inventory");
    echo "<table class='table table-sm'>";
    echo "<tr><th>Cột</th><th>Kiểu</th><th>Null</th><th>Mặc định</th></tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td>" . $col['Field'] . "</td>";
        echo "<td>" . $col['Type'] . "</td>";
        echo "<td>" . $col['Null'] . "</td>";
        echo "<td>" . $col['Default'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "<p class='error'>❌ Lỗi: " . $e->getMessage() . "</p>";
}

echo "<h2>4. Thống kê dữ liệu</h2>";

// Donations
$totalDonations = Database::fetch("SELECT COUNT(*) as count FROM donations")['count'];
$approvedDonations = Database::fetch("SELECT COUNT(*) as count FROM donations WHERE status = 'approved'")['count'];
$pendingDonations = Database::fetch("SELECT COUNT(*) as count FROM donations WHERE status = 'pending'")['count'];

echo "<div class='alert alert-info'>";
echo "<strong>Quyên góp:</strong><br>";
echo "- Tổng: $totalDonations<br>";
echo "- Đã duyệt: $approvedDonations<br>";
echo "- Chờ duyệt: $pendingDonations<br>";
echo "</div>";

// Inventory
$totalItems = Database::fetch("SELECT COUNT(*) as count FROM inventory")['count'];
$availableItems = Database::fetch("SELECT COUNT(*) as count FROM inventory WHERE status = 'available'")['count'];
$forSaleItems = Database::fetch("SELECT COUNT(*) as count FROM inventory WHERE is_for_sale = TRUE AND status = 'available'")['count'];
$freeItems = Database::fetch("SELECT COUNT(*) as count FROM inventory WHERE price_type = 'free' AND is_for_sale = TRUE AND status = 'available'")['count'];
$cheapItems = Database::fetch("SELECT COUNT(*) as count FROM inventory WHERE price_type = 'cheap' AND is_for_sale = TRUE AND status = 'available'")['count'];

echo "<div class='alert alert-success'>";
echo "<strong>Kho hàng:</strong><br>";
echo "- Tổng vật phẩm: $totalItems<br>";
echo "- Có sẵn: $availableItems<br>";
echo "- Đang bán: $forSaleItems<br>";
echo "- Miễn phí: $freeItems<br>";
echo "- Giá rẻ: $cheapItems<br>";
echo "</div>";

echo "<h2>5. Vấn đề phát hiện</h2>";

if ($approvedDonations > $totalItems) {
    echo "<div class='alert alert-warning'>";
    echo "⚠️ <strong>Vấn đề:</strong> Có $approvedDonations quyên góp đã duyệt nhưng chỉ có $totalItems vật phẩm trong kho!<br>";
    echo "→ Cần chạy script sync: <code>database/check_and_fix.sql</code>";
    echo "</div>";
    
    echo "<h3>Quyên góp đã duyệt nhưng chưa có trong kho:</h3>";
    $missingItems = Database::fetchAll("
        SELECT d.donation_id, d.item_name, d.status, d.created_at
        FROM donations d
        WHERE d.status = 'approved' 
        AND NOT EXISTS (SELECT 1 FROM inventory i WHERE i.donation_id = d.donation_id)
        ORDER BY d.created_at DESC
    ");
    
    if (!empty($missingItems)) {
        echo "<table class='table table-striped'>";
        echo "<tr><th>ID</th><th>Tên vật phẩm</th><th>Trạng thái</th><th>Ngày tạo</th></tr>";
        foreach ($missingItems as $item) {
            echo "<tr>";
            echo "<td>" . $item['donation_id'] . "</td>";
            echo "<td>" . htmlspecialchars($item['item_name']) . "</td>";
            echo "<td><span class='badge bg-success'>" . $item['status'] . "</span></td>";
            echo "<td>" . $item['created_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        echo "<form method='POST' class='mt-3'>";
        echo "<button type='submit' name='sync_inventory' class='btn btn-warning'>🔄 Sync vật phẩm vào kho ngay</button>";
        echo "</form>";
    }
} else {
    echo "<div class='alert alert-success'>";
    echo "✅ <strong>Tốt!</strong> Tất cả quyên góp đã duyệt đều có trong kho.";
    echo "</div>";
}

// Handle sync
if (isset($_POST['sync_inventory'])) {
    try {
        Database::beginTransaction();
        
        $sql = "INSERT INTO inventory (donation_id, name, description, category_id, quantity, unit, 
                condition_status, estimated_value, actual_value, images, status, price_type, sale_price, is_for_sale, created_at)
                SELECT 
                    d.donation_id, d.item_name, d.description, d.category_id, d.quantity, d.unit,
                    d.condition_status, d.estimated_value, d.estimated_value, d.images,
                    'available', 'free', 0, TRUE, d.created_at
                FROM donations d
                WHERE d.status = 'approved' 
                AND NOT EXISTS (SELECT 1 FROM inventory i WHERE i.donation_id = d.donation_id)";
        
        $stmt = Database::execute($sql);
        $inserted = $stmt->rowCount();
        
        Database::commit();
        
        echo "<div class='alert alert-success mt-3'>";
        echo "✅ Đã sync $inserted vật phẩm vào kho!<br>";
        echo "<a href='test-database.php' class='btn btn-sm btn-primary mt-2'>Kiểm tra lại</a>";
        echo "</div>";
        
    } catch (Exception $e) {
        Database::rollback();
        echo "<div class='alert alert-danger mt-3'>";
        echo "❌ Lỗi sync: " . $e->getMessage();
        echo "</div>";
    }
}

echo "<h2>6. Vật phẩm có thể bán gần đây</h2>";
$shopItems = Database::fetchAll("
    SELECT i.*, c.name as category_name
    FROM inventory i
    LEFT JOIN categories c ON i.category_id = c.category_id
    WHERE i.is_for_sale = TRUE AND i.status = 'available'
    ORDER BY i.created_at DESC
    LIMIT 10
");

if (!empty($shopItems)) {
    echo "<table class='table table-striped'>";
    echo "<tr><th>ID</th><th>Tên</th><th>Danh mục</th><th>Loại giá</th><th>Giá</th><th>Trạng thái</th></tr>";
    foreach ($shopItems as $item) {
        $priceDisplay = $item['price_type'] === 'free' ? 'Miễn phí' : number_format($item['sale_price']) . 'đ';
        $priceClass = $item['price_type'] === 'free' ? 'success' : 'warning';
        echo "<tr>";
        echo "<td>" . $item['item_id'] . "</td>";
        echo "<td>" . htmlspecialchars($item['name']) . "</td>";
        echo "<td>" . htmlspecialchars($item['category_name'] ?? 'N/A') . "</td>";
        echo "<td><span class='badge bg-$priceClass'>" . $item['price_type'] . "</span></td>";
        echo "<td>$priceDisplay</td>";
        echo "<td><span class='badge bg-info'>" . $item['status'] . "</span></td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p class='text-muted'>Chưa có vật phẩm nào trong shop.</p>";
}

echo "<hr>";
echo "<h2>7. Hướng dẫn sửa lỗi</h2>";
echo "<ol>";
echo "<li>Chạy file <code>database/check_and_fix.sql</code> trong phpMyAdmin</li>";
echo "<li>Hoặc click nút <strong>Sync vật phẩm</strong> ở trên (nếu có)</li>";
echo "<li>Kiểm tra lại trang shop: <a href='shop.php' target='_blank'>shop.php</a></li>";
echo "</ol>";

echo "<div class='mt-5 mb-5'>";
echo "<a href='index.php' class='btn btn-primary'>← Về trang chủ</a> ";
echo "<a href='shop.php' class='btn btn-success'>🛒 Xem Shop</a> ";
echo "<a href='admin/dashboard.php' class='btn btn-warning'>⚙️ Admin</a>";
echo "</div>";

echo "</div>
</body>
</html>";
?>
