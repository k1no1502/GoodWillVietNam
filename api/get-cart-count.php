<?php
session_start();
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode([
        'success' => false,
        'message' => 'User not logged in',
        'count' => 0
    ]);
    exit();
}

try {
    // Get cart count
    $cartCount = Database::fetch(
        "SELECT COUNT(*) as count FROM cart WHERE user_id = ?",
        [$_SESSION['user_id']]
    )['count'];
    
    echo json_encode([
        'success' => true,
        'count' => $cartCount
    ]);
    
} catch (Exception $e) {
    error_log("Get cart count error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'count' => 0
    ]);
}
?>