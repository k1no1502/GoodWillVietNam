<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Get active campaigns
$sql = "SELECT c.*, u.name as creator_name,
        (SELECT COUNT(*) FROM campaign_volunteers WHERE campaign_id = c.campaign_id) as volunteer_count,
        (SELECT COUNT(*) FROM campaign_donations WHERE campaign_id = c.campaign_id) as donation_count
        FROM campaigns c
        LEFT JOIN users u ON c.created_by = u.user_id
        WHERE c.status = 'active' AND c.end_date >= CURDATE()
        ORDER BY c.created_at DESC";
$campaigns = Database::fetchAll($sql);

$pageTitle = "Chiến dịch";
include 'includes/header.php';
?>

<!-- Main Content -->
<div class="container py-5 mt-5">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="display-5 fw-bold text-success mb-3">
                <i class="bi bi-trophy me-2"></i>Chiến dịch thiện nguyện
            </h1>
            <p class="lead text-muted">Tham gia các chiến dịch ý nghĩa và góp phần tạo nên sự thay đổi tích cực</p>
        </div>
    </div>

    <!-- Create Campaign Button -->
    <?php if (isLoggedIn()): ?>
        <div class="row mb-4">
            <div class="col-12">
                <a href="create-campaign.php" class="btn btn-success btn-lg">
                    <i class="bi bi-plus-circle me-2"></i>Tạo chiến dịch mới
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- Campaigns Grid -->
    <?php if (empty($campaigns)): ?>
        <div class="row">
            <div class="col-12">
                <div class="text-center py-5">
                    <i class="bi bi-trophy display-1 text-muted"></i>
                    <h3 class="mt-3 text-muted">Chưa có chiến dịch nào</h3>
                    <p class="text-muted">Hãy tạo chiến dịch đầu tiên để bắt đầu</p>
                    <?php if (isLoggedIn()): ?>
                        <a href="create-campaign.php" class="btn btn-success">Tạo chiến dịch</a>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-success">Đăng nhập để tạo chiến dịch</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="row">
            <?php foreach ($campaigns as $campaign): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <h5 class="card-title text-success"><?php echo htmlspecialchars($campaign['title']); ?></h5>
                                <span class="badge bg-success">Đang diễn ra</span>
                            </div>
                            
                            <p class="card-text text-muted mb-3">
                                <?php echo htmlspecialchars(substr($campaign['description'], 0, 120)); ?>
                                <?php if (strlen($campaign['description']) > 120): ?>...<?php endif; ?>
                            </p>
                            
                            <div class="mb-3">
                                <small class="text-muted">
                                    <i class="bi bi-person me-1"></i>Tạo bởi: <?php echo htmlspecialchars($campaign['creator_name']); ?>
                                </small>
                            </div>
                            
                            <div class="row text-center mb-3">
                                <div class="col-6">
                                    <div class="border-end">
                                        <h6 class="text-success mb-1"><?php echo $campaign['volunteer_count']; ?></h6>
                                        <small class="text-muted">Tình nguyện viên</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <h6 class="text-success mb-1"><?php echo $campaign['donation_count']; ?></h6>
                                    <small class="text-muted">Quyên góp</small>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <small class="text-muted">
                                    <i class="bi bi-calendar me-1"></i>
                                    Kết thúc: <?php echo date('d/m/Y', strtotime($campaign['end_date'])); ?>
                                </small>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <a href="campaign-detail.php?id=<?php echo $campaign['campaign_id']; ?>" 
                                   class="btn btn-outline-success">
                                    <i class="bi bi-eye me-1"></i>Xem chi tiết
                                </a>
                                
                                <?php if (isLoggedIn()): ?>
                                    <div class="btn-group" role="group">
                                        <a href="donate-to-campaign.php?id=<?php echo $campaign['campaign_id']; ?>" 
                                           class="btn btn-success btn-sm">
                                            <i class="bi bi-heart me-1"></i>Quyên góp
                                        </a>
                                        <button type="button" 
                                                class="btn btn-outline-success btn-sm register-volunteer" 
                                                data-campaign-id="<?php echo $campaign['campaign_id']; ?>">
                                            <i class="bi bi-person-plus me-1"></i>Tình nguyện
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <a href="login.php" class="btn btn-success">
                                        <i class="bi bi-lock me-1"></i>Đăng nhập để tham gia
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
// Register as volunteer
document.addEventListener('DOMContentLoaded', function() {
    const volunteerButtons = document.querySelectorAll('.register-volunteer');
    
    volunteerButtons.forEach(button => {
        button.addEventListener('click', function() {
            const campaignId = this.dataset.campaignId;
            
            if (confirm('Bạn có chắc chắn muốn đăng ký làm tình nguyện viên cho chiến dịch này?')) {
                fetch('api/register-volunteer-detail.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        campaign_id: campaignId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Đã đăng ký làm tình nguyện viên thành công!');
                        location.reload();
                    } else {
                        alert('Lỗi: ' + (data.message || 'Không thể đăng ký'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Có lỗi xảy ra khi đăng ký');
                });
            }
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>
