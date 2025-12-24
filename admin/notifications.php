<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/notifications_helper.php';

requireAdmin();
processScheduledAdminNotifications();

$pageTitle = 'Notification Center';
$errors = [];
$success = null;

$activeUsers = Database::fetchAll("SELECT user_id, name, email FROM users WHERE status = 'active' ORDER BY name ASC LIMIT 200");
$sendMode = $_POST['send_mode'] ?? 'now';
$sendTime = $_POST['send_time'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $type = $_POST['type'] ?? 'system';
    $severity = $_POST['severity'] ?? 'info';
    $targetType = $_POST['target_type'] ?? 'all';
    $selectedUsers = $_POST['target_users'] ?? [];

    if ($title === '') {
        $errors[] = 'Title is required.';
    }
    if ($content === '') {
        $errors[] = 'Content is required.';
    }
    if ($targetType === 'selected' && empty($selectedUsers)) {
        $errors[] = 'Please choose at least one target user.';
    }

    $scheduleDate = null;
    if ($sendMode === 'schedule') {
        if (empty($sendTime)) {
            $errors[] = 'Please pick a schedule time.';
        } else {
            $timestamp = strtotime($sendTime);
            if ($timestamp && $timestamp > time()) {
                $scheduleDate = date('Y-m-d H:i:s', $timestamp);
            } else {
                $errors[] = 'Schedule time must be in the future.';
            }
        }
    } else {
        $sendMode = 'now';
        $sendTime = '';
    }

    if (empty($errors)) {
        $payload = [
            'title' => $title,
            'content' => $content,
            'type' => $type,
            'severity' => $severity,
            'target_type' => $targetType,
            'target_user_ids' => $targetType === 'selected' ? json_encode(array_map('intval', (array)$selectedUsers)) : null,
            'status' => $sendMode === 'schedule' ? 'scheduled' : 'sent',
            'scheduled_at' => $sendMode === 'schedule' ? $scheduleDate : null,
            'sent_at' => $sendMode === 'schedule' ? null : date('Y-m-d H:i:s'),
            'created_by' => $_SESSION['user_id']
        ];

        $sendNow = $sendMode !== 'schedule';

        Database::execute(
            "INSERT INTO admin_notifications (title, content, type, severity, target_type, target_user_ids, status, scheduled_at, sent_at, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $payload['title'],
                $payload['content'],
                $payload['type'],
                $payload['severity'],
                $payload['target_type'],
                $payload['target_user_ids'],
                $payload['status'],
                $payload['scheduled_at'],
                $payload['sent_at'],
                $payload['created_by']
            ]
        );

        if ($sendNow) {
            $userIds = resolveNotificationTargetUsers($targetType, $selectedUsers);
            dispatchNotificationBatch($userIds, [
                'title' => $title,
                'content' => $content,
                'type' => $type,
                'severity' => $severity,
                'sent_by' => $_SESSION['user_id']
            ]);
        }

        $success = $sendNow ? 'Notification sent successfully.' : 'Notification scheduled successfully.';
        if ($success) {
            $sendMode = 'now';
            $sendTime = '';
        }
    }
}

