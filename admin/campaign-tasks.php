<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/volunteer_tracking_helper.php';

requireAdmin();

$campaignId = (int)($_GET['id'] ?? 0);
if ($campaignId <= 0) {
    header('Location: campaigns.php');
    exit();
}

$campaign = Database::fetch("SELECT * FROM campaigns WHERE campaign_id = ?", [$campaignId]);
if (!$campaign) {
    header('Location: campaigns.php');
    exit();
}

$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_task') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $taskType = $_POST['task_type'] ?? 'support';
        $requiredVolunteers = max(1, (int)($_POST['required_volunteers'] ?? 1));
        $estimatedHours = (float)($_POST['estimated_hours'] ?? 0);
        $startAt = $_POST['start_at'] ?? '';
        $endAt = $_POST['end_at'] ?? '';

        if ($name === '') {
            $errors[] = 'Task name is required.';
        }

        if (empty($errors)) {
            $estimatedMinutes = (int)round(max(0, $estimatedHours) * 60);
            $startValue = $startAt ? date('Y-m-d H:i:s', strtotime($startAt)) : null;
            $endValue = $endAt ? date('Y-m-d H:i:s', strtotime($endAt)) : null;

            Database::execute(
                "INSERT INTO campaign_tasks (campaign_id, name, description, task_type, required_volunteers, estimated_minutes, start_at, end_at, status, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'open', ?)",
                [
                    $campaignId,
                    $name,
                    $description,
                    $taskType,
                    $requiredVolunteers,
                    $estimatedMinutes,
                    $startValue,
                    $endValue,
                    $_SESSION['user_id']
                ]
            );
            $success = 'Task created.';
        }
    }

    if ($action === 'update_task_status') {
        $taskId = (int)($_POST['task_id'] ?? 0);
        $status = $_POST['status'] ?? 'open';
        $allowed = ['open', 'assigned', 'in_progress', 'completed', 'cancelled'];
        if ($taskId > 0 && in_array($status, $allowed, true)) {
            Database::execute(
                "UPDATE campaign_tasks SET status = ? WHERE task_id = ? AND campaign_id = ?",
                [$status, $taskId, $campaignId]
            );
            $success = 'Task updated.';
        }
    }

    if ($action === 'assign_volunteers') {
        $taskId = (int)($_POST['task_id'] ?? 0);
        $role = $_POST['role'] ?? 'member';
        $volunteers = $_POST['volunteers'] ?? [];
        if ($taskId <= 0) {
            $errors[] = 'Invalid task.';
        }
        if (empty($volunteers)) {
            $errors[] = 'Pick at least one volunteer.';
        }

        if (empty($errors)) {
            $volunteers = array_values(array_filter(array_map('intval', (array)$volunteers)));
            Database::beginTransaction();
            try {
                foreach ($volunteers as $userId) {
                    $exists = Database::fetch(
                        "SELECT 1 FROM campaign_task_assignments WHERE task_id = ? AND user_id = ?",
                        [$taskId, $userId]
                    );
                    if ($exists) {
                        continue;
                    }
                    Database::execute(
                        "INSERT INTO campaign_task_assignments (task_id, user_id, role, status, assigned_at)
                         VALUES (?, ?, ?, 'assigned', NOW())",
                        [$taskId, $userId, $role]
                    );
                }

                Database::execute(
                    "UPDATE campaign_tasks SET status = 'assigned' WHERE task_id = ? AND campaign_id = ? AND status = 'open'",
                    [$taskId, $campaignId]
                );

                Database::commit();
                $success = 'Volunteer(s) assigned.';
            } catch (Exception $e) {
                Database::rollback();
                $errors[] = 'Assign failed.';
                error_log('assign_volunteers failed: ' . $e->getMessage());
            }
        }
    }

    if ($action === 'bulk_assign_panel') {
        $taskId = (int)($_POST['task_id'] ?? 0);
        $role = $_POST['role'] ?? 'member';
        $volunteers = $_POST['volunteers'] ?? [];

        if ($taskId <= 0) {
            $errors[] = 'Please select a task.';
        }
        if (empty($volunteers)) {
            $errors[] = 'Please select at least one volunteer.';
        }

        if (empty($errors)) {
            $volunteers = array_values(array_filter(array_map('intval', (array)$volunteers)));
            Database::beginTransaction();
            try {
                foreach ($volunteers as $userId) {
                    $exists = Database::fetch(
                        "SELECT 1 FROM campaign_task_assignments WHERE task_id = ? AND user_id = ?",
                        [$taskId, $userId]
                    );
                    if ($exists) {
                        continue;
                    }
                    Database::execute(
                        "INSERT INTO campaign_task_assignments (task_id, user_id, role, status, assigned_at)
                         VALUES (?, ?, ?, 'assigned', NOW())",
                        [$taskId, $userId, $role]
                    );
                }

                Database::execute(
                    "UPDATE campaign_tasks SET status = 'assigned' WHERE task_id = ? AND campaign_id = ? AND status = 'open'",
                    [$taskId, $campaignId]
                );

                Database::commit();
                $success = 'Volunteer(s) assigned.';
            } catch (Exception $e) {
                Database::rollback();
                $errors[] = 'Assign failed.';
                error_log('bulk_assign_panel failed: ' . $e->getMessage());
            }
        }
    }
}

