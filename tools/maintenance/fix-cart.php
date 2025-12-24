<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

echo "<h2>Kiểm tra và sửa lỗi giỏ hàng</h2>";

try {
    // 1. Kiểm tra dữ liệu trong cart
    echo "<h3>1. Dữ liệu trong bảng cart:</h3>";
    $cartItems = Database::fetchAll("SELECT * FROM cart ORDER BY created_at DESC LIMIT 10");
    echo "<pre>";
    print_r($cartItems);
    echo "</pre>";
    
    // 2. Kiểm tra dữ liệu trong inventory
    echo "<h3>2. Dữ liệu trong bảng inventory:</h3>";
    $inventoryItems = Database::fetchAll("SELECT item_id, name, quantity, status FROM inventory WHERE is_for_sale = TRUE LIMIT 10");
    echo "<pre>";
    print_r($inventoryItems);
    echo "</pre>";
    
    // 3. Tìm các item có quantity > 100 trong cart
    echo "<h3>3. Tìm các item có quantity > 100:</h3>";
    $badItems = Database::fetchAll("SELECT * FROM cart WHERE quantity > 100");
    if (!empty($badItems)) {
        echo "<p style='color: red;'>Tìm thấy " . count($badItems) . " item có quantity > 100:</p>";
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
        echo "<p style='color: green;'>Không tìm thấy item nào có quantity > 100</p>";
    }
    
    // 4. Kiểm tra lại sau khi sửa
    echo "<h3>4. Kiểm tra lại sau khi sửa:</h3>";
    $cartItemsAfter = Database::fetchAll("SELECT * FROM cart ORDER BY created_at DESC LIMIT 10");
    echo "<pre>";
    print_r($cartItemsAfter);
    echo "</pre>";
    
    // 5. Kiểm tra available_quantity calculation
    echo "<h3>5. Kiểm tra tính toán available_quantity:</h3>";
    $testQuery = "SELECT c.*, i.quantity as inventory_quantity,
                  (i.quantity - COALESCE((SELECT SUM(quantity) FROM cart WHERE item_id = i.item_id AND cart_id != c.cart_id), 0)) as available_quantity
                  FROM cart c
                  JOIN inventory i ON c.item_id = i.item_id
                  WHERE c.user_id = 1
                  LIMIT 5";
    $testResults = Database::fetchAll($testQuery);
    echo "<pre>";
    print_r($testResults);
    echo "</pre>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Lỗi: " . $e->getMessage() . "</p>";
}
?>
