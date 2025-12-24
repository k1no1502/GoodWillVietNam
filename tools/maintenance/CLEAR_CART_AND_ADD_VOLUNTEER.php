<?php
require_once 'config/database.php';

echo "<h1>🚨 CLEAR CART & ADD VOLUNTEER FEATURE</h1>";

try {
    // 1. XÓA SẠCH CART
    echo "<h2>1. XÓA SẠCH CART</h2>";
    Database::execute("DELETE FROM cart");
    Database::execute("ALTER TABLE cart AUTO_INCREMENT = 1");
    echo "<p style='color: green; font-size: 20px;'>✅ ĐÃ XÓA SẠCH CART</p>";
    
    // 2. THÊM ITEM VÀO CART VỚI QUANTITY = 1
    echo "<h2>2. THÊM ITEM VÀO CART VỚI QUANTITY = 1</h2>";
    $firstItem = Database::fetch("SELECT item_id FROM inventory WHERE is_for_sale = TRUE AND status = 'available' LIMIT 1");
    if ($firstItem) {
        $itemId = $firstItem['item_id'];
        Database::execute("INSERT INTO cart (user_id, item_id, quantity, created_at) VALUES (1, ?, 1, NOW())", [$itemId]);
        echo "<p style='color: green; font-size: 20px;'>✅ ĐÃ THÊM VÀO CART VỚI QUANTITY = 1</p>";
        
        // Kiểm tra
        $cartItem = Database::fetch("SELECT * FROM cart WHERE user_id = 1");
        echo "<p>Cart item: " . json_encode($cartItem) . "</p>";
    }
    
    // 3. THÊM CHỨC NĂNG TÌNH NGUYỆN VIÊN VÀO HEADER
    echo "<h2>3. THÊM CHỨC NĂNG TÌNH NGUYỆN VIÊN VÀO HEADER</h2>";
    
    $headerContent = '<?php
session_start();
require_once \'config/database.php\';
require_once \'includes/functions.php\';

$pageTitle = $pageTitle ?? "Goodwill Vietnam";
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - Goodwill Vietnam</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="assets/css/style.css" rel="stylesheet">
    
    <style>
        .navbar-brand {
            font-weight: bold;
            font-size: 1.5rem;
        }
        .nav-link {
            font-weight: 500;
            transition: color 0.3s ease;
        }
        .nav-link:hover {
            color: #28a745 !important;
        }
        .badge {
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        .volunteer-btn {
            background: linear-gradient(45deg, #ff6b6b, #ff8e8e);
            border: none;
            color: white;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        .volunteer-btn:hover {
            background: linear-gradient(45deg, #ff5252, #ff7979);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm fixed-top">
        <div class="container">
            <a class="navbar-brand text-success" href="index.php">
                <i class="bi bi-heart-fill me-2"></i>Goodwill Vietnam
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">
                            <i class="bi bi-house me-1"></i>Trang chủ
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="donate.php">
                            <i class="bi bi-gift me-1"></i>Quyên góp
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="shop.php">
                            <i class="bi bi-shop me-1"></i>Shop Bán Hàng
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="campaigns.php">
                            <i class="bi bi-megaphone me-1"></i>Chiến dịch
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="about.php">
                            <i class="bi bi-info-circle me-1"></i>Giới thiệu
                        </a>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <?php if (isLoggedIn()): ?>
                        <!-- Volunteer Button -->
                        <li class="nav-item me-2">
                            <a href="volunteer.php" class="btn volunteer-btn btn-sm">
                                <i class="bi bi-people-fill me-1"></i>Tham gia Tình nguyện
                            </a>
                        </li>
                        
                        <!-- Cart -->
                        <li class="nav-item me-2">
                            <a href="cart.php" class="nav-link position-relative">
                                <i class="bi bi-cart3"></i>
                                <span class="badge bg-warning text-dark" id="cart-count">0</span>
                            </a>
                        </li>
                        
                        <!-- User Menu -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="bi bi-person-circle me-1"></i><?php echo htmlspecialchars($_SESSION[\'username\']); ?>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="profile.php">
                                    <i class="bi bi-person me-2"></i>Hồ sơ
                                </a></li>
                                <li><a class="dropdown-item" href="my-donations.php">
                                    <i class="bi bi-gift me-2"></i>Quyên góp của tôi
                                </a></li>
                                <li><a class="dropdown-item" href="my-orders.php">
                                    <i class="bi bi-bag me-2"></i>Đơn hàng của tôi
                                </a></li>
                                <li><a class="dropdown-item" href="volunteer.php">
                                    <i class="bi bi-people me-2"></i>Tình nguyện viên
                                </a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="change-password.php">
                                    <i class="bi bi-key me-2"></i>Đổi mật khẩu
                                </a></li>
                                <li><a class="dropdown-item" href="logout.php">
                                    <i class="bi bi-box-arrow-right me-2"></i>Đăng xuất
                                </a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a href="login.php" class="btn btn-outline-success me-2">
                                <i class="bi bi-box-arrow-in-right me-1"></i>Đăng nhập
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="register.php" class="btn btn-success">
                                <i class="bi bi-person-plus me-1"></i>Đăng ký
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Add top margin for fixed navbar -->
    <div style="margin-top: 80px;"></div>

    <script>
        <?php if (isLoggedIn()): ?>
        document.addEventListener(\'DOMContentLoaded\', function() {
            fetch(\'<?php echo isset($baseUrl) ? $baseUrl : \'\'; ?>api/get-cart-count.php\')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const cartCount = document.getElementById(\'cart-count\');
                        if (cartCount) {
                            cartCount.textContent = data.count;
                            if (data.count > 0) {
                                cartCount.classList.add(\'pulse\');
                            }
                        }
                    }
                })
                .catch(error => console.error(\'Error loading cart count:\', error));
        });
        <?php endif; ?>
    </script>
</body>
</html>';

    // Ghi file header.php mới
    file_put_contents('includes/header.php', $headerContent);
    echo "<p style='color: green; font-size: 20px;'>✅ ĐÃ THÊM CHỨC NĂNG TÌNH NGUYỆN VIÊN VÀO HEADER</p>";
    
    // 4. TẠO TRANG VOLUNTEER.PHP
    echo "<h2>4. TẠO TRANG VOLUNTEER.PHP</h2>";
    
    $volunteerContent = '<?php
session_start();
require_once \'config/database.php\';
require_once \'includes/functions.php\';

requireLogin();

$pageTitle = "Tham gia Tình nguyện";

// Get user\'s volunteer registrations
$volunteerRegistrations = Database::fetchAll(
    "SELECT cv.*, c.title as campaign_title, c.description as campaign_description, c.status as campaign_status
     FROM campaign_volunteers cv
     JOIN campaigns c ON cv.campaign_id = c.campaign_id
     WHERE cv.user_id = ? AND cv.status = \'active\'
     ORDER BY cv.created_at DESC",
    [$_SESSION[\'user_id\']]
);

// Get available campaigns for volunteering
$availableCampaigns = Database::fetchAll(
    "SELECT * FROM campaigns 
     WHERE status = \'approved\' AND end_date > NOW()
     ORDER BY created_at DESC"
);

include \'includes/header.php\';
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

    <!-- Volunteer Registrations -->
    <?php if (!empty($volunteerRegistrations)): ?>
        <div class="row mb-5">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">
                            <i class="bi bi-check-circle me-2"></i>Đăng ký tình nguyện của bạn
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($volunteerRegistrations as $registration): ?>
                            <div class="border-bottom p-3 mb-3">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($registration[\'campaign_title\']); ?></h6>
                                        <p class="text-muted small mb-2"><?php echo htmlspecialchars(substr($registration[\'campaign_description\'], 0, 100)) . \'...\'; ?></p>
                                        <div class="d-flex gap-2">
                                            <span class="badge bg-success">Đã đăng ký</span>
                                            <span class="badge bg-info"><?php echo ucfirst($registration[\'campaign_status\']); ?></span>
                                        </div>
                                    </div>
                                    <div class="col-md-4 text-end">
                                        <small class="text-muted">
                                            Đăng ký: <?php echo date(\'d/m/Y\', strtotime($registration[\'created_at\'])); ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

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
                                            <h6 class="card-title"><?php echo htmlspecialchars($campaign[\'title\']); ?></h6>
                                            <p class="card-text text-muted small">
                                                <?php echo htmlspecialchars(substr($campaign[\'description\'], 0, 150)) . \'...\'; ?>
                                            </p>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-muted">
                                                    <i class="bi bi-calendar me-1"></i>
                                                    <?php echo date(\'d/m/Y\', strtotime($campaign[\'start_date\'])); ?> - 
                                                    <?php echo date(\'d/m/Y\', strtotime($campaign[\'end_date\'])); ?>
                                                </small>
                                                <a href="campaign-detail.php?id=<?php echo $campaign[\'campaign_id\']; ?>" 
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

<?php include \'includes/footer.php\'; ?>';

    // Ghi file volunteer.php
    file_put_contents('volunteer.php', $volunteerContent);
    echo "<p style='color: green; font-size: 20px;'>✅ ĐÃ TẠO TRANG VOLUNTEER.PHP</p>";
    
    // 5. KIỂM TRA LẠI CART
    echo "<h2>5. KIỂM TRA LẠI CART</h2>";
    $cartItems = Database::fetchAll("SELECT * FROM cart");
    echo "<pre>";
    print_r($cartItems);
    echo "</pre>";
    
    echo "<h1 style='color: green;'>✅ HOÀN THÀNH!</h1>";
    echo "<p style='font-size: 20px;'><strong>Bây giờ hãy:</strong></p>";
    echo "<ol style='font-size: 18px;'>";
    echo "<li>Vào <a href='cart.php' target='_blank'>http://localhost/Cap%201%20-%202/cart.php</a></li>";
    echo "<li>Nhấn Ctrl+F5 để hard refresh</li>";
    echo "<li>Kiểm tra value trong input field = 1 (không còn 100)</li>";
    echo "<li>Kiểm tra nút 'Tham gia Tình nguyện' trong header</li>";
    echo "<li>Vào <a href='volunteer.php' target='_blank'>http://localhost/Cap%201%20-%202/volunteer.php</a></li>";
    echo "</ol>";
    
} catch (Exception $e) {
    echo "<p style='color: red; font-size: 20px;'>Lỗi: " . $e->getMessage() . "</p>";
}
?>
