<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

echo "<h2>🔧 SỬA LỖI QUANTITY = 100 TRONG GIỎ HÀNG</h2>";

try {
    // 1. Kiểm tra dữ liệu hiện tại trong cart
    echo "<h3>1. Kiểm tra dữ liệu hiện tại trong cart:</h3>";
    $cartItems = Database::fetchAll("SELECT * FROM cart ORDER BY created_at DESC LIMIT 10");
    echo "<pre>";
    print_r($cartItems);
    echo "</pre>";
    
    // 2. Tìm và sửa tất cả items có quantity = 100
    echo "<h3>2. Tìm và sửa items có quantity = 100:</h3>";
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
    
    // 3. Tìm và sửa tất cả items có quantity > 10 (có thể là lỗi)
    echo "<h3>3. Tìm và sửa items có quantity > 10:</h3>";
    $suspiciousItems = Database::fetchAll("SELECT * FROM cart WHERE quantity > 10");
    
    if (!empty($suspiciousItems)) {
        echo "<p style='color: orange;'>Tìm thấy " . count($suspiciousItems) . " items có quantity > 10:</p>";
        echo "<pre>";
        print_r($suspiciousItems);
        echo "</pre>";
        
        // Sửa quantity về 1
        echo "<h4>Sửa quantity về 1:</h4>";
        foreach ($suspiciousItems as $item) {
            Database::execute("UPDATE cart SET quantity = 1 WHERE cart_id = ?", [$item['cart_id']]);
            echo "<p>✅ Đã sửa cart_id {$item['cart_id']} từ {$item['quantity']} về 1</p>";
        }
    } else {
        echo "<p style='color: green;'>Không tìm thấy items có quantity > 10</p>";
    }
    
    // 4. Kiểm tra lại sau khi sửa
    echo "<h3>4. Kiểm tra lại sau khi sửa:</h3>";
    $cartItemsAfter = Database::fetchAll("SELECT * FROM cart ORDER BY created_at DESC LIMIT 10");
    echo "<pre>";
    print_r($cartItemsAfter);
    echo "</pre>";
    
    // 5. Kiểm tra available_quantity calculation
    echo "<h3>5. Kiểm tra available_quantity calculation:</h3>";
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
    
    // 6. Test cập nhật quantity
    echo "<h3>6. Test cập nhật quantity:</h3>";
    $firstCartItem = Database::fetch("SELECT * FROM cart WHERE user_id = 1 LIMIT 1");
    if ($firstCartItem) {
        $cartId = $firstCartItem['cart_id'];
        $currentQty = $firstCartItem['quantity'];
        
        echo "<p>Test cập nhật cart_id: $cartId, current quantity: $currentQty</p>";
        
        // Test tăng quantity
        Database::execute("UPDATE cart SET quantity = quantity + 1 WHERE cart_id = ?", [$cartId]);
        $updatedItem = Database::fetch("SELECT * FROM cart WHERE cart_id = ?", [$cartId]);
        echo "<p>After +1: " . json_encode($updatedItem) . "</p>";
        
        // Test giảm quantity
        Database::execute("UPDATE cart SET quantity = quantity - 1 WHERE cart_id = ?", [$cartId]);
        $updatedItem2 = Database::fetch("SELECT * FROM cart WHERE cart_id = ?", [$cartId]);
        echo "<p>After -1: " . json_encode($updatedItem2) . "</p>";
    } else {
        echo "<p>Không có item nào trong cart để test</p>";
    }
    
    echo "<h3 style='color: green;'>✅ HOÀN THÀNH SỬA LỖI!</h3>";
    echo "<p><strong>Bây giờ hãy test lại:</strong></p>";
    echo "<ul>";
    echo "<li>Vào Giỏ hàng</li>";
    echo "<li>Kiểm tra số lượng không còn 100</li>";
    echo "<li>Test nút tăng/giảm số lượng</li>";
    echo "<li>Kiểm tra available_quantity</li>";
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Lỗi: " . $e->getMessage() . "</p>";
}
?>
