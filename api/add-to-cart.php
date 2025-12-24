<?php
session_start();
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    echo json_encode([
        'success' => false,
        'message' => 'Vui lòng dang nh?p d? thêm vào gi? hàng.'
    ]);
    exit();
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $item_id = (int)($input['item_id'] ?? 0);

    if ($item_id <= 0) {
        throw new Exception('V?t ph?m không h?p l?.');
    }

    $item = Database::fetch(
        "SELECT * FROM inventory WHERE item_id = ? AND status = 'available' AND is_for_sale = TRUE",
        [$item_id]
    );

    if (!$item) {
        throw new Exception('V?t ph?m không t?n t?i ho?c không còn s?n.');
    }

    $availableQuantity = (int)$item['quantity'];
    $totalReserved = (int)Database::fetch(
        "SELECT COALESCE(SUM(quantity), 0) as total FROM cart WHERE item_id = ? AND user_id <> ?",
        [$item_id, $_SESSION['user_id']]
    )['total'];
    $availableAfterReserved = max(0, $availableQuantity - $totalReserved);

    if ($availableAfterReserved <= 0) {
        throw new Exception('Vat pham hien da het hang hoac dang duoc dat giu.');
    }
    $currentCartQuantity = (int)Database::fetch(
        "SELECT COALESCE(SUM(quantity), 0) as total FROM cart WHERE user_id = ? AND item_id = ?",
        [$_SESSION['user_id'], $item_id]
    )['total'];

    if (false) {
        throw new Exception('S? lu?ng trong gi? dã d?t t?i da hi?n có.');
    }

    $cartItem = Database::fetch(
        "SELECT * FROM cart WHERE user_id = ? AND item_id = ?",
        [$_SESSION['user_id'], $item_id]
    );

    if ($cartItem) {
        $message = 'V?t ph?m dã có trong gi? hàng.';
    } else {
        Database::execute(
            "INSERT INTO cart (user_id, item_id, quantity, created_at) VALUES (?, ?, 1, NOW())",
            [$_SESSION['user_id'], $item_id]
        );
        $message = 'Ðã thêm vào gi? hàng!';
    }

    $cartCount = Database::fetch(
        "SELECT COUNT(*) as count FROM cart WHERE user_id = ?",
        [$_SESSION['user_id']]
    )['count'];

    logActivity($_SESSION['user_id'], 'add_to_cart', "Added item #$item_id to cart");

    echo json_encode([
        'success' => true,
        'message' => $message,
        'cart_count' => $cartCount
    ]);

} catch (Exception $e) {
    error_log('Add to cart error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
