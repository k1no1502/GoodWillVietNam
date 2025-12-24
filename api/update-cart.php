<?php
session_start();
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập.']);
    exit();
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $cart_id = (int)($input['cart_id'] ?? 0);
    $action = $input['action'] ?? '';
    
    if ($cart_id <= 0) {
        throw new Exception('Giỏ hàng không hợp lệ.');
    }
    
    // Verify cart belongs to user
    $cartItem = Database::fetch(
        "SELECT * FROM cart WHERE cart_id = ? AND user_id = ?",
        [$cart_id, $_SESSION['user_id']]
    );
    
    if (!$cartItem) {
        throw new Exception('Không tìm thấy vật phẩm trong giỏ hàng.');
    }
    
    if ($action === 'increase') {
        Database::execute(
            "UPDATE cart SET quantity = quantity + 1, updated_at = NOW() WHERE cart_id = ?",
            [$cart_id]
        );
    } elseif ($action === 'decrease') {
        if ($cartItem['quantity'] <= 1) {
            // Remove item if quantity is 1
            Database::execute("DELETE FROM cart WHERE cart_id = ?", [$cart_id]);
        } else {
            Database::execute(
                "UPDATE cart SET quantity = quantity - 1, updated_at = NOW() WHERE cart_id = ?",
                [$cart_id]
            );
        }
    } else {
        throw new Exception('Hành động không hợp lệ.');
    }
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    error_log("Update cart error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
