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
    $campaign_id = (int)($_POST['campaign_id'] ?? 0);
    $skills = sanitize($_POST['skills'] ?? '');
    $availability = sanitize($_POST['availability'] ?? '');
    $role = sanitize($_POST['role'] ?? '');
    $message = sanitize($_POST['message'] ?? '');
    
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
    
    // Insert volunteer registration with detailed info
    // Check if role column exists
    $columns = Database::fetchAll("SHOW COLUMNS FROM campaign_volunteers LIKE 'role'");
    if (!empty($columns)) {
        Database::execute(
            "INSERT INTO campaign_volunteers (campaign_id, user_id, skills, availability, role, message, status, created_at) 
             VALUES (?, ?, ?, ?, ?, ?, 'approved', NOW())",
            [$campaign_id, $_SESSION['user_id'], $skills, $availability, $role, $message]
        );
    } else {
        Database::execute(
            "INSERT INTO campaign_volunteers (campaign_id, user_id, skills, availability, message, status, created_at) 
             VALUES (?, ?, ?, ?, ?, 'approved', NOW())",
            [$campaign_id, $_SESSION['user_id'], $skills, $availability, $message]
        );
    }
    
    logActivity($_SESSION['user_id'], 'register_volunteer', "Registered as volunteer for campaign #$campaign_id with details");
    
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