$tasks = Database::fetchAll(
    "SELECT * FROM campaign_tasks WHERE campaign_id = ? ORDER BY created_at DESC",
    [$campaignId]
);

$campaignVolunteers = Database::fetchAll(
    "SELECT cv.user_id, u.name, u.email
     FROM campaign_volunteers cv
     JOIN users u ON u.user_id = cv.user_id
     WHERE cv.campaign_id = ? AND cv.status = 'approved'
     ORDER BY u.name ASC",
    [$campaignId]
);

$taskAssignments = Database::fetchAll(
    "SELECT a.*, u.name, u.email, t.name AS task_name
     FROM campaign_task_assignments a
     JOIN users u ON u.user_id = a.user_id
     JOIN campaign_tasks t ON t.task_id = a.task_id
     WHERE t.campaign_id = ?
     ORDER BY a.created_at DESC",
    [$campaignId]
);

$pageTitle = 'Campaign Tasks';
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
                    <div>
                        <h1 class="h2 mb-1">Task Assignment</h1>
                        <div class="text-muted small">Campaign: <?php echo htmlspecialchars($campaign['name'] ?? ''); ?></div>
                    </div>
                    <a href="campaigns.php" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-left me-1"></i>Back
                    </a>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger"><?php echo implode('<br>', array_map('htmlspecialchars', $errors)); ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>

                <div class="row g-4">
                    <div class="col-lg-5">
                        <div class="card shadow-sm">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">Create task</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" class="row g-3">
                                    <input type="hidden" name="action" value="create_task">
                                    <div class="col-12">
                                        <label class="form-label">Task name</label>
                                        <input type="text" class="form-control" name="name" required>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Description</label>
                                        <textarea class="form-control" name="description" rows="3"></textarea>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Type</label>
                                        <select class="form-select" name="task_type">
                                            <option value="on_site">On-site</option>
                                            <option value="online">Online</option>
                                            <option value="support" selected>Support</option>
                                            <option value="logistics">Logistics</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Required volunteers</label>
                                        <input type="number" class="form-control" name="required_volunteers" value="1" min="1">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Estimated hours</label>
                                        <input type="number" class="form-control" name="estimated_hours" value="0" min="0" step="0.5">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Start</label>
                                        <input type="datetime-local" class="form-control" name="start_at">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">End</label>
                                        <input type="datetime-local" class="form-control" name="end_at">
                                    </div>
                                    <div class="col-12 text-end">
                                        <button class="btn btn-primary" type="submit">
                                            <i class="bi bi-plus-circle me-1"></i>Create
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div class="card shadow-sm mt-4">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">Select volunteers</h5>
                                <div class="text-muted small">Pick volunteers first, then assign to a task.</div>
                            </div>
                            <div class="card-body">
                                <?php if (empty($campaignVolunteers)): ?>
                                    <div class="text-muted">No approved volunteers yet.</div>
                                <?php else: ?>
                                    <div class="mb-2">
                                        <input type="text" class="form-control form-control-sm" id="volunteerSearch" placeholder="Search volunteer...">
                                    </div>

                                    <form method="POST" id="bulkAssignForm">
                                        <input type="hidden" name="action" value="bulk_assign_panel">

                                        <div class="border rounded p-2" style="max-height: 220px; overflow:auto;">
                                            <?php foreach ($campaignVolunteers as $v): ?>
                                                <div class="form-check volunteer-row">
                                                    <input class="form-check-input" type="checkbox" name="volunteers[]" value="<?php echo (int)$v['user_id']; ?>" id="vol<?php echo (int)$v['user_id']; ?>">
                                                    <label class="form-check-label" for="vol<?php echo (int)$v['user_id']; ?>">
                                                        <?php echo htmlspecialchars($v['name'] . ' (' . $v['email'] . ')'); ?>
                                                    </label>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>

                                        <div class="row g-2 mt-3">
                                            <div class="col-12">
                                                <label class="form-label form-label-sm mb-1">Assign to task</label>
                                                <select class="form-select form-select-sm" name="task_id" required>
                                                    <option value="">-- Select task --</option>
                                                    <?php foreach ($tasks as $t): ?>
                                                        <option value="<?php echo (int)$t['task_id']; ?>">
                                                            <?php echo htmlspecialchars($t['name']); ?> (<?php echo htmlspecialchars($t['status']); ?>)
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-7">
                                                <label class="form-label form-label-sm mb-1">Role</label>
                                                <select class="form-select form-select-sm" name="role">
                                                    <option value="member">Member</option>
                                                    <option value="leader">Leader</option>
                                                </select>
                                            </div>
                                            <div class="col-5 d-grid align-self-end">
                                                <button class="btn btn-sm btn-success" type="submit">
                                                    <i class="bi bi-person-check me-1"></i>Assign
                                                </button>
                                            </div>
                                        </div>

                                        <div class="d-flex justify-content-between mt-2">
                                            <button type="button" class="btn btn-sm btn-outline-secondary" id="selectAllVol">Select all</button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" id="clearAllVol">Clear</button>
                                        </div>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="card shadow-sm mt-4">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">Assignments (latest)</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($taskAssignments)): ?>
                                    <div class="text-muted">No assignments yet.</div>
                                <?php else: ?>
                                    <div class="list-group">
                                        <?php foreach (array_slice($taskAssignments, 0, 8) as $a): ?>
                                            <div class="list-group-item">
                                                <div class="fw-semibold"><?php echo htmlspecialchars($a['task_name'] ?? ''); ?></div>
                                                <div class="text-muted small"><?php echo htmlspecialchars(($a['name'] ?? '') . ' · ' . ($a['role'] ?? '')); ?></div>
                                                <span class="badge bg-secondary"><?php echo htmlspecialchars($a['status'] ?? ''); ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-7">
                        <div class="card shadow-sm">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Tasks</h5>
                                <span class="text-muted small"><?php echo count($tasks); ?> tasks</span>
                            </div>
                            <div class="table-responsive">
                                <table class="table align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Task</th>
                                            <th>Status</th>
                                            <th>Assign</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($tasks)): ?>
                                            <tr><td colspan="3" class="text-center text-muted py-4">No tasks yet.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($tasks as $task): ?>
                                                <tr>
                                                    <td>
                                                        <div class="fw-semibold"><?php echo htmlspecialchars($task['name']); ?></div>
                                                        <div class="text-muted small">
                                                            <?php echo htmlspecialchars($task['task_type']); ?> · <?php echo (int)$task['required_volunteers']; ?> volunteers
                                                        </div>
                                                    </td>
                                                    <td style="width: 180px;">
                                                        <form method="POST" class="d-flex gap-2">
                                                            <input type="hidden" name="action" value="update_task_status">
                                                            <input type="hidden" name="task_id" value="<?php echo (int)$task['task_id']; ?>">
                                                            <select name="status" class="form-select form-select-sm">
                                                                <?php
                                                                $options = ['open', 'assigned', 'in_progress', 'completed', 'cancelled'];
                                                                foreach ($options as $opt):
                                                                ?>
                                                                    <option value="<?php echo $opt; ?>" <?php echo $opt === $task['status'] ? 'selected' : ''; ?>>
                                                                        <?php echo ucfirst(str_replace('_', ' ', $opt)); ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                            <button class="btn btn-sm btn-outline-primary" type="submit">Save</button>
                                                        </form>
                                                    </td>
                                                    <td style="width: 260px;">
                                                        <form method="POST" class="row g-2">
                                                            <input type="hidden" name="action" value="assign_volunteers">
                                                            <input type="hidden" name="task_id" value="<?php echo (int)$task['task_id']; ?>">
                                                            <div class="col-12">
                                                                <select class="form-select form-select-sm" name="volunteers[]" multiple>
                                                                    <?php foreach ($campaignVolunteers as $v): ?>
                                                                        <option value="<?php echo (int)$v['user_id']; ?>">
                                                                            <?php echo htmlspecialchars($v['name'] . ' (' . $v['email'] . ')'); ?>
                                                                        </option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>
                                                            <div class="col-7">
                                                                <select class="form-select form-select-sm" name="role">
                                                                    <option value="member">Member</option>
                                                                    <option value="leader">Leader</option>
                                                                </select>
                                                            </div>
                                                            <div class="col-5 d-grid">
                                                                <button class="btn btn-sm btn-success" type="submit">Assign</button>
                                                            </div>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/data-refresh.js" data-base="../" data-interval="5000"></script>
    <script>
        const volunteerSearch = document.getElementById('volunteerSearch');
        const volunteerRows = document.querySelectorAll('.volunteer-row');
        const selectAllBtn = document.getElementById('selectAllVol');
        const clearAllBtn = document.getElementById('clearAllVol');

        volunteerSearch?.addEventListener('input', function () {
            const q = this.value.toLowerCase().trim();
            volunteerRows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(q) ? '' : 'none';
            });
        });

        selectAllBtn?.addEventListener('click', function () {
            document.querySelectorAll('input[name="volunteers[]"]').forEach(cb => {
                if (cb.closest('.volunteer-row')?.style.display !== 'none') {
                    cb.checked = true;
                }
            });
        });

        clearAllBtn?.addEventListener('click', function () {
            document.querySelectorAll('input[name="volunteers[]"]').forEach(cb => cb.checked = false);
        });
    </script>
</body>
</html>
