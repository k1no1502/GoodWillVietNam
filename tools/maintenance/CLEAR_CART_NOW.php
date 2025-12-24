<?php
require_once 'config/database.php';

echo "<h1>🚨 CLEAR CART NOW</h1>";

try {
    // XÓA SẠCH CART
    echo "<h2>1. XÓA SẠCH CART</h2>";
    Database::execute("DELETE FROM cart");
    Database::execute("ALTER TABLE cart AUTO_INCREMENT = 1");
    echo "<p style='color: green; font-size: 20px;'>✅ ĐÃ XÓA SẠCH CART</p>";
    
    // THÊM ITEM VÀO CART VỚI QUANTITY = 1
    echo "<h2>2. THÊM ITEM VÀO CART VỚI QUANTITY = 1</h2>";
    $firstItem = Database::fetch("SELECT item_id FROM inventory WHERE is_for_sale = TRUE AND status = 'available' LIMIT 1");
    if ($firstItem) {
        $itemId = $firstItem['item_id'];
        Database::execute("INSERT INTO cart (user_id, item_id, quantity, created_at) VALUES (1, ?, 1, NOW())", [$itemId]);
        echo "<p style='color: green; font-size: 20px;'>✅ ĐÃ THÊM VÀO CART VỚI QUANTITY = 1</p>";
        
        // Kiểm tra
        $cartItem = Database::fetch("SELECT * FROM cart WHERE user_id = 1");
        echo "<p>Cart item: " . json_encode($cartItem) . "</p>";
        
        if ($cartItem['quantity'] == 1) {
            echo "<p style='color: green; font-size: 20px; font-weight: bold;'>🎉 QUANTITY = 1 - ĐÚNG RỒI!</p>";
        } else {
            echo "<p style='color: red; font-size: 20px; font-weight: bold;'>❌ QUANTITY = {$cartItem['quantity']} - VẪN SAI!</p>";
        }
    }
    
    echo "<h1 style='color: green;'>✅ HOÀN THÀNH!</h1>";
    echo "<p style='font-size: 20px;'><strong>Bây giờ hãy:</strong></p>";
    echo "<ol style='font-size: 18px;'>";
    echo "<li>Vào <a href='cart.php' target='_blank'>http://localhost/Cap%201%20-%202/cart.php</a></li>";
    echo "<li>Nhấn Ctrl+F5 để hard refresh</li>";
    echo "<li>Kiểm tra value trong input field = 1 (không còn 100)</li>";
    echo "<li>Kiểm tra nút 'Tham gia Tình nguyện' trong header</li>";
    echo "<li>Vào <a href='volunteer.php' target='_blank'>http://localhost/Cap%201%20-%202/volunteer.php</a></li>";
    echo "</ol>";
    
} catch (Exception $e) {
    echo "<p style='color: red; font-size: 20px;'>Lỗi: " . $e->getMessage() . "</p>";
}
?>
