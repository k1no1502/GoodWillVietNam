<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once '../config/database.php';
require_once '../includes/functions.php';

requireLogin();

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

    $logisticsConfigPath = __DIR__ . '/../config/logistics.php';
    $logisticsConfig = file_exists($logisticsConfigPath) ? require $logisticsConfigPath : [];
    $sim = $logisticsConfig['simulation'] ?? [];
    $enabled = (bool)($sim['auto_deliver_enabled'] ?? false);
    $minSeconds = (int)($sim['auto_deliver_min_seconds'] ?? 0);

    if (!$enabled) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Auto deliver disabled.']);
        exit();
    }

    $order = Database::fetch(
        "SELECT order_id, user_id, status, shipping_last_mile_status,
                UNIX_TIMESTAMP(shipping_last_mile_updated_at) AS last_mile_ts,
                UNIX_TIMESTAMP(shipped_at) AS shipped_ts
         FROM orders
         WHERE order_id = ? AND user_id = ?",
        [$orderId, (int)$_SESSION['user_id']]
    );

    if (!$order) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Order not found.']);
        exit();
    }

    $status = strtolower(trim((string)($order['status'] ?? '')));
    $lastMile = strtolower(trim((string)($order['shipping_last_mile_status'] ?? '')));

    if ($status === 'cancelled') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Order cancelled.']);
        exit();
    }

    if (!in_array($lastMile, ['out_for_delivery', 'in_transit', 'picked_up'], true) && $status !== 'shipping') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Order is not in delivery.']);
        exit();
    }

    $now = time();
    $baseTs = (int)($order['last_mile_ts'] ?? 0);
    if ($baseTs <= 0) {
        $baseTs = (int)($order['shipped_ts'] ?? 0);
    }

    if ($minSeconds > 0 && $baseTs > 0 && ($now - $baseTs) < $minSeconds) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'message' => 'Too early to auto-complete delivery.',
            'retry_after_seconds' => max(1, $minSeconds - ($now - $baseTs)),
        ]);
        exit();
    }

    $historyTableExists = !empty(Database::fetchAll("SHOW TABLES LIKE 'order_status_history'"));
    $oldStatus = (string)($order['status'] ?? 'shipping');

    Database::beginTransaction();

    Database::execute(
        "UPDATE orders
         SET status = 'delivered',
             shipping_last_mile_status = 'delivered',
             shipping_last_mile_updated_at = NOW(),
             delivered_at = NOW(),
             updated_at = NOW()
         WHERE order_id = ? AND user_id = ?",
        [$orderId, (int)$_SESSION['user_id']]
    );

    if ($historyTableExists) {
        Database::execute(
            "INSERT INTO order_status_history (order_id, old_status, new_status, note, created_at)
             VALUES (?, ?, ?, ?, NOW())",
            [$orderId, $oldStatus !== '' ? $oldStatus : 'shipping', 'delivered', 'Auto complete delivery (buyer map)']
        );

        Database::execute(
            "INSERT INTO order_status_history (order_id, old_status, new_status, note, created_at)
             VALUES (?, ?, ?, ?, NOW())",
            [$orderId, 'logistics:' . ($lastMile !== '' ? $lastMile : 'out_for_delivery'), 'logistics:delivered', 'Auto complete delivery (buyer map)']
        );
    }

    Database::commit();

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    if (Database::getConnection()->inTransaction()) {
        Database::rollback();
    }
    error_log('auto-deliver-order error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to auto-complete delivery.']);
}

