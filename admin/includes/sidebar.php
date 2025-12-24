<?php
$currentPage = basename($_SERVER['PHP_SELF'], '.php');

// Get pending counts
$pendingDonations = Database::fetch("SELECT COUNT(*) as count FROM donations WHERE status = 'pending'")['count'];
$pendingFeedback = Database::fetch("SELECT COUNT(*) as count FROM feedback WHERE status = 'pending'")['count'];
$pendingCampaigns = Database::fetch("SELECT COUNT(*) as count FROM campaigns WHERE status = 'pending' OR status = 'draft'")['count'];
?>
<nav class="col-md-3 col-lg-2 d-md-block admin-sidebar">
    <div class="position-sticky pt-3">
        <div class="text-center mb-4">
            <a href="../index.php" class="text-decoration-none">
                <h5 class="text-white fw-bold">
                    <i class="bi bi-heart-fill me-2"></i>Goodwill Vietnam
                </h5>
            </a>
            <p class="text-white-50 small">Admin Panel</p>
        </div>
        
        <ul class="nav nav-pills flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>" href="dashboard.php">
                    <i class="bi bi-speedometer2 me-2"></i>Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $currentPage === 'donations' ? 'active' : ''; ?>" href="donations.php">
                    <i class="bi bi-heart-fill me-2"></i>Quyên góp
                    <?php if ($pendingDonations > 0): ?>
                        <span class="badge bg-warning ms-2"><?php echo $pendingDonations; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $currentPage === 'inventory' ? 'active' : ''; ?>" href="inventory.php">
                    <i class="bi bi-box-seam me-2"></i>Kho hàng
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $currentPage === 'orders' ? 'active' : ''; ?>" href="orders.php">
                    <i class="bi bi-cart-check me-2"></i>Đơn hàng
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $currentPage === 'users' ? 'active' : ''; ?>" href="users.php">
                    <i class="bi bi-people me-2"></i>Người dùng
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $currentPage === 'campaigns' ? 'active' : ''; ?>" href="campaigns.php">
                    <i class="bi bi-trophy me-2"></i>Chiến dịch
                    <?php if ($pendingCampaigns > 0): ?>
                        <span class="badge bg-warning ms-2"><?php echo $pendingCampaigns; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($currentPage === 'assignments' || $currentPage === 'campaign-tasks') ? 'active' : ''; ?>" href="assignments.php">
                    <i class="bi bi-list-task me-2"></i>Assignments
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $currentPage === 'volunteer-hours' ? 'active' : ''; ?>" href="volunteer-hours.php">
                    <i class="bi bi-clock-history me-2"></i>Volunteer Hours
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $currentPage === 'categories' ? 'active' : ''; ?>" href="categories.php">
                    <i class="bi bi-tags me-2"></i>Danh mục
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $currentPage === 'feedback' ? 'active' : ''; ?>" href="feedback.php">
                    <i class="bi bi-chat-dots me-2"></i>Phản hồi
                    <?php if ($pendingFeedback > 0): ?>
                        <span class="badge bg-warning ms-2"><?php echo $pendingFeedback; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $currentPage === 'notifications' ? 'active' : ''; ?>" href="notifications.php">
                    <i class="bi bi-bell me-2"></i>Notifications
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $currentPage === 'reports' ? 'active' : ''; ?>" href="reports.php">
                    <i class="bi bi-graph-up me-2"></i>Báo cáo
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $currentPage === 'settings' ? 'active' : ''; ?>" href="settings.php">
                    <i class="bi bi-gear me-2"></i>Cài đặt
                </a>
            </li>
            <li class="nav-item mt-4">
                <a class="nav-link" href="../index.php">
                    <i class="bi bi-house me-2"></i>Về trang chủ
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="../logout.php">
                    <i class="bi bi-box-arrow-right me-2"></i>Đăng xuất
                </a>
            </li>
        </ul>
    </div>
</nav>
