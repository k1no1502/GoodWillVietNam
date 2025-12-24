<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/notifications_helper.php';

$items = Database::fetchAll("
    SELECT *
    FROM admin_notifications
    WHERE status IN ('sent', 'scheduled')
    ORDER BY admin_notify_id ASC
");

$dispatched = 0;
foreach ($items as $notification) {
    if ($notification['status'] === 'scheduled' && !empty($notification['scheduled_at'])) {
        if (strtotime($notification['scheduled_at']) > time()) {
            continue;
        }
    }

    $targets = $notification['target_type'] === 'selected'
        ? json_decode($notification['target_user_ids'] ?? '[]', true)
        : null;
    $userIds = resolveNotificationTargetUsers($notification['target_type'], $targets);
    if (empty($userIds)) {
        continue;
    }

    dispatchNotificationBatch($userIds, [
        'title' => $notification['title'],
        'content' => $notification['content'],
        'severity' => $notification['severity'],
        'type' => $notification['type'],
        'sent_by' => $notification['created_by'],
        'created_at' => $notification['scheduled_at'] ?: $notification['created_at']
    ]);

    Database::execute(
        "UPDATE admin_notifications SET status = 'sent', sent_at = NOW() WHERE admin_notify_id = ?",
        [$notification['admin_notify_id']]
    );
    $dispatched++;
}

echo "Dispatched {$dispatched} notification batches.\n";
