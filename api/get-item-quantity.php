<?php
session_start();
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/functions.php';

try {
    $item_id = (int)($_GET['item_id'] ?? 0);
    
    if ($item_id <= 0) {
        throw new Exception('Item ID khÃ´ng há»£p lá».');
    }
    
    // Get item with available quantity (dÃ¹ng tá»n kho trá»±c tiáº¿p)
    $item = Database::fetch(
        "SELECT i.*,
                GREATEST(i.quantity - COALESCE((SELECT SUM(quantity) FROM cart WHERE item_id = i.item_id), 0), 0) as available_quantity
         FROM inventory i
         WHERE i.item_id = ? AND i.status = 'available' AND i.is_for_sale = TRUE",
        [$item_id]
    );
    
    if (!$item) {
        throw new Exception('Item khÃ´ng tá»n táº¡i hoáº·c khÃ´ng cÃ³ sáºµn.');
    }
    
    echo json_encode([
        'success' => true,
        'available_quantity' => max(0, $item['available_quantity']),
        'unit' => $item['unit'] ?? 'CÃ¡i',
        'item_name' => $item['name']
    ]);
    
} catch (Exception $e) {
    error_log("Get item quantity error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
