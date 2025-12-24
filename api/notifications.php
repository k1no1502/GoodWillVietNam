<?php
session_start();
header('Content-Type: application/json');

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/notifications_helper.php';

if (!isLoggedIn()) {
    echo json_encode([
        'success' => false,
        'message' => 'Vui long dang nhap truoc.'
    ]);
    exit();
}

processScheduledAdminNotifications();

$userId = $_SESSION['user_id'];
$action = $_GET['action'] ?? ($_POST['action'] ?? 'list');
$method = $_SERVER['REQUEST_METHOD'];

function buildNotificationFilters() {
    return [
        'date_from' => $_GET['date_from'] ?? null,
        'date_to' => $_GET['date_to'] ?? null,
        'status' => $_GET['status'] ?? null,
        'type' => $_GET['type'] ?? null
    ];
}

try {
    switch ($action) {
        case 'count':
            echo json_encode([
                'success' => true,
                'count' => getUnreadNotificationCount($userId)
            ]);
            break;

        case 'mark-read':
            if ($method !== 'POST') {
                throw new Exception('Phuong thuc khong hop le.');
            }
            $payload = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            $notifyId = (int)($payload['notify_id'] ?? 0);
            if ($notifyId <= 0) {
                throw new Exception('Notification ID khong hop le.');
            }
            markNotificationAsRead($notifyId, $userId);
            echo json_encode(['success' => true]);
            break;

        case 'mark-all':
            if ($method !== 'POST') {
                throw new Exception('Phuong thuc khong hop le.');
            }
            markAllNotificationsAsRead($userId);
            echo json_encode(['success' => true]);
            break;

        case 'detail':
            $notifyId = (int)($_GET['id'] ?? 0);
            if ($notifyId <= 0) {
                throw new Exception('Notification ID khong hop le.');
            }
            $notification = Database::fetch(
                "SELECT notify_id, title, message, type, category, is_read, action_url, created_at 
                 FROM notifications 
                 WHERE notify_id = ? AND user_id = ? AND created_at <= NOW()",
                [$notifyId, $userId]
            );
            if (!$notification) {
                throw new Exception('Khong tim thay thong bao.');
            }
            if (!$notification['is_read']) {
                markNotificationAsRead($notifyId, $userId);
                $notification['is_read'] = 1;
            }
            echo json_encode(['success' => true, 'data' => $notification]);
            break;

        case 'list':
        default:
            $page = max(1, (int)($_GET['page'] ?? 1));
            $perPage = (int)($_GET['per_page'] ?? 10);
            $perPage = max(5, min(50, $perPage));
            $offset = ($page - 1) * $perPage;

            $filters = buildNotificationFilters();
            $total = countUserNotifications($userId, $filters);
            $notifications = fetchUserNotifications($userId, $filters, $perPage, $offset);

            echo json_encode([
                'success' => true,
                'data' => $notifications,
                'pagination' => [
                    'total' => $total,
                    'page' => $page,
                    'per_page' => $perPage,
                    'total_pages' => $perPage > 0 ? (int)ceil($total / $perPage) : 1
                ]
            ]);
            break;
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
