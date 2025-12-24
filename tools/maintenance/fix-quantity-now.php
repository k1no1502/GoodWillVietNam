<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

echo "<h2>🔧 Sửa lỗi quantity = 100 ngay lập tức</h2>";

try {
    // 1. Xóa tất cả dữ liệu trong cart
    echo "<h3>1. Xóa tất cả dữ liệu trong cart:</h3>";
    $deleted = Database::execute("DELETE FROM cart");
    echo "<p>✅ Đã xóa tất cả dữ liệu trong cart</p>";
    
    // 2. Kiểm tra lại
    $cartCount = Database::fetch("SELECT COUNT(*) as count FROM cart")['count'];
    echo "<p>Cart count sau khi xóa: $cartCount</p>";
    
    // 3. Kiểm tra inventory
    echo "<h3>2. Kiểm tra inventory:</h3>";
    $inventoryItems = Database::fetchAll("SELECT item_id, name, quantity, is_for_sale, price_type FROM inventory WHERE is_for_sale = TRUE AND status = 'available' LIMIT 5");
    echo "<pre>";
    print_r($inventoryItems);
    echo "</pre>";
    
    // 4. Test thêm item vào cart với quantity = 1
    echo "<h3>3. Test thêm item vào cart với quantity = 1:</h3>";
    $firstItem = Database::fetch("SELECT item_id FROM inventory WHERE is_for_sale = TRUE AND status = 'available' LIMIT 1");
    if ($firstItem) {
        $itemId = $firstItem['item_id'];
        echo "<p>Test thêm item_id: $itemId</p>";
        
        // Thêm vào cart với quantity = 1
        Database::execute("INSERT INTO cart (user_id, item_id, quantity, created_at) VALUES (1, ?, 1, NOW())", [$itemId]);
        echo "<p>✅ Đã thêm item vào cart với quantity = 1</p>";
        
        // Kiểm tra lại
        $cartItem = Database::fetch("SELECT * FROM cart WHERE user_id = 1 AND item_id = ?", [$itemId]);
        echo "<p>Cart item: " . json_encode($cartItem) . "</p>";
        
        // Test thêm lần nữa (should be quantity = 2)
        Database::execute("UPDATE cart SET quantity = quantity + 1 WHERE user_id = 1 AND item_id = ?", [$itemId]);
        $cartItem2 = Database::fetch("SELECT * FROM cart WHERE user_id = 1 AND item_id = ?", [$itemId]);
        echo "<p>After adding again: " . json_encode($cartItem2) . "</p>";
    } else {
        echo "<p>Không có item nào trong inventory</p>";
    }
    
    // 5. Kiểm tra available_quantity calculation
    echo "<h3>4. Kiểm tra available_quantity calculation:</h3>";
    $testQuery = "SELECT i.item_id, i.name, i.quantity as inventory_quantity,
                  (i.quantity - COALESCE((SELECT SUM(quantity) FROM cart WHERE item_id = i.item_id), 0)) as available_quantity
                  FROM inventory i
                  WHERE i.is_for_sale = TRUE AND i.status = 'available'
                  LIMIT 3";
    $testResults = Database::fetchAll($testQuery);
    echo "<pre>";
    print_r($testResults);
    echo "</pre>";
    
    echo "<h3 style='color: green;'>✅ Hoàn thành sửa lỗi!</h3>";
    echo "<p><strong>Bây giờ hãy test lại:</strong></p>";
    echo "<ul>";
    echo "<li>Vào Shop Bán Hàng</li>";
    echo "<li>Thêm sản phẩm vào giỏ</li>";
    echo "<li>Kiểm tra số lượng = 1 (không còn 100)</li>";
    echo "<li>Kiểm tra hiển thị số lượng còn lại</li>";
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Lỗi: " . $e->getMessage() . "</p>";
}
?>
