<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/volunteer_tracking_helper.php';

requireAdmin();

$campaigns = Database::fetchAll("
    SELECT c.*,
           (SELECT COUNT(*) FROM campaign_volunteers cv WHERE cv.campaign_id = c.campaign_id AND cv.status = 'approved') AS volunteers_joined,
           (SELECT COUNT(*) FROM campaign_tasks t WHERE t.campaign_id = c.campaign_id) AS tasks_total,
           (SELECT COUNT(*) FROM campaign_tasks t WHERE t.campaign_id = c.campaign_id AND t.status = 'completed') AS tasks_completed
    FROM campaigns c
    ORDER BY c.created_at DESC
    LIMIT 100
");

$pageTitle = 'Assignments';
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
                    <h1 class="h2">Task Assignments</h1>
                </div>

                <div class="card shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Campaigns</h5>
                        <span class="text-muted small"><?php echo count($campaigns); ?> campaigns</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Campaign</th>
                                    <th>Status</th>
                                    <th>Volunteers</th>
                                    <th>Tasks</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($campaigns)): ?>
                                    <tr><td colspan="5" class="text-center text-muted py-4">No campaigns found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($campaigns as $c): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-semibold"><?php echo htmlspecialchars($c['name'] ?? ''); ?></div>
                                                <div class="text-muted small">
                                                    <?php echo htmlspecialchars(($c['start_date'] ?? '') . ' - ' . ($c['end_date'] ?? '')); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary"><?php echo htmlspecialchars($c['status'] ?? ''); ?></span>
                                            </td>
                                            <td class="fw-semibold"><?php echo (int)($c['volunteers_joined'] ?? 0); ?></td>
                                            <td>
                                                <span class="fw-semibold"><?php echo (int)($c['tasks_completed'] ?? 0); ?></span>
                                                <span class="text-muted">/ <?php echo (int)($c['tasks_total'] ?? 0); ?></span>
                                            </td>
                                            <td class="text-end">
                                                <a class="btn btn-sm btn-primary" href="campaign-tasks.php?id=<?php echo (int)$c['campaign_id']; ?>">
                                                    <i class="bi bi-list-task me-1"></i>Manage tasks
                                                </a>
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

