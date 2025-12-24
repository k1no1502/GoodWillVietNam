<?php
session_start();
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/notifications_helper.php';

if (!isLoggedIn()) {
    echo json_encode([
        'success' => false,
        'message' => "Vui lAýng Ž`ŽŸng nh §-p Ž` ¯Ÿ th ¯ñc hi ¯Øn thao tA­c nAÿy."
    ]);
    exit();
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $order_id = (int)($input['order_id'] ?? 0);

    if ($order_id <= 0) {
        throw new Exception("D ¯_ li ¯Øu khA'ng h ¯œp l ¯Ø.");
    }

    $order = Database::fetch(
        "SELECT * FROM orders WHERE order_id = ? AND user_id = ?",
        [$order_id, $_SESSION['user_id']]
    );

    if (!$order) {
        throw new Exception("Ž?’­n hAÿng khA'ng t ¯\"n t §­i.");
    }

    if ($order['status'] !== 'pending') {
        throw new Exception("Ch ¯% cA3 th ¯Ÿ h ¯y Ž`’­n hAÿng Ž`ang ch ¯? x ¯- lA«.");
    }

    Database::beginTransaction();

    $orderItems = Database::fetchAll(
        "SELECT item_id, quantity FROM order_items WHERE order_id = ?",
        [$order_id]
    );

    foreach ($orderItems as $item) {
        Database::execute(
            "UPDATE inventory SET quantity = quantity + ? WHERE item_id = ?",
            [$item['quantity'], $item['item_id']]
        );
    }

    Database::execute(
        "UPDATE orders SET status = 'cancelled', updated_at = NOW() WHERE order_id = ?",
        [$order_id]
    );

    Database::execute(
        "INSERT INTO order_status_history (order_id, old_status, new_status, note, created_at) 
         VALUES (?, ?, 'cancelled', 'KhA­ch hAÿng h ¯y Ž`’­n hAÿng', NOW())",
        [$order_id, $order['status']]
    );

    createUserNotification(
        $_SESSION['user_id'],
        "Ž?’­n hAÿng Ž`Aœ h ¯y",
        "Ž?’­n hAÿng #" . str_pad($order_id, 6, '0', STR_PAD_LEFT) . " Ž`Aœ Ž`’ø ¯œc h ¯y thAÿnh cA'ng.",
        [
            'type' => 'warning',
            'category' => 'order'
        ]
    );

    Database::commit();

    logActivity($_SESSION['user_id'], 'cancel_order', "Cancelled order #$order_id");

    echo json_encode([
        'success' => true,
        'message' => "Ž?Aœ h ¯y Ž`’­n hAÿng thAÿnh cA'ng!"
    ]);

} catch (Exception $e) {
    Database::rollback();
    error_log("Cancel order error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
