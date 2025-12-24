<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/functions.php';

try {
    $stats = getStatistics();
    
    echo json_encode([
        'success' => true,
        'data' => $stats
    ]);
    
} catch (Exception $e) {
    error_log("Error getting statistics: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi tải thống kê',
        'data' => [
            'users' => 0,
            'donations' => 0,
            'items' => 0,
            'campaigns' => 0
        ]
    ]);
}
?>
