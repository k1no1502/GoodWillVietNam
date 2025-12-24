<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/volunteer_tracking_helper.php';

requireAdmin();

$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $logId = (int)($_POST['log_id'] ?? 0);
    if ($logId > 0 && in_array($action, ['approve', 'reject'], true)) {
        $status = $action === 'approve' ? 'approved' : 'rejected';
        Database::execute(
            "UPDATE volunteer_hours_logs
             SET status = ?, approved_by = ?, approved_at = NOW()
             WHERE log_id = ?",
            [$status, (int)$_SESSION['user_id'], $logId]
        );
        $success = 'Updated log.';
    }
}

$pendingLogs = Database::fetchAll("
    SELECT l.*, u.name AS user_name, u.email AS user_email, c.name AS campaign_name, t.name AS task_name
    FROM volunteer_hours_logs l
    JOIN users u ON u.user_id = l.user_id
    JOIN campaigns c ON c.campaign_id = l.campaign_id
    LEFT JOIN campaign_tasks t ON t.task_id = l.task_id
    WHERE l.status = 'pending'
    ORDER BY l.created_at ASC
    LIMIT 50
");

$pageTitle = 'Volunteer Hours Approval';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - Goodwill Vietnam</title>
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
                    <h1 class="h2">Volunteer Hours</h1>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>

                <div class="card shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Pending approvals</h5>
                        <span class="text-muted small"><?php echo count($pendingLogs); ?> items</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Volunteer</th>
                                    <th>Campaign / Task</th>
                                    <th>Minutes</th>
                                    <th>Submitted</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($pendingLogs)): ?>
                                    <tr><td colspan="5" class="text-center text-muted py-4">No pending logs.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($pendingLogs as $log): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-semibold"><?php echo htmlspecialchars($log['user_name'] ?? ''); ?></div>
                                                <div class="text-muted small"><?php echo htmlspecialchars($log['user_email'] ?? ''); ?></div>
                                            </td>
                                            <td>
                                                <div class="fw-semibold"><?php echo htmlspecialchars($log['campaign_name'] ?? ''); ?></div>
                                                <div class="text-muted small"><?php echo htmlspecialchars($log['task_name'] ?? ''); ?></div>
                                            </td>
                                            <td class="fw-semibold"><?php echo (int)$log['minutes']; ?></td>
                                            <td class="text-muted small"><?php echo htmlspecialchars($log['created_at']); ?></td>
                                            <td>
                                                <div class="d-flex gap-2">
                                                    <form method="POST">
                                                        <input type="hidden" name="action" value="approve">
                                                        <input type="hidden" name="log_id" value="<?php echo (int)$log['log_id']; ?>">
                                                        <button class="btn btn-sm btn-success" type="submit">Approve</button>
                                                    </form>
                                                    <form method="POST">
                                                        <input type="hidden" name="action" value="reject">
                                                        <input type="hidden" name="log_id" value="<?php echo (int)$log['log_id']; ?>">
                                                        <button class="btn btn-sm btn-outline-danger" type="submit">Reject</button>
                                                    </form>
                                                </div>
                                            </td>
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
</body>
</html>

