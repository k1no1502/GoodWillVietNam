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
    Database::execute("DELETE FROM cart WHERE user_id = ?", [$_SESSION['user_id']]);
    
    logActivity($_SESSION['user_id'], 'clear_cart', 'Cleared shopping cart');
    
    echo json_encode(['success' => true, 'message' => 'Đã xóa tất cả vật phẩm.']);
    
} catch (Exception $e) {
    error_log("Clear cart error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Có lỗi xảy ra.']);
}
?>
