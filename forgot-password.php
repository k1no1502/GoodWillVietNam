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
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');

    if (empty($email)) {
        $status = 'error';
        $message = 'Vui lòng nhập email.';
    } elseif (!validateEmail($email)) {
        $status = 'error';
        $message = 'Email không hợp lệ.';
    } else {
        try {
            // Tìm người dùng theo email
            $sql = "SELECT user_id, name, email FROM users WHERE email = ? AND status = 'active' LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            // Luôn hiển thị thông báo chung để tránh lộ thông tin tài khoản
            $status = 'success';
            $message = 'Nếu email tồn tại, chúng tôi đã gửi mã OTP và hướng dẫn đặt lại mật khẩu. Vui lòng kiểm tra hộp thư (hoặc mục spam).';

            if ($user) {
                $otpCode = generateOtpCode();
                setOtp('reset', $user['email'], $otpCode, 120);
                $_SESSION['pending_reset_email'] = $user['email'];
                $_SESSION['pending_reset_user_id'] = $user['user_id'];

                // Tạo link đặt lại mật khẩu (không dùng token, xác thực bằng OTP)
                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
                $resetLink = $scheme . $host . $basePath . '/reset-password.php?email=' . urlencode($user['email']);

                // Gửi email kèm OTP
                $subject = 'Mã OTP đặt lại mật khẩu - Goodwill Vietnam';
                $emailBody = "
                    <h3>Chào " . htmlspecialchars($user['name']) . ",</h3>
                    <p>Mã OTP đặt lại mật khẩu của bạn là: <strong>{$otpCode}</strong></p>
                    <p>Mã có hiệu lực trong 2 phút.</p>
                    <p>Truy cập liên kết sau để nhập OTP và đặt mật khẩu mới:</p>
                    <p><a href=\"{$resetLink}\">Đặt lại mật khẩu</a></p>
                    <p>Nếu bạn không yêu cầu, vui lòng bỏ qua email này.</p>
                    <p>Trân trọng,<br>Goodwill Vietnam</p>
                ";

                // Gửi nhưng không tiết lộ kết quả cụ thể ra ngoài
                sendEmail($user['email'], $subject, $emailBody);
            }
        } catch (Exception $e) {
            error_log('Forgot password error: ' . $e->getMessage());
            $status = 'error';
            $message = 'Có lỗi xảy ra. Vui lòng thử lại sau.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quên mật khẩu - Goodwill Vietnam</title>
    
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
                            <i class="bi bi-envelope-lock text-success display-4"></i>
                            <h2 class="fw-bold mt-3">Quên mật khẩu</h2>
                            <p class="text-muted">Nhập email để nhận hướng dẫn đặt lại mật khẩu</p>
                        </div>

                        <?php if (!empty($status)): ?>
                            <div class="alert alert-<?php echo $status === 'success' ? 'success' : 'danger'; ?>" role="alert">
                                <i class="bi <?php echo $status === 'success' ? 'bi-check-circle' : 'bi-exclamation-triangle'; ?> me-2"></i>
                                <?php echo htmlspecialchars($message); ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" class="needs-validation" novalidate>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-envelope"></i>
                                    </span>
                                    <input type="email"
                                           class="form-control"
                                           id="email"
                                           name="email"
                                           value="<?php echo htmlspecialchars($email); ?>"
                                           placeholder="Nhập email của bạn"
                                           required>
                                    <div class="invalid-feedback">
                                        Vui lòng nhập email hợp lệ.
                                    </div>
                                </div>
                            </div>

                            <div class="d-grid mb-3">
                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="bi bi-send me-2"></i>Gửi hướng dẫn
                                </button>
                            </div>
                        </form>

                        <div class="text-center">
                            <a href="login.php" class="text-decoration-none text-success fw-semibold">
                                <i class="bi bi-arrow-left-short"></i> Quay lại đăng nhập
                            </a>
                        </div>
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
