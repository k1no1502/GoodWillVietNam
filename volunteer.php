<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

requireLogin();

$pageTitle = "Tham gia Tình nguyện";

// Get available campaigns for volunteering
$availableCampaigns = Database::fetchAll(
    "SELECT c.*, u.name as creator_name,
            (SELECT COUNT(*) FROM campaign_volunteers WHERE campaign_id = c.campaign_id) as volunteer_count
     FROM campaigns c
     LEFT JOIN users u ON c.created_by = u.user_id
     WHERE c.status = 'active' AND c.end_date >= CURDATE()
     ORDER BY c.created_at DESC"
);

include 'includes/header.php';
?>

<!-- Main Content -->
<div class="container py-5 mt-5">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="display-5 fw-bold text-success mb-3">
                <i class="bi bi-people-fill me-2"></i>Tham gia Tình nguyện
            </h1>
            <p class="lead text-muted">Đóng góp sức mình cho cộng đồng thông qua các hoạt động tình nguyện</p>
        </div>
    </div>

    <!-- Available Campaigns -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <i class="bi bi-megaphone me-2"></i>Chiến dịch đang tuyển tình nguyện viên
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($availableCampaigns)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-info-circle display-4 text-muted"></i>
                            <h5 class="mt-3 text-muted">Hiện tại chưa có chiến dịch nào</h5>
                            <p class="text-muted">Hãy quay lại sau để xem các cơ hội tình nguyện mới</p>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($availableCampaigns as $campaign): ?>
                                <div class="col-md-6 mb-4">
                                    <div class="card h-100">
                                        <div class="card-body">
                                            <h5 class="card-title"><?php echo htmlspecialchars($campaign['name']); ?></h5>
                                            <p class="card-text text-muted small">
                                                <?php echo htmlspecialchars(substr($campaign['description'] ?? '', 0, 150)); ?>
                                                <?php if (strlen($campaign['description'] ?? '') > 150): ?>...<?php endif; ?>
                                            </p>
                                            <div class="mb-2">
                                                <small class="text-muted">
                                                    <i class="bi bi-person me-1"></i>Người tạo: <?php echo htmlspecialchars($campaign['creator_name'] ?? 'N/A'); ?>
                                                </small>
                                                <br>
                                                <small class="text-muted">
                                                    <i class="bi bi-people me-1"></i>Tình nguyện viên: <?php echo $campaign['volunteer_count'] ?? 0; ?>
                                                </small>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-muted">
                                                    <i class="bi bi-calendar me-1"></i>
                                                    <?php echo formatDate($campaign['start_date']); ?> - 
                                                    <?php echo formatDate($campaign['end_date']); ?>
                                                </small>
                                                <a href="campaign-detail.php?id=<?php echo $campaign['campaign_id']; ?>" 
                                                   class="btn btn-success btn-sm">
                                                    <i class="bi bi-eye me-1"></i>Xem chi tiết
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>