<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Nếu đã đăng nhập thì quay lại trang chủ
if (isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$status = '';
$message = '';
$user = null;
$tokenValid = false;
$emailParam = $_GET['email'] ?? ($_SESSION['pending_reset_email'] ?? '');

if (empty($emailParam)) {
    $status = 'error';
    $message = 'Liên kết đặt lại mật khẩu không hợp lệ.';
} else {
    try {
        $sql = "SELECT user_id, name, email FROM users WHERE email = ? AND status = 'active' LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$emailParam]);
        $user = $stmt->fetch();

        if ($user && isset($_SESSION['otp']['reset'][$user['email']])) {
            $record = $_SESSION['otp']['reset'][$user['email']];
            if ($record['expires'] >= time()) {
                $tokenValid = true;
            } else {
                $status = 'error';
                $message = 'OTP đã hết hạn. Vui lòng gửi yêu cầu mới.';
            }
        } else {
            $status = 'error';
            $message = 'OTP không tồn tại hoặc đã hết hạn. Vui lòng gửi yêu cầu mới.';
        }
    } catch (Exception $e) {
        error_log('Reset password load error: ' . $e->getMessage());
        $status = 'error';
        $message = 'Không thể tải thông tin. Vui lòng thử lại.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tokenValid && $user) {
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $otpInput = $_POST['otp'] ?? '';

    if (empty($otpInput)) {
        $status = 'error';
        $message = 'Vui lòng nhập OTP.';
    } elseif (empty($password) || empty($confirm)) {
        $status = 'error';
        $message = 'Vui lòng nhập đầy đủ mật khẩu.';
    } elseif (strlen($password) < 6) {
        $status = 'error';
        $message = 'Mật khẩu cần ít nhất 6 ký tự.';
    } elseif ($password !== $confirm) {
        $status = 'error';
        $message = 'Mật khẩu nhập lại không khớp.';
    } else {
        $otpCheck = verifyOtp('reset', $user['email'], $otpInput);
        if (!$otpCheck['success']) {
            $status = 'error';
            $message = $otpCheck['message'];
        } else {
            try {
                $hashed = hashPassword($password);
                $sql = "UPDATE users 
                        SET password = ?, reset_token = NULL, reset_expires = NULL, updated_at = NOW() 
                        WHERE user_id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$hashed, $user['user_id']]);

                logActivity($user['user_id'], 'reset_password', 'User reset password');

                $status = 'success';
                $message = 'Đặt lại mật khẩu thành công. Bạn có thể đăng nhập bằng mật khẩu mới.';
                unset($_SESSION['pending_reset_email'], $_SESSION['pending_reset_user_id']);
            } catch (Exception $e) {
                error_log('Reset password save error: ' . $e->getMessage());
                $status = 'error';
                $message = 'Không thể đặt lại mật khẩu. Vui lòng thử lại sau.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đặt lại mật khẩu - Goodwill Vietnam</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-success">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="bi bi-heart-fill me-2"></i>Goodwill Vietnam
            </a>
        </div>
    </nav>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card shadow-lg border-0">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <i class="bi bi-shield-lock text-success display-4"></i>
                            <h2 class="fw-bold mt-3">Đặt lại mật khẩu</h2>
                            <p class="text-muted">Nhập OTP và tạo mật khẩu mới</p>
                        </div>

                        <?php if (!empty($status)): ?>
                            <div class="alert alert-<?php echo $status === 'success' ? 'success' : 'danger'; ?>" role="alert">
                                <i class="bi <?php echo $status === 'success' ? 'bi-check-circle' : 'bi-exclamation-triangle'; ?> me-2"></i>
                                <?php echo htmlspecialchars($message); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($tokenValid && $status !== 'success' && $user): ?>
                            <form method="POST" class="needs-validation" novalidate>
                                <div class="mb-3">
                                    <label for="otp" class="form-label">OTP</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="bi bi-shield-lock"></i>
                                        </span>
                                        <input type="text"
                                               class="form-control"
                                               id="otp"
                                               name="otp"
                                               placeholder="Nhập mã OTP"
                                               minlength="4"
                                               maxlength="6"
                                               required>
                                        <div class="invalid-feedback">
                                            Vui lòng nhập OTP hợp lệ.
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="password" class="form-label">Mật khẩu mới</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="bi bi-lock"></i>
                                        </span>
                                        <input type="password"
                                               class="form-control"
                                               id="password"
                                               name="password"
                                               placeholder="Nhập mật khẩu mới"
                                               minlength="6"
                                               required>
                                        <div class="invalid-feedback">
                                            Mật khẩu cần ít nhất 6 ký tự.
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-4">
                                    <label for="confirm_password" class="form-label">Nhập lại mật khẩu</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="bi bi-lock-fill"></i>
                                        </span>
                                        <input type="password"
                                               class="form-control"
                                               id="confirm_password"
                                               name="confirm_password"
                                               placeholder="Nhập lại mật khẩu"
                                               minlength="6"
                                               required>
                                        <div class="invalid-feedback">
                                            Vui lòng nhập lại mật khẩu khớp nhau.
                                        </div>
                                    </div>
                                </div>

                                <div class="d-grid mb-3">
                                    <button type="submit" class="btn btn-success btn-lg">
                                        <i class="bi bi-shield-check me-2"></i>Lưu mật khẩu mới
                                    </button>
                                </div>
                            </form>
                        <?php elseif ($status === 'success'): ?>
                            <div class="text-center">
                                <a href="login.php" class="btn btn-success btn-lg">
                                    <i class="bi bi-box-arrow-in-right me-2"></i>Đăng nhập
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="text-center">
                                <a href="forgot-password.php" class="text-decoration-none text-success fw-semibold">
                                    <i class="bi bi-arrow-repeat me-1"></i> Gửi yêu cầu mới
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Bootstrap validation
        (function() {
            'use strict';
            const forms = document.querySelectorAll('.needs-validation');
            Array.prototype.slice.call(forms).forEach(function(form) {
                form.addEventListener('submit', function(event) {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                }, false);
            });
        })();
    </script>
</body>
</html>
