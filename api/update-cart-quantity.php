<?php
session_start();
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode([
        'success' => false,
        'message' => 'Vui lÃ²ng ÄÄng nháº­p Äá» cáº­p nháº­t giá» hÃ ng.'
    ]);
    exit();
}

try {
    // Get POST data
    $input = json_decode(file_get_contents('php://input'), true);
    $cart_id = (int)($input['cart_id'] ?? 0);
    $quantity = (int)($input['quantity'] ?? 1);
    
    if ($cart_id <= 0 || $quantity <= 0) {
        throw new Exception('Dá»¯ liá»u khÃ´ng há»£p lá».');
    }
    
    // Check if cart item exists and belongs to user
    $cartItem = Database::fetch(
        "SELECT c.*, i.quantity as inventory_quantity 
         FROM cart c 
         JOIN inventory i ON c.item_id = i.item_id 
         WHERE c.cart_id = ? AND c.user_id = ?",
        [$cart_id, $_SESSION['user_id']]
    );
    
    if (!$cartItem) {
        throw new Exception('Sáº£n pháº©m khÃ´ng tá»n táº¡i trong giá» hÃ ng.');
    }
    
    // Check available quantity theo tá»n kho ÄÆ¡n giáº£n
    $availableQuantityRow = Database::fetch(
        "SELECT quantity AS available FROM inventory WHERE item_id = ?",
        [$cartItem['item_id']]
    );
    $availableQuantity = (int)($availableQuantityRow['available'] ?? 0);

    // Prevent oversubscription across multiple users' carts
    $otherReserved = (int)Database::fetch(
        "SELECT COALESCE(SUM(quantity), 0) as total FROM cart WHERE item_id = ? AND cart_id <> ?",
        [$cartItem['item_id'], $cart_id]
    )['total'];
    $maxAllowed = max(0, $availableQuantity - $otherReserved);

    if ($quantity > $maxAllowed) {
        throw new Exception('So luong vuot qua ton kho con lai sau khi tru cac gio hang khac (' . $maxAllowed . ').');
    }
    
    if ($quantity > $availableQuantity) {
        throw new Exception('Sá» lÆ°á»£ng vÆ°á»£t quÃ¡ sá» cÃ³ sáºµn (' . $availableQuantity . ').');
    }
    
    // Update quantity
    Database::execute(
        "UPDATE cart SET quantity = ?, updated_at = NOW() WHERE cart_id = ?",
        [$quantity, $cart_id]
    );
    
    // Log activity
    logActivity($_SESSION['user_id'], 'update_cart', "Updated cart item #$cart_id quantity to $quantity");
    
    echo json_encode([
        'success' => true,
        'message' => 'ÄÃ£ cáº­p nháº­t sá» lÆ°á»£ng thÃ nh cÃ´ng!'
    ]);
    
} catch (Exception $e) {
    error_log("Update cart quantity error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
