<?php
session_start();
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode([
        'success' => false,
        'message' => 'Vui lòng đăng nhập để xóa sản phẩm.'
    ]);
    exit();
}

try {
    // Get POST data
    $input = json_decode(file_get_contents('php://input'), true);
    $cart_id = (int)($input['cart_id'] ?? 0);
    
    if ($cart_id <= 0) {
        throw new Exception('Dữ liệu không hợp lệ.');
    }
    
    // Check if cart item exists and belongs to user
    $cartItem = Database::fetch(
        "SELECT * FROM cart WHERE cart_id = ? AND user_id = ?",
        [$cart_id, $_SESSION['user_id']]
    );
    
    if (!$cartItem) {
        throw new Exception('Sản phẩm không tồn tại trong giỏ hàng.');
    }
    
    // Remove from cart
    Database::execute(
        "DELETE FROM cart WHERE cart_id = ?",
        [$cart_id]
    );
    
    // Log activity
    logActivity($_SESSION['user_id'], 'remove_from_cart', "Removed item #{$cartItem['item_id']} from cart");
    
    echo json_encode([
        'success' => true,
        'message' => 'Đã xóa sản phẩm khỏi giỏ hàng!'
    ]);
    
} catch (Exception $e) {
    error_log("Remove from cart error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>