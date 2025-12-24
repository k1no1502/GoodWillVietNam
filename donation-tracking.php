<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/donation_tracking_helpers.php';

requireLogin();
ensureDonationTrackingTable();
$trackingTemplates = getDonationTrackingTemplates();

$donation_id = (int)($_GET['id'] ?? 0);
if ($donation_id <= 0) {
    header('Location: my-donations.php');
    exit();
}

$donation = Database::fetch(
    "SELECT d.*, c.name AS category_name 
     FROM donations d 
     LEFT JOIN categories c ON d.category_id = c.category_id 
     WHERE d.donation_id = ? AND d.user_id = ?",
    [$donation_id, $_SESSION['user_id']]
);

if (!$donation) {
    header('Location: my-donations.php');
    exit();
}

$inventory = Database::fetch(
    "SELECT * FROM inventory WHERE donation_id = ? LIMIT 1",
    [$donation_id]
);

$trackingRecordsMap = getDonationTrackingMap([$donation_id]);
$trackingRecords = $trackingRecordsMap[$donation_id] ?? [];

$statusLabels = [
    'pending'   => 'Chờ duyệt',
    'approved'  => 'Đã duyệt',
    'rejected'  => 'Bị từ chối',
    'cancelled' => 'Đã hủy'
];

$inventoryStatusLabels = [
    'available' => 'Đang lưu kho',
    'reserved'  => 'Đã gửi cho chiến dịch',
    'sold'      => 'Đã phân phối',
    'damaged'   => 'Đã loại bỏ',
    'disposed'  => 'Đã xả lý'
];

$trackingSteps = [];
foreach ($trackingTemplates as $key => $template) {
    $record = $trackingRecords[$key] ?? null;
    $status = $record['step_status'] ?? ($template['default_status'] ?? 'pending');
    $label = $record['step_label'] ?? $template['label'];
    $description = $record['description'] ?? $template['description'];
    $timestamp = $record['event_time'] ?? null;
    $note = $record['note'] ?? '';

    if (!$record) {
        switch ($key) {
            case 'submitted':
                $status = 'completed';
                $timestamp = $donation['created_at'];
                break;
            case 'review':
                $status = in_array($donation['status'], ['approved', 'rejected', 'cancelled'], true) ? 'completed' : 'in_progress';
                $timestamp = $donation['created_at'];
                break;
            case 'approved':
                if ($donation['status'] === 'approved') {
                    $status = 'completed';
                    $timestamp = $donation['updated_at'];
                    $note = $donation['admin_notes'] ?? '';
                }
                break;
            case 'distributed':
                if ($inventory && in_array($inventory['status'], ['reserved','sold','damaged','disposed'], true)) {
                    $status = 'completed';
                    $timestamp = $inventory['updated_at'];
                    $note = $inventoryStatusLabels[$inventory['status']] ?? '';
                }
                break;
        }
    } else {
        if (!$timestamp) {
            if (in_array($key, ['submitted','review'], true)) {
                $timestamp = $donation['created_at'];
            } elseif ($key === 'approved' && $donation['status'] === 'approved') {
                $timestamp = $donation['updated_at'];
            } elseif ($key === 'distributed' && $inventory) {
                $timestamp = $inventory['updated_at'];
            }
        }
        if ($note === '' && $key === 'approved' && !empty($donation['admin_notes'])) {
            $note = $donation['admin_notes'];
        }
        if ($note === '' && $key === 'distributed' && $inventory) {
            $note = $inventoryStatusLabels[$inventory['status']] ?? '';
        }
    }

    $trackingSteps[] = [
        'key'        => $key,
        'label'      => $label,
        'description'=> $description,
        'status'     => $status,
        'completed'  => $status === 'completed',
        'timestamp'  => $timestamp,
        'note'       => $note
    ];
}

$completedSteps = 0;
$inProgressSteps = 0;
foreach ($trackingSteps as $step) {
    if ($step['status'] === 'completed') {
        $completedSteps++;
    } elseif ($step['status'] === 'in_progress') {
        $inProgressSteps++;
    }
}
$progressPercent = (int)round((($completedSteps + ($inProgressSteps * 0.5)) / max(count($trackingSteps), 1)) * 100);
$statusBadgeMap = [
    'pending'     => ['text' => 'Chờ xử lý', 'class' => 'bg-secondary text-white'],
    'in_progress' => ['text' => 'Đang thực hiện', 'class' => 'bg-warning text-dark'],
    'completed'   => ['text' => 'Hoàn thành', 'class' => 'bg-success text-white']
];
$pageTitle = "Theo dõi quyên góp";
include 'includes/header.php';
?>

