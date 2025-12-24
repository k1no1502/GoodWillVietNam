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
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $address = sanitize($_POST['address'] ?? '');
    $agree_terms = isset($_POST['agree_terms']);
    
    // Validate input
    if (empty($name) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = 'Vui lòng nhập đầy đủ thông tin bắt buộc.';
    } elseif (!validateEmail($email)) {
        $error = 'Email không hợp lệ.';
    } elseif (strlen($password) < 6) {
        $error = 'Mật khẩu phải có ít nhất 6 ký tự.';
    } elseif ($password !== $confirm_password) {
        $error = 'Mật khẩu xác nhận không khớp.';
    } elseif (!$agree_terms) {
        $error = 'Vui lòng đồng ý với điều khoản sử dụng.';
    } else {
        try {
            // Check if email already exists
            $sql = "SELECT user_id FROM users WHERE email = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$email]);
            
            if ($stmt->fetch()) {
                $error = 'Email này đã được sử dụng. Vui lòng chọn email khác.';
            } else {
                // Generate verification token
                $verification_token = generateToken();
                
                // Insert new user
                $sql = "INSERT INTO users (name, email, password, phone, address, role_id, status, email_verified, verification_token, created_at) 
                        VALUES (?, ?, ?, ?, ?, 2, 'active', FALSE, ?, NOW())";
                $stmt = $pdo->prepare($sql);
                $hashed_password = hashPassword($password);
                
                if ($stmt->execute([$name, $email, $hashed_password, $phone, $address, $verification_token])) {
                    $user_id = $pdo->lastInsertId();
                    
                    // Log activity
                    logActivity($user_id, 'register', 'New user registered');
                    
                    // Send verification email (in production)
                    // sendVerificationEmail($email, $verification_token);
                    
                    $success = 'Đăng ký thành công! Bạn có thể đăng nhập ngay bây giờ.';
                    
                    // Auto login after registration
                    $_SESSION['user_id'] = $user_id;
                    $_SESSION['name'] = $name;
                    $_SESSION['email'] = $email;
                    $_SESSION['role'] = 'user';
                    $_SESSION['avatar'] = '';
                    
                    // Redirect to home page
                    header('Location: index.php');
                    exit();
                } else {
                    $error = 'Có lỗi xảy ra khi tạo tài khoản. Vui lòng thử lại.';
                }
            }
        } catch (Exception $e) {
            error_log("Registration error: " . $e->getMessage());
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
    <title>Đăng ký - Goodwill Vietnam</title>
    
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

    <!-- Registration Form -->
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card shadow-lg border-0">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <i class="bi bi-person-plus text-success display-4"></i>
                            <h2 class="fw-bold mt-3">Đăng ký tài khoản</h2>
                            <p class="text-muted">Tham gia cộng đồng thiện nguyện</p>
                        </div>

                        <?php if ($error): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-success" role="alert">
                                <i class="bi bi-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" class="needs-validation" novalidate>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="name" class="form-label">Họ và tên *</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="bi bi-person"></i>
                                        </span>
                                        <input type="text" 
                                               class="form-control" 
                                               id="name" 
                                               name="name" 
                                               value="<?php echo htmlspecialchars($name ?? ''); ?>"
                                               placeholder="Nhập họ và tên"
                                               required>
                                        <div class="invalid-feedback">
                                            Vui lòng nhập họ và tên.
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email *</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="bi bi-envelope"></i>
                                        </span>
                                        <input type="email" 
                                               class="form-control" 
                                               id="email" 
                                               name="email" 
                                               value="<?php echo htmlspecialchars($email ?? ''); ?>"
                                               placeholder="Nhập email"
                                               required>
                                        <div class="invalid-feedback">
                                            Vui lòng nhập email hợp lệ.
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="phone" class="form-label">Số điện thoại</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="bi bi-phone"></i>
                                        </span>
                                        <input type="tel" 
                                               class="form-control" 
                                               id="phone" 
                                               name="phone" 
                                               value="<?php echo htmlspecialchars($phone ?? ''); ?>"
                                               placeholder="Nhập số điện thoại">
                                    </div>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="address" class="form-label">Địa chỉ</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="bi bi-geo-alt"></i>
                                        </span>
                                        <input type="text" 
                                               class="form-control" 
                                               id="address" 
                                               name="address" 
                                               value="<?php echo htmlspecialchars($address ?? ''); ?>"
                                               placeholder="Nhập địa chỉ">
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="password" class="form-label">Mật khẩu *</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="bi bi-lock"></i>
                                        </span>
                                        <input type="password" 
                                               class="form-control" 
                                               id="password" 
                                               name="password" 
                                               placeholder="Nhập mật khẩu"
                                               minlength="6"
                                               required>
                                        <button class="btn btn-outline-secondary" 
                                                type="button" 
                                                id="togglePassword">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <div class="invalid-feedback">
                                            Mật khẩu phải có ít nhất 6 ký tự.
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="confirm_password" class="form-label">Xác nhận mật khẩu *</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="bi bi-lock-fill"></i>
                                        </span>
                                        <input type="password" 
                                               class="form-control" 
                                               id="confirm_password" 
                                               name="confirm_password" 
                                               placeholder="Nhập lại mật khẩu"
                                               required>
                                        <div class="invalid-feedback">
                                            Mật khẩu xác nhận không khớp.
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3 form-check">
                                <input type="checkbox" 
                                       class="form-check-input" 
                                       id="agree_terms" 
                                       name="agree_terms"
                                       required>
                                <label class="form-check-label" for="agree_terms">
                                    Tôi đồng ý với 
                                    <a href="terms.php" target="_blank" class="text-success">điều khoản sử dụng</a> 
                                    và 
                                    <a href="privacy.php" target="_blank" class="text-success">chính sách bảo mật</a>
                                </label>
                                <div class="invalid-feedback">
                                    Vui lòng đồng ý với điều khoản sử dụng.
                                </div>
                            </div>

                            <div class="d-grid mb-3">
                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="bi bi-person-plus me-2"></i>Đăng ký
                                </button>
                            </div>
                        </form>

                        <div class="text-center">
                            <p class="text-muted">
                                Đã có tài khoản? 
                                <a href="login.php" class="text-success fw-bold text-decoration-none">
                                    Đăng nhập ngay
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

        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (password !== confirmPassword) {
                this.setCustomValidity('Mật khẩu xác nhận không khớp');
            } else {
                this.setCustomValidity('');
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
