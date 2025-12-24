<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/volunteer_tracking_helper.php';

requireLogin();

$userId = (int)$_SESSION['user_id'];
$campaignId = (int)($_GET['campaign_id'] ?? 0);

$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'mark_done') {
        $assignmentId = (int)($_POST['assignment_id'] ?? 0);
        if ($assignmentId > 0) {
            Database::execute(
                "UPDATE campaign_task_assignments SET status = 'completed', completed_at = NOW()
                 WHERE assignment_id = ? AND user_id = ?",
                [$assignmentId, $userId]
            );
            $success = 'Task marked completed.';
        }
    }

    if ($action === 'log_hours') {
        $hoursCampaignId = (int)($_POST['campaign_id'] ?? 0);
        $taskId = (int)($_POST['task_id'] ?? 0);
        $minutes = (int)($_POST['minutes'] ?? 0);
        $note = trim($_POST['note'] ?? '');

        if ($hoursCampaignId <= 0) {
            $errors[] = 'Invalid campaign.';
        }
        if ($minutes <= 0) {
            $errors[] = 'Minutes must be > 0.';
        }
        if (empty($errors)) {
            Database::execute(
                "INSERT INTO volunteer_hours_logs (campaign_id, task_id, user_id, minutes, note, status)
                 VALUES (?, ?, ?, ?, ?, 'pending')",
                [$hoursCampaignId, $taskId > 0 ? $taskId : null, $userId, $minutes, $note]
            );
            $success = 'Hours submitted (pending approval).';
        }
    }
}

$filters = [];
$params = [$userId];
$sql = "
    SELECT a.assignment_id, a.status AS assignment_status, a.role, a.assigned_at, a.completed_at,
           t.task_id, t.name AS task_name, t.description, t.task_type, t.status AS task_status,
           c.campaign_id, c.name AS campaign_name, c.status AS campaign_status
    FROM campaign_task_assignments a
    JOIN campaign_tasks t ON t.task_id = a.task_id
    JOIN campaigns c ON c.campaign_id = t.campaign_id
    WHERE a.user_id = ?";

if ($campaignId > 0) {
    $sql .= " AND c.campaign_id = ?";
    $params[] = $campaignId;
}

$sql .= " ORDER BY a.created_at DESC";
$assignments = Database::fetchAll($sql, $params);

$campaignOptions = Database::fetchAll("
    SELECT DISTINCT c.campaign_id, c.name
    FROM campaigns c
    JOIN campaign_task_assignments a
    JOIN campaign_tasks t ON t.task_id = a.task_id AND t.campaign_id = c.campaign_id
    WHERE a.user_id = ?
    ORDER BY c.name ASC
", [$userId]);

$pendingLogs = Database::fetchAll(
    "SELECT log_id, campaign_id, task_id, minutes, status, created_at
     FROM volunteer_hours_logs
     WHERE user_id = ?
     ORDER BY created_at DESC
     LIMIT 10",
    [$userId]
);

$pageTitle = 'My Tasks';
include 'includes/header.php';
?>

<div class="container py-5 mt-5">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h2 class="mb-1">My Tasks</h2>
            <div class="text-muted">Assigned tasks across campaigns</div>
        </div>
        <form method="GET" class="d-flex gap-2">
            <select class="form-select" name="campaign_id" onchange="this.form.submit()">
                <option value="0">All campaigns</option>
                <?php foreach ($campaignOptions as $c): ?>
                    <option value="<?php echo (int)$c['campaign_id']; ?>" <?php echo $campaignId === (int)$c['campaign_id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($c['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger"><?php echo implode('<br>', array_map('htmlspecialchars', $errors)); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Assigned tasks</h5>
                    <span class="text-muted small"><?php echo count($assignments); ?> tasks</span>
                </div>
                <div class="list-group list-group-flush">
                    <?php if (empty($assignments)): ?>
                        <div class="list-group-item text-center text-muted py-5">No tasks assigned yet.</div>
                    <?php else: ?>
                        <?php foreach ($assignments as $a): ?>
                            <div class="list-group-item">
                                <div class="d-flex flex-wrap justify-content-between gap-2">
                                    <div>
                                        <div class="fw-semibold"><?php echo htmlspecialchars($a['task_name']); ?></div>
                                        <div class="text-muted small">
                                            <?php echo htmlspecialchars($a['campaign_name']); ?> · <?php echo htmlspecialchars($a['task_type']); ?> · Role: <?php echo htmlspecialchars($a['role']); ?>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-<?php echo $a['assignment_status'] === 'completed' ? 'success' : 'secondary'; ?>">
                                            <?php echo htmlspecialchars($a['assignment_status']); ?>
                                        </span>
                                    </div>
                                </div>
                                <?php if (!empty($a['description'])): ?>
                                    <div class="text-muted mt-2"><?php echo htmlspecialchars($a['description']); ?></div>
                                <?php endif; ?>
                                <div class="d-flex flex-wrap gap-2 mt-3">
                                    <?php if ($a['assignment_status'] !== 'completed'): ?>
                                        <form method="POST">
                                            <input type="hidden" name="action" value="mark_done">
                                            <input type="hidden" name="assignment_id" value="<?php echo (int)$a['assignment_id']; ?>">
                                            <button class="btn btn-sm btn-outline-success" type="submit">
                                                <i class="bi bi-check2 me-1"></i>Mark completed
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#logHours<?php echo (int)$a['assignment_id']; ?>">
                                        <i class="bi bi-clock me-1"></i>Log hours
                                    </button>
                                </div>
                                <div class="collapse mt-3" id="logHours<?php echo (int)$a['assignment_id']; ?>">
                                    <form method="POST" class="row g-2">
                                        <input type="hidden" name="action" value="log_hours">
                                        <input type="hidden" name="campaign_id" value="<?php echo (int)$a['campaign_id']; ?>">
                                        <input type="hidden" name="task_id" value="<?php echo (int)$a['task_id']; ?>">
                                        <div class="col-4 col-md-3">
                                            <input type="number" class="form-control form-control-sm" name="minutes" min="5" step="5" placeholder="Minutes" required>
                                        </div>
                                        <div class="col-8 col-md-7">
                                            <input type="text" class="form-control form-control-sm" name="note" placeholder="Note (optional)">
                                        </div>
                                        <div class="col-12 col-md-2 d-grid">
                                            <button class="btn btn-sm btn-primary" type="submit">Submit</button>
                                        </div>
                                        <div class="col-12 text-muted small">Submitted hours require admin approval.</div>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Recent hour logs</h5>
                </div>
                <div class="list-group list-group-flush">
                    <?php if (empty($pendingLogs)): ?>
                        <div class="list-group-item text-muted">No hour logs yet.</div>
                    <?php else: ?>
                        <?php foreach ($pendingLogs as $log): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between">
                                    <div class="fw-semibold"><?php echo (int)$log['minutes']; ?> min</div>
                                    <span class="badge bg-<?php echo $log['status'] === 'approved' ? 'success' : ($log['status'] === 'rejected' ? 'danger' : 'secondary'); ?>">
                                        <?php echo htmlspecialchars($log['status']); ?>
                                    </span>
                                </div>
                                <div class="text-muted small"><?php echo htmlspecialchars($log['created_at']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

