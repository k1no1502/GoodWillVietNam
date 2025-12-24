<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Kiểm tra xem người dùng đã đăng nhập chưa
$isLoggedIn = isset($_SESSION['user_id']);
$userRole = $isLoggedIn ? $_SESSION['role'] : 'guest';

// Lấy thống kê
try {
    $stats = getStatistics();
} catch (Exception $e) {
    error_log("Error getting statistics: " . $e->getMessage());
    $stats = [
        'users' => 0,
        'donations' => 0,
        'items' => 0,
        'campaigns' => 0
    ];
}

$pageTitle = "Trang chủ";
include 'includes/header.php';
?>
<!-- Hero Section -->
    <section class="hero-section bg-success text-white py-5">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h1 class="display-4 fw-bold mb-4">Chung tay vì cộng đồng</h1>
                    <p class="lead mb-4">Hệ thống thiện nguyện kết nối những tấm lòng nhân ái, tạo nên những điều kỳ diệu cho cộng đồng.</p>
                    <div class="d-flex gap-3">
                        <a href="donate.php" class="btn btn-light btn-lg">
                            <i class="bi bi-heart-fill me-2"></i>Quyên góp ngay
                        </a>
                        <a href="items.php" class="btn btn-outline-light btn-lg">
                            <i class="bi bi-box-seam me-2"></i>Xem vật phẩm
                        </a>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="text-center">
                        <i class="bi bi-heart-pulse display-1 text-warning"></i>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Statistics Section -->
    <section class="py-5">
        <div class="container">
            <div class="row text-center">
                <div class="col-md-3 mb-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <i class="bi bi-people-fill text-success display-4 mb-3"></i>
                            <h3 class="fw-bold text-success" id="totalUsers"><?php echo number_format($stats['users'] ?? 0); ?></h3>
                            <p class="text-muted">Người dùng</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <i class="bi bi-gift-fill text-primary display-4 mb-3"></i>
                            <h3 class="fw-bold text-primary" id="totalDonations"><?php echo number_format($stats['donations'] ?? 0); ?></h3>
                            <p class="text-muted">Quyên góp</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <i class="bi bi-box-seam text-warning display-4 mb-3"></i>
                            <h3 class="fw-bold text-warning" id="totalItems"><?php echo number_format($stats['items'] ?? 0); ?></h3>
                            <p class="text-muted">Vật phẩm</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <i class="bi bi-trophy-fill text-danger display-4 mb-3"></i>
                            <h3 class="fw-bold text-danger" id="totalCampaigns"><?php echo number_format($stats['campaigns'] ?? 0); ?></h3>
                            <p class="text-muted">Chiến dịch</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 mx-auto text-center mb-5">
                    <h2 class="display-5 fw-bold mb-3">Tính năng nổi bật</h2>
                    <p class="lead text-muted">Hệ thống được thiết kế để tối ưu hóa quá trình thiện nguyện và kết nối cộng đồng</p>
                </div>
            </div>
            
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center p-4">
                            <i class="bi bi-shield-check text-success display-4 mb-3"></i>
                            <h5 class="fw-bold">Bảo mật cao</h5>
                            <p class="text-muted">Hệ thống bảo mật đa tầng, mã hóa dữ liệu và xác thực người dùng an toàn.</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center p-4">
                            <i class="bi bi-graph-up text-primary display-4 mb-3"></i>
                            <h5 class="fw-bold">Báo cáo chi tiết</h5>
                            <p class="text-muted">Thống kê và báo cáo trực quan giúp theo dõi tác động của các hoạt động thiện nguyện.</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center p-4">
                            <i class="bi bi-phone text-warning display-4 mb-3"></i>
                            <h5 class="fw-bold">Responsive</h5>
                            <p class="text-muted">Giao diện thân thiện, tối ưu cho mọi thiết bị từ desktop đến mobile.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Recent Donations Section -->
    <section class="py-5">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 mx-auto text-center mb-5">
                    <h2 class="display-5 fw-bold mb-3">Quyên góp gần đây</h2>
                    <p class="lead text-muted">Những đóng góp ý nghĩa từ cộng đồng</p>
                </div>
            </div>
            
            <div class="row" id="recentDonations">
                <!-- Recent donations will be loaded here via AJAX -->
            </div>
        </div>
    </section>


<?php include 'includes/footer.php'; ?>
