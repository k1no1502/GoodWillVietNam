<?php
if (!function_exists('ensureNotificationSchema')) {
    function ensureNotificationSchema() {
        static $ensured = false;
        if ($ensured) {
            return;
        }
        $ensured = true;

        try {
            $hasCategory = Database::fetch("SHOW COLUMNS FROM notifications LIKE 'category'");
            if (!$hasCategory) {
                Database::execute("ALTER TABLE notifications ADD COLUMN category ENUM('system','campaign','donation','order','general') DEFAULT 'general' AFTER type");
            }
        } catch (Exception $e) {
            error_log('ensureNotificationSchema category failed: ' . $e->getMessage());
        }

        try {
            $hasSentBy = Database::fetch("SHOW COLUMNS FROM notifications LIKE 'sent_by'");
            if (!$hasSentBy) {
                Database::execute("ALTER TABLE notifications ADD COLUMN sent_by INT NULL AFTER user_id");
            }
        } catch (Exception $e) {
            error_log('ensureNotificationSchema sent_by failed: ' . $e->getMessage());
        }

        try {
            Database::execute("
                CREATE TABLE IF NOT EXISTS admin_notifications (
                    admin_notify_id INT PRIMARY KEY AUTO_INCREMENT,
                    title VARCHAR(200) NOT NULL,
                    content TEXT NOT NULL,
                    type ENUM('system', 'campaign', 'donation', 'order', 'general') DEFAULT 'system',
                    severity ENUM('info', 'success', 'warning', 'error') DEFAULT 'info',
                    target_type ENUM('all', 'selected') DEFAULT 'all',
                    target_user_ids JSON,
                    status ENUM('draft', 'scheduled', 'sent', 'cancelled') DEFAULT 'draft',
                    scheduled_at DATETIME NULL,
                    sent_at DATETIME NULL,
                    created_by INT NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } catch (Exception $e) {
            error_log('ensureNotificationSchema admin table failed: ' . $e->getMessage());
        }
    }
}

ensureNotificationSchema();

if (!function_exists('getUnreadNotificationCount')) {
    function getUnreadNotificationCount($userId) {
        if (empty($userId)) {
            return 0;
        }

        $row = Database::fetch(
            "SELECT COUNT(*) AS total FROM notifications WHERE user_id = ? AND is_read = 0 AND created_at <= NOW()",
            [$userId]
        );

        return (int)($row['total'] ?? 0);
    }
}

if (!function_exists('fetchUserNotifications')) {
    function fetchUserNotifications($userId, $filters = [], $limit = 20, $offset = 0) {
        if (empty($userId)) {
            return [];
        }

        $params = [$userId];
        $conditions = ["user_id = ?", "created_at <= NOW()"];

        if (!empty($filters['status']) && in_array($filters['status'], ['read', 'unread'], true)) {
            $conditions[] = $filters['status'] === 'read' ? "is_read = 1" : "is_read = 0";
        }

        if (!empty($filters['type']) && in_array($filters['type'], ['system', 'campaign', 'donation', 'order', 'general'], true)) {
            $conditions[] = "category = ?";
            $params[] = $filters['type'];
        }

        if (!empty($filters['date_from'])) {
            $conditions[] = "DATE(created_at) >= ?";
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $conditions[] = "DATE(created_at) <= ?";
            $params[] = $filters['date_to'];
        }

        $sql = "
            SELECT notify_id, title, message, type, category, is_read, action_url, created_at
            FROM notifications
            WHERE " . implode(' AND ', $conditions) . "
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?";

        $params[] = (int)$limit;
        $params[] = (int)$offset;

        return Database::fetchAll($sql, $params);
    }
}

if (!function_exists('countUserNotifications')) {
    function countUserNotifications($userId, $filters = []) {
        if (empty($userId)) {
            return 0;
        }

        $params = [$userId];
        $conditions = ["user_id = ?", "created_at <= NOW()"];

        if (!empty($filters['status']) && in_array($filters['status'], ['read', 'unread'], true)) {
            $conditions[] = $filters['status'] === 'read' ? "is_read = 1" : "is_read = 0";
        }

        if (!empty($filters['type']) && in_array($filters['type'], ['system', 'campaign', 'donation', 'order', 'general'], true)) {
            $conditions[] = "category = ?";
            $params[] = $filters['type'];
        }

        if (!empty($filters['date_from'])) {
            $conditions[] = "DATE(created_at) >= ?";
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $conditions[] = "DATE(created_at) <= ?";
            $params[] = $filters['date_to'];
        }

        $row = Database::fetch(
            "SELECT COUNT(*) AS total FROM notifications WHERE " . implode(' AND ', $conditions),
            $params
        );

        return (int)($row['total'] ?? 0);
    }
}

if (!function_exists('markNotificationAsRead')) {
    function markNotificationAsRead($notifyId, $userId) {
        if (empty($notifyId) || empty($userId)) {
            return false;
        }

        Database::execute(
            "UPDATE notifications SET is_read = 1 WHERE notify_id = ? AND user_id = ?",
            [$notifyId, $userId]
        );

        return true;
    }
}

if (!function_exists('markAllNotificationsAsRead')) {
    function markAllNotificationsAsRead($userId) {
        if (empty($userId)) {
            return;
        }

        Database::execute(
            "UPDATE notifications SET is_read = 1 WHERE user_id = ?",
            [$userId]
        );
    }
}

if (!function_exists('createUserNotification')) {
    function createUserNotification($userId, $title, $message, $options = []) {
        if (empty($userId) || empty($title) || empty($message)) {
            return false;
        }

        $defaults = [
            'type' => 'info',
            'category' => 'general',
            'action_url' => null,
            'sent_by' => null,
            'created_at' => null
        ];
        $data = array_merge($defaults, $options);

        $sql = "
            INSERT INTO notifications (user_id, sent_by, title, message, type, category, is_read, action_url, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?)";

        $createdAt = $data['created_at'] ?? date('Y-m-d H:i:s');

        Database::execute($sql, [
            $userId,
            $data['sent_by'],
            $title,
            $message,
            $data['type'],
            $data['category'],
            $data['action_url'],
            $createdAt
        ]);

        return Database::lastInsertId();
    }
}

if (!function_exists('resolveNotificationTargetUsers')) {
    function resolveNotificationTargetUsers($targetType, $rawUserIds = null) {
        if ($targetType === 'selected' && !empty($rawUserIds)) {
            $ids = array_filter(array_map('intval', (array)$rawUserIds));
            if (!empty($ids)) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $rows = Database::fetchAll(
                    "SELECT user_id FROM users WHERE user_id IN ($placeholders) AND status = 'active'",
                    $ids
                );
                return array_column($rows, 'user_id');
            }
            return [];
        }

    $rows = Database::fetchAll(
        "SELECT user_id FROM users WHERE status = 'active'"
    );

    if (empty($rows)) {
        $fallback = Database::fetchAll("SELECT user_id FROM users LIMIT 5");
        if (!empty($fallback)) {
            return array_column($fallback, 'user_id');
        }
    }

    return array_column($rows, 'user_id');
}
}

if (!function_exists('dispatchNotificationBatch')) {
    function dispatchNotificationBatch($userIds, $payload) {
        if (empty($userIds)) {
            return 0;
        }

        $total = 0;
        Database::beginTransaction();
        try {
            foreach ($userIds as $uid) {
                createUserNotification(
                    $uid,
                    $payload['title'],
                    $payload['content'],
                    [
                        'type' => $payload['severity'] ?? 'info',
                        'category' => $payload['type'] ?? 'general',
                        'action_url' => $payload['action_url'] ?? null,
                        'sent_by' => $payload['sent_by'] ?? null,
                        'created_at' => $payload['created_at'] ?? null
                    ]
                );
                $total++;
            }
            Database::commit();
        } catch (Exception $e) {
            Database::rollback();
            error_log('dispatchNotificationBatch failed: ' . $e->getMessage());
            throw $e;
        }

        return $total;
    }
}

if (!function_exists('processScheduledAdminNotifications')) {
    function processScheduledAdminNotifications() {
        static $ran = false;
        if ($ran) {
            return;
        }
        $ran = true;

    try {
        $due = Database::fetchAll("
            SELECT *
            FROM admin_notifications
            WHERE status = 'scheduled' AND scheduled_at <= NOW()
            ORDER BY scheduled_at ASC
            LIMIT 20
        ");
    } catch (Exception $e) {
        error_log('processScheduledAdminNotifications lookup failed: ' . $e->getMessage());
        return;
    }

        foreach ($due as $notification) {
            try {
                $targets = $notification['target_type'] === 'selected'
                    ? json_decode($notification['target_user_ids'] ?? '[]', true)
                    : null;
                $userIds = resolveNotificationTargetUsers($notification['target_type'], $targets);

                dispatchNotificationBatch($userIds, [
                    'title' => $notification['title'],
                    'content' => $notification['content'],
                    'severity' => $notification['severity'],
                    'type' => $notification['type'],
                    'sent_by' => $notification['created_by'],
                    'created_at' => $notification['scheduled_at']
                ]);

                Database::execute(
                    "UPDATE admin_notifications SET status = 'sent', sent_at = NOW() WHERE admin_notify_id = ?",
                    [$notification['admin_notify_id']]
                );
            } catch (Exception $e) {
                error_log('processScheduledAdminNotifications error: ' . $e->getMessage());
            }
        }
    }
}
?>
