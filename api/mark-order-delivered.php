<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once '../config/database.php';
require_once '../includes/functions.php';

requireAdmin();

function readJsonBody(): array
{
    $raw = file_get_contents('php://input');
    if (!$raw) {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

try {
    $payload = array_merge($_POST ?? [], readJsonBody());
    $orderId = (int)($payload['order_id'] ?? 0);
    if ($orderId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing order_id.']);
        exit();
    }

    $order = Database::fetch("SELECT order_id, status FROM orders WHERE order_id = ?", [$orderId]);
    if (!$order) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Order not found.']);
        exit();
    }

    $historyTableExists = !empty(Database::fetchAll("SHOW TABLES LIKE 'order_status_history'"));
    $oldStatus = (string)($order['status'] ?? '');

    Database::beginTransaction();

    Database::execute(
        "UPDATE orders
         SET status = 'delivered',
             shipping_last_mile_status = 'delivered',
             shipping_last_mile_updated_at = NOW(),
             delivered_at = NOW(),
             updated_at = NOW()
         WHERE order_id = ?",
        [$orderId]
    );

    if ($historyTableExists) {
        Database::execute(
            "INSERT INTO order_status_history (order_id, old_status, new_status, note, created_at)
             VALUES (?, ?, ?, ?, NOW())",
            [$orderId, $oldStatus !== '' ? $oldStatus : 'shipping', 'delivered', 'Auto complete delivery (map)']
        );

        Database::execute(
            "INSERT INTO order_status_history (order_id, old_status, new_status, note, created_at)
             VALUES (?, ?, ?, ?, NOW())",
            [$orderId, 'logistics:out_for_delivery', 'logistics:delivered', 'Auto complete delivery (map)']
        );
    }

    Database::commit();

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    if (Database::getConnection()->inTransaction()) {
        Database::rollback();
    }
    error_log('mark-order-delivered error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to mark delivered.']);
}