<div class="container mt-5 pt-5">
    <div class="row mb-4">
        <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h1 class="fw-bold mb-1">
                    <i class="bi bi-box-seam me-2"></i>Theo dõi quyên góp
                </h1>
                <p class="text-muted mb-0">
                    Mã quyên g #<?php echo str_pad($donation_id, 6, '0', STR_PAD_LEFT); ?>
                </p>
            </div>
            <div class="d-flex gap-2">
                <a href="my-donations.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>Quay lại danh sách 
                </a>
                <a href="donate.php" class="btn btn-success">
                    <i class="bi bi-plus-circle me-1"></i>Tạo quyên góp mới 
                </a>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <i class="bi bi-flag me-2"></i>Tiến trình xử lý
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Tiến độ</span>
                            <strong><?php echo $progressPercent; ?>%</strong>
                        </div>
                        <div class="progress" style="height: 18px;">
                            <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $progressPercent; ?>%;">
                                <?php echo $progressPercent; ?>%
                            </div>
                        </div>
                    </div>

                    <div class="timeline">
                        <?php foreach ($trackingSteps as $step): ?>
                            <?php
                                $icon = 'circle text-secondary';
                                if ($step['status'] === 'completed') {
                                    $icon = 'check-circle-fill text-success';
                                } elseif ($step['status'] === 'in_progress') {
                                    $icon = 'arrow-repeat text-warning';
                                }
                                $badgeInfo = $statusBadgeMap[$step['status']] ?? null;
                            ?>
                            <div class="timeline-item <?php echo $step['completed'] ? 'completed' : ''; ?>">
                                <div class="timeline-marker">
                                    <i class="bi bi-<?php echo $icon; ?>"></i>
                                </div>
                                <div class="timeline-content">
                                    <div class="d-flex align-items-center gap-2">
                                        <h6 class="mb-1"><?php echo $step['label']; ?></h6>
                                        <?php if ($badgeInfo): ?>
                                            <span class="badge <?php echo $badgeInfo['class']; ?> small">
                                                <?php echo $badgeInfo['text']; ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="mb-1 text-muted small"><?php echo $step['description']; ?></p>
                                    <?php if ($step['timestamp']): ?>
                                        <p class="small text-muted mb-0">
                                            <?php echo formatDate($step['timestamp'], 'd/m/Y H:i'); ?>
                                        </p>
                                    <?php endif; ?>
                                    <?php if (!empty($step['note'])): ?>
                                        <p class="small text-info mb-0"><?php echo htmlspecialchars($step['note']); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="bi bi-box me-2"></i>Thông tin vật phẩm</h5>
                </div>
                <div class="card-body">
                    <p class="mb-1"><strong>Tên Vật Phẩm:</strong> <?php echo htmlspecialchars($donation['item_name']); ?></p>
                    <p class="mb-1"><strong>Danh mục:</strong> <?php echo htmlspecialchars($donation['category_name'] ?? 'KhÃ´ng xÃ¡c Ä‘á»‹nh'); ?></p>
                    <p class="mb-1"><strong>Số lượng:</strong> <?php echo (int)$donation['quantity']; ?> <?php echo htmlspecialchars($donation['unit']); ?></p>
                    <p class="mb-1"><strong>Tình trạng:</strong> <?php echo ucfirst($donation['condition_status']); ?></p>
                    <p class="mb-1">
                        <strong>Trạng thái hiện tại::</strong>
                        <?php echo $statusLabels[$donation['status']] ?? $donation['status']; ?>
                    </p>
                    <?php if ($donation['description']): ?>
                        <p class="mt-3 mb-0 text-muted"><?php echo nl2br(htmlspecialchars($donation['description'])); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($inventory): ?>
                <div class="card shadow-sm">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">
                            <i class="bi bi-archive me-2"></i>Kho lưu trữ
                        </h5>
                    </div>
                    <div class="card-body">
                        <p class="mb-1"><strong>Mã kho:</strong> #<?php echo str_pad($inventory['item_id'], 5, '0', STR_PAD_LEFT); ?></p>
                        <p class="mb-1"><strong>Trạng thái kho:</strong> <?php echo $inventoryStatusLabels[$inventory['status']] ?? $inventory['status']; ?></p>
                        <?php if (!empty($inventory['location'])): ?>
                            <p class="mb-1"><strong>Vá»‹ trÃ­:</strong> <?php echo htmlspecialchars($inventory['location']); ?></p>
                        <?php endif; ?>
                        <p class="mb-0 text-muted small">Cập nhật: <?php echo formatDate($inventory['updated_at'], 'd/m/Y H:i'); ?></p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<style>
.timeline {
    position: relative;
    margin-left: 1rem;
}
.timeline::before {
    content: '';
    position: absolute;
    left: 12px;
    top: 0;
    width: 2px;
    height: 100%;
    background-color: #dee2e6;
}
.timeline-item {
    position: relative;
    margin-bottom: 1.5rem;
    padding-left: 2.5rem;
}
.timeline-item .timeline-marker {
    position: absolute;
    left: -5px;
    top: 0;
    background: #fff;
}
.timeline-item.completed .timeline-content h6 {
    color: #198754;
}
</style>

