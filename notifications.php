<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/notifications_helper.php';

requireLogin();
processScheduledAdminNotifications();

$pageTitle = 'Notifications';
$userId = $_SESSION['user_id'];

function sanitizeDate($date)
{
    if (empty($date)) {
        return null;
    }
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : null;
}

$filters = [
    'date_from' => sanitizeDate($_GET['date_from'] ?? ''),
    'date_to' => sanitizeDate($_GET['date_to'] ?? ''),
    'status' => $_GET['status'] ?? 'all',
    'type' => $_GET['type'] ?? ''
];

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

$queryFilters = $filters;
if ($filters['status'] === 'all') {
    $queryFilters['status'] = null;
}
if (empty($filters['type']) || $filters['type'] === 'all') {
    $queryFilters['type'] = null;
}

$total = countUserNotifications($userId, $queryFilters);
$notifications = fetchUserNotifications($userId, $queryFilters, $perPage, $offset);
$totalPages = $perPage > 0 ? (int)ceil($total / $perPage) : 1;
$unreadCount = getUnreadNotificationCount($userId);

include 'includes/header.php';
?>

<div class="container py-5">
    <div class="row">
        <div class="col-lg-10 mx-auto">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-1">Notifications</h2>
                    <p class="text-muted mb-0">You have <?php echo $unreadCount; ?> unread notifications.</p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-secondary" id="resetFiltersBtn">
                        <i class="bi bi-arrow-clockwise me-1"></i>Reset
                    </button>
                    <button class="btn btn-success" id="markAllBtn">
                        <i class="bi bi-check2-all me-1"></i>Mark all as read
                    </button>
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <form class="row gy-3" id="filterForm" method="GET">
                        <div class="col-md-3">
                            <label class="form-label">Date from</label>
                            <input type="date" class="form-control" name="date_from" value="<?php echo htmlspecialchars($filters['date_from'] ?? ''); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Date to</label>
                            <input type="date" class="form-control" name="date_to" value="<?php echo htmlspecialchars($filters['date_to'] ?? ''); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <?php
                                $statusOptions = [
                                    'all' => 'All',
                                    'unread' => 'Unread',
                                    'read' => 'Read'
                                ];
                                foreach ($statusOptions as $value => $label):
                                ?>
                                    <option value="<?php echo $value; ?>" <?php echo $filters['status'] === $value ? 'selected' : ''; ?>>
                                        <?php echo $label; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Type</label>
                            <select class="form-select" name="type">
                                <?php
                                $typeOptions = [
                                    '' => 'All',
                                    'system' => 'System',
                                    'campaign' => 'Campaign',
                                    'donation' => 'Donation',
                                    'order' => 'Order',
                                    'general' => 'General'
                                ];
                                foreach ($typeOptions as $value => $label):
                                ?>
                                    <option value="<?php echo $value; ?>" <?php echo ($filters['type'] ?? '') === $value ? 'selected' : ''; ?>>
                                        <?php echo $label; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 text-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-funnel me-1"></i>Apply filter
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="list-group list-group-flush" id="notificationList">
                    <?php if (empty($notifications)): ?>
                        <div class="list-group-item text-center py-5 text-muted">
                            No notifications found.
                        </div>
                    <?php else: ?>
                        <?php foreach ($notifications as $notification): ?>
                            <?php
                            $isUnread = !$notification['is_read'];
                            $badgeClass = match ($notification['category']) {
                                'campaign' => 'bg-warning',
                                'donation' => 'bg-success',
                                'order' => 'bg-info',
                                default => 'bg-secondary'
                            };
                            ?>
                            <button type="button" class="list-group-item list-group-item-action d-flex gap-3 align-items-start notification-item <?php echo $isUnread ? 'bg-light' : ''; ?>"
                                data-id="<?php echo $notification['notify_id']; ?>"
                                data-title="<?php echo htmlspecialchars($notification['title']); ?>"
                                data-message="<?php echo htmlspecialchars($notification['message']); ?>"
                                data-time="<?php echo htmlspecialchars(formatDate($notification['created_at'])); ?>">
                                <div class="flex-shrink-0">
                                    <i class="bi bi-bell fs-4 text-secondary"></i>
                                </div>
                                <div class="flex-grow-1 text-start">
                                    <div class="d-flex justify-content-between mb-1">
                                        <h6 class="mb-0 <?php echo $isUnread ? 'fw-bold' : ''; ?>">
                                            <?php echo htmlspecialchars($notification['title']); ?>
                                        </h6>
                                        <span class="badge <?php echo $badgeClass; ?>">
                                            <?php echo ucfirst($notification['category']); ?>
                                        </span>
                                    </div>
                                    <p class="text-muted mb-1 small">
                                        <?php
                                        $preview = function_exists('mb_strimwidth')
                                            ? mb_strimwidth($notification['message'], 0, 120, '...')
                                            : substr($notification['message'], 0, 120) . (strlen($notification['message']) > 120 ? '...' : '');
                                        echo htmlspecialchars($preview);
                                        ?>
                                    </p>
                                    <small class="text-muted">
                                        <i class="bi bi-clock me-1"></i><?php echo htmlspecialchars(formatDate($notification['created_at'])); ?>
                                        <?php if ($isUnread): ?>
                                            <span class="ms-2 text-success">• Unread</span>
                                        <?php endif; ?>
                                    </small>
                                </div>
                            </button>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($totalPages > 1): ?>
                <nav class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <?php
                            $query = $_GET;
                            $query['page'] = $i;
                            ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="<?php echo htmlspecialchars('?' . http_build_query($query)); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Detail Modal -->
<div class="modal fade" id="notificationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Notification</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <h5 id="modalTitle"></h5>
                <p class="text-muted small mb-2" id="modalTime"></p>
                <p id="modalMessage" class="mb-0"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = new bootstrap.Modal(document.getElementById('notificationModal'));
    const notificationItems = document.querySelectorAll('.notification-item');
    const markAllBtn = document.getElementById('markAllBtn');
    const resetBtn = document.getElementById('resetFiltersBtn');
    const form = document.getElementById('filterForm');

    resetBtn?.addEventListener('click', function() {
        form.reset();
        window.location.href = 'notifications.php';
    });

    notificationItems.forEach(item => {
        item.addEventListener('click', function() {
            const id = this.dataset.id;
            fetch(`api/notifications.php?action=detail&id=${id}`)
                .then(res => res.json())
                .then(data => {
                    if (!data.success) {
                        throw new Error(data.message || 'Failed to load notification');
                    }
                    document.getElementById('modalTitle').textContent = data.data.title;
                    document.getElementById('modalTime').textContent = data.data.created_at;
                    document.getElementById('modalMessage').textContent = data.data.message;
                    this.classList.remove('bg-light', 'fw-bold');
                    modal.show();
                })
                .catch(err => alert(err.message));
        });
    });

    markAllBtn?.addEventListener('click', function() {
        if (!confirm('Mark all notifications as read?')) {
            return;
        }
        fetch('api/notifications.php?action=mark-all', {
            method: 'POST'
        })
            .then(res => res.json())
            .then(data => {
                if (!data.success) {
                    throw new Error(data.message || 'Failed to update notifications');
                }
                window.location.reload();
            })
            .catch(err => alert(err.message));
    });
});
</script>

<?php include 'includes/footer.php'; ?>
