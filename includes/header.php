<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/notifications_helper.php';

$pageTitle = $pageTitle ?? "Goodwill Vietnam";

processScheduledAdminNotifications();
$notificationCount = isLoggedIn() ? getUnreadNotificationCount($_SESSION['user_id']) : 0;
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
    <link rel="icon" type="image/jpeg" href="assets/images/favicons/GWVN.jpg">
    
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
        .notification-indicator {
            position: absolute;
            top: 0;
            left: 18px;
            min-width: 18px;
            height: 18px;
            padding: 0 4px;
            background-color: #dc3545;
            color: #fff;
            border-radius: 999px;
            font-size: 11px;
            line-height: 18px;
            text-align: center;
            display: none;
        }
        .notification-indicator.show {
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top">
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
                        
                        <!-- Notifications -->
                        <li class="nav-item me-2">
                            <a href="notifications.php" class="nav-link position-relative">
                                <i class="bi bi-bell"></i>
                                <span class="notification-indicator <?php echo $notificationCount > 0 ? 'show' : ''; ?>" id="notification-count">
                                    <?php echo $notificationCount > 99 ? '99+' : $notificationCount; ?>
                                </span>
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
                                <i class="bi bi-person-circle me-1"></i><?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?>
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
                                <li><a class="dropdown-item" href="feedback.php">
                                    <i class="bi bi-chat-dots me-2"></i>Phản hồi
                                </a></li>
                                <?php if (isAdmin()): ?>
                                    <li><a class="dropdown-item" href="admin/dashboard.php">
                                        <i class="bi bi-speedometer2 me-2"></i>Admin Panel
                                    </a></li>
                                <?php endif; ?>
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

    <script>
        <?php if (isLoggedIn()): ?>
        document.addEventListener('DOMContentLoaded', function() {
            fetch('<?php echo isset($baseUrl) ? $baseUrl : ''; ?>api/get-cart-count.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const cartCount = document.getElementById('cart-count');
                        if (cartCount) {
                            cartCount.textContent = data.count;
                            if (data.count > 0) {
                                cartCount.classList.add('pulse');
                            }
                        }
                    }
                })
                .catch(error => console.error('Error loading cart count:', error));

            fetch('api/notifications.php?action=count')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const badge = document.getElementById('notification-count');
                        if (badge) {
                            const value = data.count > 99 ? '99+' : data.count;
                            badge.textContent = value;
                            if (data.count > 0) {
                                badge.classList.add('show');
                            } else {
                                badge.classList.remove('show');
                            }
                        }
                    }
                })
                .catch(error => console.error('Error loading notifications count:', error));
        });
<?php endif; ?>
    </script>
    <script src="<?php echo isset($baseUrl) ? $baseUrl : ''; ?>assets/js/data-refresh.js" data-base="<?php echo isset($baseUrl) ? $baseUrl : ''; ?>" data-interval="5000"></script>
