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

    $order = Database::fetch("SELECT order_id FROM orders WHERE order_id = ?", [$orderId]);
    if (!$order) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Order not found.']);
        exit();
    }

    $statusCode = trim((string)($payload['status_code'] ?? ''));
    $title = trim((string)($payload['title'] ?? ''));
    $note = trim((string)($payload['note'] ?? ''));
    $locationAddress = trim((string)($payload['location_address'] ?? ''));

    $lat = $payload['lat'] ?? null;
    $lng = $payload['lng'] ?? null;
    $lat = ($lat === '' || $lat === null) ? null : (float)$lat;
    $lng = ($lng === '' || $lng === null) ? null : (float)$lng;

    $occurredAt = trim((string)($payload['occurred_at'] ?? ''));
    if ($occurredAt === '') {
        $occurredAt = date('Y-m-d H:i:s');
    }

    if ($title === '') {
        $title = 'Cập nhật vận chuyển';
    }

    Database::execute(
        "INSERT INTO order_tracking_events
            (order_id, status_code, title, note, location_address, lat, lng, occurred_at, created_by)
         VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [
            $orderId,
            $statusCode,
            $title,
            $note,
            $locationAddress,
            $lat,
            $lng,
            $occurredAt,
            (int)($_SESSION['user_id'] ?? 0),
        ]
    );

    echo json_encode([
        'success' => true,
        'event_id' => (int)Database::lastInsertId(),
    ]);
} catch (Exception $e) {
    error_log('add-order-tracking-event error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to add tracking event.']);
}

