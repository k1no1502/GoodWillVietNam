<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

requireLogin();

$success = '';
$error = '';

$pageTitle = "Hồ sơ cá nhân";

// Get user data
$user = getUserById($_SESSION['user_id']);

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $address = sanitize($_POST['address'] ?? '');
    
    if (empty($name)) {
        $error = 'Vui lòng nhập họ và tên.';
    } else {
        try {
            Database::execute(
                "UPDATE users SET name = ?, phone = ?, address = ?, updated_at = NOW() WHERE user_id = ?",
                [$name, $phone, $address, $_SESSION['user_id']]
            );
            
            $_SESSION['name'] = $name;
            $success = 'Cập nhật thông tin thành công!';
            $user = getUserById($_SESSION['user_id']); // Refresh user data
        } catch (Exception $e) {
            error_log("Profile update error: " . $e->getMessage());
            $error = 'Có lỗi xảy ra khi cập nhật thông tin.';
        }
    }
}

// Get user statistics
$stats = [
    'total_donations' => Database::fetch(
        "SELECT COUNT(*) as count FROM donations WHERE user_id = ?",
        [$_SESSION['user_id']]
    )['count'],
    'approved_donations' => Database::fetch(
        "SELECT COUNT(*) as count FROM donations WHERE user_id = ? AND status = 'approved'",
        [$_SESSION['user_id']]
    )['count'],
    'total_orders' => Database::fetch(
        "SELECT COUNT(*) as count FROM orders WHERE user_id = ?",
        [$_SESSION['user_id']]
    )['count'],
    'pending_donations' => Database::fetch(
        "SELECT COUNT(*) as count FROM donations WHERE user_id = ? AND status = 'pending'",
        [$_SESSION['user_id']]
    )['count']
];

include 'includes/header.php';
?>

<!-- Main Content -->
<div class="container py-5 mt-5">
    <div class="row">
        <div class="col-lg-4">
            <!-- Profile Card -->
            <div class="card shadow-sm">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <i class="bi bi-person-circle display-1 text-success"></i>
                    </div>
                    <h4 class="card-title"><?php echo htmlspecialchars($user['name']); ?></h4>
                    <p class="text-muted"><?php echo htmlspecialchars($user['email']); ?></p>
                    <p class="text-muted">
                        <i class="bi bi-calendar me-1"></i>
                        Tham gia: <?php echo date('d/m/Y', strtotime($user['created_at'])); ?>
                    </p>
                </div>
            </div>

            <!-- Statistics -->
            <div class="card shadow-sm mt-4">
                <div class="card-header bg-success text-white">
                    <h6 class="mb-0">
                        <i class="bi bi-graph-up me-2"></i>Thống kê
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6 mb-3">
                            <h5 class="text-success"><?php echo $stats['total_donations']; ?></h5>
                            <small class="text-muted">Quyên góp</small>
                        </div>
                        <div class="col-6 mb-3">
                            <h5 class="text-success"><?php echo $stats['approved_donations']; ?></h5>
                            <small class="text-muted">Đã duyệt</small>
                        </div>
                        <div class="col-6">
                            <h5 class="text-success"><?php echo $stats['total_orders']; ?></h5>
                            <small class="text-muted">Đơn hàng</small>
                        </div>
                        <div class="col-6">
                            <h5 class="text-warning"><?php echo $stats['pending_donations']; ?></h5>
                            <small class="text-muted">Chờ duyệt</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <!-- Profile Form -->
            <div class="card shadow-sm">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">
                        <i class="bi bi-person-gear me-2"></i>Thông tin cá nhân
                    </h5>
                </div>
                <div class="card-body">
                    <?php if ($success): ?>
                        <div class="alert alert-success" role="alert">
                            <i class="bi bi-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="needs-validation" novalidate>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="name" class="form-label">Họ và tên *</label>
                                <input type="text" 
                                       class="form-control" 
                                       id="name" 
                                       name="name" 
                                       value="<?php echo htmlspecialchars($user['name']); ?>"
                                       required>
                                <div class="invalid-feedback">
                                    Vui lòng nhập họ và tên.
                                </div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" 
                                       class="form-control" 
                                       id="email" 
                                       value="<?php echo htmlspecialchars($user['email']); ?>"
                                       disabled>
                                <div class="form-text">Email không thể thay đổi</div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="phone" class="form-label">Số điện thoại</label>
                                <input type="tel" 
                                       class="form-control" 
                                       id="phone" 
                                       name="phone" 
                                       value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                                       placeholder="Nhập số điện thoại">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="role" class="form-label">Vai trò</label>
                                <input type="text" 
                                       class="form-control" 
                                       id="role" 
                                       value="<?php echo ucfirst($user['role']); ?>"
                                       disabled>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="address" class="form-label">Địa chỉ</label>
                            <textarea class="form-control" 
                                      id="address" 
                                      name="address" 
                                      rows="3" 
                                      placeholder="Nhập địa chỉ chi tiết"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-check-circle me-2"></i>Cập nhật thông tin
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card shadow-sm mt-4">
                <div class="card-header bg-light">
                    <h6 class="mb-0">
                        <i class="bi bi-lightning me-2"></i>Thao tác nhanh
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <a href="my-donations.php" class="btn btn-outline-success w-100">
                                <i class="bi bi-heart me-2"></i>Quyên góp của tôi
                            </a>
                        </div>
                        <div class="col-md-6 mb-3">
                            <a href="my-orders.php" class="btn btn-outline-success w-100">
                                <i class="bi bi-bag me-2"></i>Đơn hàng của tôi
                            </a>
                        </div>
                        <div class="col-md-6 mb-3">
                            <a href="donate.php" class="btn btn-outline-success w-100">
                                <i class="bi bi-plus-circle me-2"></i>Quyên góp mới
                            </a>
                        </div>
                        <div class="col-md-6 mb-3">
                            <a href="change-password.php" class="btn btn-outline-warning w-100">
                                <i class="bi bi-key me-2"></i>Đổi mật khẩu
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Form validation
(function() {
    'use strict';
    window.addEventListener('load', function() {
        var forms = document.getElementsByClassName('needs-validation');
        var validation = Array.prototype.filter.call(forms, function(form) {
            form.addEventListener('submit', function(event) {
                if (form.checkValidity() === false) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    }, false);
})();
</script>

<?php include 'includes/footer.php'; ?>
