<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$error = '';
$infoMessage = '';

// Show message when account was locked/disabled
if (isset($_GET['message']) && $_GET['message'] === 'account_locked') {
    $infoMessage = 'Tài khoản của bạn đã bị khóa hoặc tạm ngưng. Vui lòng liên hệ quản trị viên để được hỗ trợ.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    // Validate input
    if (empty($email) || empty($password)) {
        $error = 'Vui lòng nhập đầy đủ thông tin.';
    } elseif (!validateEmail($email)) {
        $error = 'Email không hợp lệ.';
    } else {
        try {
            // Get user from database
            $sql = "SELECT u.*, r.role_name FROM users u 
                    LEFT JOIN roles r ON u.role_id = r.role_id 
                    WHERE u.email = ? AND u.status = 'active'";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && verifyPassword($password, $user['password'])) {
                // Login successful
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role_name'];
                $_SESSION['avatar'] = $user['avatar'];
                
                // Update last login
                $sql = "UPDATE users SET last_login = NOW() WHERE user_id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$user['user_id']]);
                
                // Log activity
                logActivity($user['user_id'], 'login', 'User logged in');
                
                // Set remember me cookie
                if ($remember) {
                    $token = generateToken();
                    setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/'); // 30 days
                    
                    // Store token in database
                    $sql = "UPDATE users SET remember_token = ? WHERE user_id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$token, $user['user_id']]);
                }
                
                // Redirect based on role
                $redirect = isset($_GET['redirect']) ? $_GET['redirect'] : 'index.php';
                if ($user['role_name'] === 'admin') {
                    $redirect = 'admin/dashboard.php';
                }
                
                header('Location: ' . $redirect);
                exit();
            } else {
                $error = 'Email hoặc mật khẩu không đúng.';
            }
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            $error = 'Có lỗi xảy ra. Vui lòng thử lại sau.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập - Goodwill Vietnam</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-light">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-success">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="bi bi-heart-fill me-2"></i>Goodwill Vietnam
            </a>
        </div>
    </nav>

    <!-- Login Form -->
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card shadow-lg border-0">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <i class="bi bi-person-circle text-success display-4"></i>
                            <h2 class="fw-bold mt-3">Đăng nhập</h2>
                            <p class="text-muted">Chào mừng bạn quay trở lại</p>
                        </div>

                        <?php if ($error): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($infoMessage): ?>
                            <div class="alert alert-warning" role="alert">
                                <i class="bi bi-info-circle me-2"></i><?php echo htmlspecialchars($infoMessage); ?>
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
                                           value="<?php echo htmlspecialchars($email ?? ''); ?>"
                                           placeholder="Nhập email của bạn"
                                           required>
                                    <div class="invalid-feedback">
                                        Vui lòng nhập email hợp lệ.
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label">Mật khẩu</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-lock"></i>
                                    </span>
                                    <input type="password" 
                                           class="form-control" 
                                           id="password" 
                                           name="password" 
                                           placeholder="Nhập mật khẩu"
                                           required>
                                    <button class="btn btn-outline-secondary" 
                                            type="button" 
                                            id="togglePassword">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <div class="invalid-feedback">
                                        Vui lòng nhập mật khẩu.
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3 form-check">
                                <input type="checkbox" 
                                       class="form-check-input" 
                                       id="remember" 
                                       name="remember">
                                <label class="form-check-label" for="remember">
                                    Ghi nhớ đăng nhập
                                </label>
                            </div>

                            <div class="d-grid mb-3">
                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="bi bi-box-arrow-in-right me-2"></i>Đăng nhập
                                </button>
                            </div>
                        </form>

                        <div class="text-center">
                            <p class="text-muted mb-2">
                                <a href="forgot-password.php" class="text-decoration-none">
                                    Quên mật khẩu?
                                </a>
                            </p>
                            <p class="text-muted">
                                Chưa có tài khoản? 
                                <a href="register.php" class="text-success fw-bold text-decoration-none">
                                    Đăng ký ngay
                                </a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="assets/js/main.js"></script>
    
    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const password = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (password.type === 'password') {
                password.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                password.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        });

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
</body>
</html>
