<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireAdmin();

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    try {
        if ($action === 'update_settings') {
            // Check if system_settings table exists
            $tableExists = Database::fetch("
                SELECT COUNT(*) as count 
                FROM information_schema.tables 
                WHERE table_schema = DATABASE() 
                AND table_name = 'system_settings'
            ");
            
            if ($tableExists['count'] > 0) {
                // Update existing settings
                foreach ($_POST as $key => $value) {
                    if ($key !== 'action' && strpos($key, 'setting_') === 0) {
                        $setting_key = str_replace('setting_', '', $key);
                        $setting_value = sanitize($value);
                        
                        Database::execute("
                            INSERT INTO system_settings (setting_key, setting_value, updated_at) 
                            VALUES (?, ?, NOW())
                            ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()
                        ", [$setting_key, $setting_value, $setting_value]);
                    }
                }
            }
            
            setFlashMessage('success', 'Đã cập nhật cài đặt.');
            logActivity($_SESSION['user_id'], 'update_settings', 'Updated system settings');
            
        } elseif ($action === 'update_profile') {
            $name = sanitize($_POST['admin_name'] ?? '');
            $email = sanitize($_POST['admin_email'] ?? '');
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            
            if (empty($name) || empty($email)) {
                throw new Exception('Tên và email không được để trống.');
            }
            
            // Update name and email
            Database::execute(
                "UPDATE users SET name = ?, email = ?, updated_at = NOW() WHERE user_id = ?",
                [$name, $email, $_SESSION['user_id']]
            );
            
            // Update password if provided
            if (!empty($new_password)) {
                if (empty($current_password)) {
                    throw new Exception('Vui lòng nhập mật khẩu hiện tại.');
                }
                
                $user = Database::fetch("SELECT password FROM users WHERE user_id = ?", [$_SESSION['user_id']]);
                if (!verifyPassword($current_password, $user['password'])) {
                    throw new Exception('Mật khẩu hiện tại không đúng.');
                }
                
                Database::execute(
                    "UPDATE users SET password = ?, updated_at = NOW() WHERE user_id = ?",
                    [hashPassword($new_password), $_SESSION['user_id']]
                );
            }
            
            setFlashMessage('success', 'Đã cập nhật thông tin cá nhân.');
            logActivity($_SESSION['user_id'], 'update_profile', 'Updated admin profile');
        }
    } catch (Exception $e) {
        setFlashMessage('error', 'Có lỗi xảy ra: ' . $e->getMessage());
    }
    
    header('Location: settings.php');
    exit();
}

// Get current admin info
$admin = Database::fetch("SELECT * FROM users WHERE user_id = ?", [$_SESSION['user_id']]);

// Get system settings
$settings = [];
$tableExists = Database::fetch("
    SELECT COUNT(*) as count 
    FROM information_schema.tables 
    WHERE table_schema = DATABASE() 
    AND table_name = 'system_settings'
");

if ($tableExists['count'] > 0) {
    $settingsRows = Database::fetchAll("SELECT setting_key, setting_value FROM system_settings");
    foreach ($settingsRows as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cài đặt hệ thống - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 admin-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="bi bi-gear me-2"></i>Cài đặt hệ thống</h1>
                </div>

                <?php echo displayFlashMessages(); ?>

                <!-- Settings Tabs -->
                <ul class="nav nav-tabs mb-4" id="settingsTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button">
                            <i class="bi bi-gear me-1"></i>Cài đặt chung
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile" type="button">
                            <i class="bi bi-person me-1"></i>Thông tin cá nhân
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="settingsTabContent">
                    <!-- General Settings -->
                    <div class="tab-pane fade show active" id="general" role="tabpanel">
                        <div class="card shadow-sm">
                            <div class="card-header">
                                <h6 class="mb-0">Cài đặt hệ thống</h6>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="action" value="update_settings">
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Tên website</label>
                                        <input type="text" 
                                               class="form-control" 
                                               name="setting_site_name" 
                                               value="<?php echo htmlspecialchars($settings['site_name'] ?? 'Goodwill Vietnam'); ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Email liên hệ</label>
                                        <input type="email" 
                                               class="form-control" 
                                               name="setting_contact_email" 
                                               value="<?php echo htmlspecialchars($settings['contact_email'] ?? ''); ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Số điện thoại liên hệ</label>
                                        <input type="text" 
                                               class="form-control" 
                                               name="setting_contact_phone" 
                                               value="<?php echo htmlspecialchars($settings['contact_phone'] ?? ''); ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Địa chỉ</label>
                                        <textarea class="form-control" 
                                                  name="setting_address" 
                                                  rows="3"><?php echo htmlspecialchars($settings['address'] ?? ''); ?></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" 
                                                   type="checkbox" 
                                                   name="setting_enable_campaigns" 
                                                   value="true"
                                                   <?php echo ($settings['enable_campaigns'] ?? 'true') === 'true' ? 'checked' : ''; ?>>
                                            <label class="form-check-label">Bật chức năng chiến dịch</label>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" 
                                                   type="checkbox" 
                                                   name="setting_campaign_approval_required" 
                                                   value="true"
                                                   <?php echo ($settings['campaign_approval_required'] ?? 'true') === 'true' ? 'checked' : ''; ?>>
                                            <label class="form-check-label">Yêu cầu duyệt chiến dịch</label>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" 
                                                   type="checkbox" 
                                                   name="setting_enable_donations" 
                                                   value="true"
                                                   <?php echo ($settings['enable_donations'] ?? 'true') === 'true' ? 'checked' : ''; ?>>
                                            <label class="form-check-label">Bật chức năng quyên góp</label>
                                        </div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-save me-1"></i>Lưu cài đặt
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Profile Settings -->
                    <div class="tab-pane fade" id="profile" role="tabpanel">
                        <div class="card shadow-sm">
                            <div class="card-header">
                                <h6 class="mb-0">Thông tin quản trị viên</h6>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="action" value="update_profile">
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Tên *</label>
                                        <input type="text" 
                                               class="form-control" 
                                               name="admin_name" 
                                               value="<?php echo htmlspecialchars($admin['name']); ?>" 
                                               required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Email *</label>
                                        <input type="email" 
                                               class="form-control" 
                                               name="admin_email" 
                                               value="<?php echo htmlspecialchars($admin['email']); ?>" 
                                               required>
                                    </div>
                                    
                                    <hr>
                                    <h6>Đổi mật khẩu</h6>
                                    <p class="text-muted small">Để trống nếu không muốn đổi mật khẩu</p>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Mật khẩu hiện tại</label>
                                        <input type="password" 
                                               class="form-control" 
                                               name="current_password" 
                                               placeholder="Nhập mật khẩu hiện tại">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Mật khẩu mới</label>
                                        <input type="password" 
                                               class="form-control" 
                                               name="new_password" 
                                               placeholder="Nhập mật khẩu mới">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Xác nhận mật khẩu mới</label>
                                        <input type="password" 
                                               class="form-control" 
                                               name="confirm_password" 
                                               placeholder="Nhập lại mật khẩu mới">
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-save me-1"></i>Cập nhật thông tin
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password confirmation validation
        document.querySelector('form[action="update_profile"]')?.addEventListener('submit', function(e) {
            const newPassword = document.querySelector('input[name="new_password"]').value;
            const confirmPassword = document.querySelector('input[name="confirm_password"]').value;
            
            if (newPassword && newPassword !== confirmPassword) {
                e.preventDefault();
                alert('Mật khẩu xác nhận không khớp!');
            }
        });
    </script>
</body>
</html>

