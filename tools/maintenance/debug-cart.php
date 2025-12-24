<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

echo "<h2>Debug Cart - Kiểm tra lỗi quantity = 100</h2>";

try {
    // 1. Kiểm tra dữ liệu trong cart
    echo "<h3>1. Dữ liệu trong cart:</h3>";
    $cartItems = Database::fetchAll("SELECT * FROM cart ORDER BY created_at DESC LIMIT 5");
    echo "<pre>";
    print_r($cartItems);
    echo "</pre>";
    
    // 2. Kiểm tra inventory
    echo "<h3>2. Dữ liệu inventory:</h3>";
    $inventoryItems = Database::fetchAll("SELECT item_id, name, quantity, is_for_sale, price_type FROM inventory WHERE is_for_sale = TRUE LIMIT 5");
    echo "<pre>";
    print_r($inventoryItems);
    echo "</pre>";
    
    // 3. Tìm items có quantity = 100
    echo "<h3>3. Tìm items có quantity = 100:</h3>";
    $badItems = Database::fetchAll("SELECT * FROM cart WHERE quantity = 100");
    if (!empty($badItems)) {
        echo "<p style='color: red;'>Tìm thấy " . count($badItems) . " items có quantity = 100:</p>";
        echo "<pre>";
        print_r($badItems);
        echo "</pre>";
        
        // Sửa quantity về 1
        echo "<h4>Sửa quantity về 1:</h4>";
        foreach ($badItems as $item) {
            Database::execute("UPDATE cart SET quantity = 1 WHERE cart_id = ?", [$item['cart_id']]);
            echo "<p>✅ Đã sửa cart_id {$item['cart_id']} từ {$item['quantity']} về 1</p>";
        }
    } else {
        echo "<p style='color: green;'>Không tìm thấy items có quantity = 100</p>";
    }
    
    // 4. Xóa tất cả cart để test lại
    echo "<h3>4. Xóa tất cả cart để test lại:</h3>";
    $deleted = Database::execute("DELETE FROM cart");
    echo "<p>✅ Đã xóa tất cả dữ liệu trong cart</p>";
    
    // 5. Test thêm item vào cart
    echo "<h3>5. Test thêm item vào cart:</h3>";
    $firstItem = Database::fetch("SELECT item_id FROM inventory WHERE is_for_sale = TRUE AND status = 'available' LIMIT 1");
    if ($firstItem) {
        $itemId = $firstItem['item_id'];
        echo "<p>Test thêm item_id: $itemId</p>";
        
        // Thêm vào cart
        Database::execute("INSERT INTO cart (user_id, item_id, quantity, created_at) VALUES (1, ?, 1, NOW())", [$itemId]);
        echo "<p>✅ Đã thêm item vào cart với quantity = 1</p>";
        
        // Kiểm tra lại
        $cartItem = Database::fetch("SELECT * FROM cart WHERE user_id = 1 AND item_id = ?", [$itemId]);
        echo "<p>Cart item: " . json_encode($cartItem) . "</p>";
    } else {
        echo "<p>Không có item nào trong inventory</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Lỗi: " . $e->getMessage() . "</p>";
}
?>
