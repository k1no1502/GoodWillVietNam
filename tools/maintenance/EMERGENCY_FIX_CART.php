<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

echo "<h1>🚨 EMERGENCY FIX CART - SỬA NGAY LẬP TỨC</h1>";

try {
    // 1. XÓA SẠCH TẤT CẢ CART
    echo "<h2>1. XÓA SẠCH TẤT CẢ CART</h2>";
    Database::execute("DELETE FROM cart");
    echo "<p style='color: green; font-size: 18px;'>✅ ĐÃ XÓA SẠCH TẤT CẢ CART</p>";
    
    // 2. RESET AUTO_INCREMENT
    echo "<h2>2. RESET AUTO_INCREMENT</h2>";
    Database::execute("ALTER TABLE cart AUTO_INCREMENT = 1");
    echo "<p style='color: green; font-size: 18px;'>✅ ĐÃ RESET AUTO_INCREMENT</p>";
    
    // 3. KIỂM TRA INVENTORY
    echo "<h2>3. KIỂM TRA INVENTORY</h2>";
    $inventoryItems = Database::fetchAll("SELECT item_id, name, quantity FROM inventory WHERE is_for_sale = TRUE AND status = 'available' LIMIT 3");
    echo "<pre>";
    print_r($inventoryItems);
    echo "</pre>";
    
    // 4. THÊM ITEM VÀO CART VỚI QUANTITY = 1
    echo "<h2>4. THÊM ITEM VÀO CART VỚI QUANTITY = 1</h2>";
    if (!empty($inventoryItems)) {
        $itemId = $inventoryItems[0]['item_id'];
        echo "<p>Thêm item_id: $itemId với quantity = 1</p>";
        
        // Thêm vào cart
        Database::execute("INSERT INTO cart (user_id, item_id, quantity, created_at) VALUES (1, ?, 1, NOW())", [$itemId]);
        echo "<p style='color: green; font-size: 18px;'>✅ ĐÃ THÊM VÀO CART VỚI QUANTITY = 1</p>";
        
        // Kiểm tra
        $cartItem = Database::fetch("SELECT * FROM cart WHERE user_id = 1");
        echo "<p>Cart item: " . json_encode($cartItem) . "</p>";
        
        if ($cartItem['quantity'] == 1) {
            echo "<p style='color: green; font-size: 20px; font-weight: bold;'>🎉 QUANTITY = 1 - ĐÚNG RỒI!</p>";
        } else {
            echo "<p style='color: red; font-size: 20px; font-weight: bold;'>❌ QUANTITY = {$cartItem['quantity']} - VẪN SAI!</p>";
        }
    }
    
    // 5. TEST TĂNG QUANTITY
    echo "<h2>5. TEST TĂNG QUANTITY</h2>";
    if (!empty($inventoryItems)) {
        Database::execute("UPDATE cart SET quantity = quantity + 1 WHERE user_id = 1");
        $cartItem2 = Database::fetch("SELECT * FROM cart WHERE user_id = 1");
        echo "<p>After +1: " . json_encode($cartItem2) . "</p>";
        
        if ($cartItem2['quantity'] == 2) {
            echo "<p style='color: green; font-size: 20px; font-weight: bold;'>🎉 QUANTITY = 2 - ĐÚNG RỒI!</p>";
        } else {
            echo "<p style='color: red; font-size: 20px; font-weight: bold;'>❌ QUANTITY = {$cartItem2['quantity']} - VẪN SAI!</p>";
        }
    }
    
    // 6. KIỂM TRA CART TABLE STRUCTURE
    echo "<h2>6. KIỂM TRA CART TABLE STRUCTURE</h2>";
    $tableInfo = Database::fetchAll("DESCRIBE cart");
    echo "<pre>";
    print_r($tableInfo);
    echo "</pre>";
    
    echo "<h1 style='color: green;'>✅ HOÀN THÀNH EMERGENCY FIX!</h1>";
    echo "<p style='font-size: 18px;'><strong>Bây giờ hãy:</strong></p>";
    echo "<ol style='font-size: 16px;'>";
    echo "<li>Vào <a href='cart.php' target='_blank'>http://localhost/Cap%201%20-%202/cart.php</a></li>";
    echo "<li>Nhấn Ctrl+F5 để hard refresh</li>";
    echo "<li>Kiểm tra số lượng = 1 (không còn 100)</li>";
    echo "<li>Test nút tăng/giảm số lượng</li>";
    echo "</ol>";
    
    echo "<p style='color: red; font-size: 16px;'><strong>Nếu vẫn lỗi, hãy:</strong></p>";
    echo "<ul style='font-size: 16px;'>";
    echo "<li>Đăng xuất và đăng nhập lại</li>";
    echo "<li>Xóa cache trình duyệt</li>";
    echo "<li>Mở Incognito/Private mode</li>";
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<p style='color: red; font-size: 18px;'>Lỗi: " . $e->getMessage() . "</p>";
}
?>
