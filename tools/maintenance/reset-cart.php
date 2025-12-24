<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

echo "<h2>Reset và sửa lỗi giỏ hàng</h2>";

try {
    // 1. Xóa tất cả dữ liệu trong cart
    echo "<h3>1. Xóa tất cả dữ liệu trong cart:</h3>";
    $deleted = Database::execute("DELETE FROM cart");
    echo "<p>✅ Đã xóa tất cả dữ liệu trong cart</p>";
    
    // 2. Kiểm tra lại
    echo "<h3>2. Kiểm tra lại cart (sau khi xóa):</h3>";
    $cartCount = Database::fetch("SELECT COUNT(*) as count FROM cart")['count'];
    echo "<p>Cart count: $cartCount</p>";
    
    // 3. Kiểm tra inventory
    echo "<h3>3. Kiểm tra inventory:</h3>";
    $inventoryCount = Database::fetch("SELECT COUNT(*) as count FROM inventory WHERE is_for_sale = TRUE AND status = 'available'")['count'];
    echo "<p>Available items: $inventoryCount</p>";
    
    // 4. Test thêm item vào cart
    echo "<h3>4. Test thêm item vào cart:</h3>";
    $firstItem = Database::fetch("SELECT item_id FROM inventory WHERE is_for_sale = TRUE AND status = 'available' LIMIT 1");
    if ($firstItem) {
        $itemId = $firstItem['item_id'];
        echo "<p>Test thêm item_id: $itemId</p>";
        
        // Thêm vào cart
        Database::execute("INSERT INTO cart (user_id, item_id, quantity, created_at) VALUES (1, ?, 1, NOW())", [$itemId]);
        echo "<p>✅ Đã thêm item vào cart</p>";
        
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