$history = Database::fetchAll("
    SELECT an.*, u.name AS creator_name
    FROM admin_notifications an
    LEFT JOIN users u ON u.user_id = an.created_by
    ORDER BY an.created_at DESC
    LIMIT 25
");
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Goodwill Vietnam</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 admin-content">
                <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Notification Center</h1>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <?php echo implode('<br>', array_map('htmlspecialchars', $errors)); ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Send notification</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Title</label>
                                <input type="text" name="title" class="form-control" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Type</label>
                                <select name="type" class="form-select">
                                    <option value="system">System</option>
                                    <option value="campaign">Campaign</option>
                                    <option value="donation">Donation</option>
                                    <option value="order">Order</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Severity</label>
                                <select name="severity" class="form-select">
                                    <option value="info">Info</option>
                                    <option value="success">Success</option>
                                    <option value="warning">Warning</option>
                                    <option value="error">Error</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Content</label>
                                <textarea name="content" class="form-control" rows="4" required></textarea>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Target</label>
                                <select name="target_type" id="targetType" class="form-select">
                                    <option value="all">All users</option>
                                    <option value="selected">Selected users</option>
                                </select>
                            </div>
                            <div class="col-md-8" id="userSelectWrapper" style="display:none;">
                                <label class="form-label">Select users</label>
                                <select name="target_users[]" class="form-select" multiple>
                                    <?php foreach ($activeUsers as $user): ?>
                                        <option value="<?php echo $user['user_id']; ?>">
                                            <?php echo htmlspecialchars($user['name'] . ' (' . $user['email'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">Hold Ctrl/Command to select multiple users.</small>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label d-block">Delivery</label>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="send_mode" id="sendModeNow" value="now" <?php echo $sendMode === 'schedule' ? '' : 'checked'; ?>>
                                    <label class="form-check-label" for="sendModeNow">Send now</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="send_mode" id="sendModeSchedule" value="schedule" <?php echo $sendMode === 'schedule' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="sendModeSchedule">Schedule</label>
                                </div>
                            </div>
                            <div class="col-md-4" id="scheduleWrapper" style="<?php echo $sendMode === 'schedule' ? '' : 'display:none;'; ?>">
                                <label class="form-label">Schedule time</label>
                                <input type="datetime-local" name="send_time" id="scheduleInput" class="form-control" value="<?php echo htmlspecialchars($sendTime); ?>" <?php echo $sendMode === 'schedule' ? '' : 'disabled'; ?>>
                                <small class="text-muted">Choose when the notification should go out.</small>
                            </div>
                            <div class="col-12 text-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-send me-1"></i>Send notification
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Recent notifications</h5>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Type</th>
                                    <th>Target</th>
                                    <th>Status</th>
                                    <th>Scheduled</th>
                                    <th>Sent</th>
                                    <th>Created by</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($history)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">No notifications yet.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($history as $row): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($row['title']); ?></strong>
                                                <div class="text-muted small">
                                                    <?php
                                                    $snippet = substr($row['content'], 0, 60);
                                                    if (strlen($row['content']) > 60) {
                                                        $snippet .= '...';
                                                    }
                                                    echo htmlspecialchars($snippet);
                                                    ?>
                                                </div>
                                            </td>
                                            <td><?php echo ucfirst($row['type']); ?></td>
                                            <td><?php echo $row['target_type'] === 'all' ? 'All users' : 'Selected'; ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $row['status'] === 'sent' ? 'success' : ($row['status'] === 'scheduled' ? 'warning' : 'secondary'); ?>">
                                                    <?php echo ucfirst($row['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo $row['scheduled_at'] ? formatDate($row['scheduled_at']) : '-'; ?></td>
                                            <td><?php echo $row['sent_at'] ? formatDate($row['sent_at']) : '-'; ?></td>
                                            <td><?php echo htmlspecialchars($row['creator_name'] ?? 'System'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/data-refresh.js" data-base="../" data-interval="5000"></script>
    <script>
        const targetSelect = document.getElementById('targetType');
        const userWrapper = document.getElementById('userSelectWrapper');
        const sendModeRadios = document.querySelectorAll('input[name="send_mode"]');
        const scheduleWrapper = document.getElementById('scheduleWrapper');
        const scheduleInput = document.getElementById('scheduleInput');

        const toggleUserSelect = () => {
            userWrapper.style.display = targetSelect.value === 'selected' ? '' : 'none';
        };

        const toggleSchedule = () => {
            const selectedMode = document.querySelector('input[name="send_mode"]:checked');
            const isSchedule = selectedMode && selectedMode.value === 'schedule';
            if (scheduleWrapper) {
                scheduleWrapper.style.display = isSchedule ? '' : 'none';
            }
            if (scheduleInput) {
                scheduleInput.disabled = !isSchedule;
            }
        };

        targetSelect.addEventListener('change', toggleUserSelect);
        sendModeRadios.forEach(radio => radio.addEventListener('change', toggleSchedule));

        toggleUserSelect();
        toggleSchedule();
    </script>
</body>
</html>
