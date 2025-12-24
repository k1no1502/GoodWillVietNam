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
    $campaign_id = (int)($input['campaign_id'] ?? 0);
    
    if ($campaign_id <= 0) {
        throw new Exception('Chiến dịch không hợp lệ.');
    }
    
    // Check if campaign exists and is active
    $campaign = Database::fetch(
        "SELECT * FROM campaigns WHERE campaign_id = ? AND status = 'active'",
        [$campaign_id]
    );
    
    if (!$campaign) {
        throw new Exception('Chiến dịch không tồn tại hoặc đã kết thúc.');
    }
    
    // Check if already registered
    $existing = Database::fetch(
        "SELECT * FROM campaign_volunteers WHERE campaign_id = ? AND user_id = ?",
        [$campaign_id, $_SESSION['user_id']]
    );
    
    if ($existing) {
        throw new Exception('Bạn đã đăng ký cho chiến dịch này rồi.');
    }
    
    // Insert volunteer registration
    Database::execute(
        "INSERT INTO campaign_volunteers (campaign_id, user_id, status, created_at) 
         VALUES (?, ?, 'approved', NOW())",
        [$campaign_id, $_SESSION['user_id']]
    );
    
    logActivity($_SESSION['user_id'], 'register_volunteer', "Registered as volunteer for campaign #$campaign_id");
    
    echo json_encode([
        'success' => true,
        'message' => 'Đăng ký tình nguyện viên thành công!'
    ]);
    
} catch (Exception $e) {
    error_log("Register volunteer error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
