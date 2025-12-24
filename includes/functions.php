<?php
/**
 * Common Functions
 * CÃ¡c hÃ m tiá»‡n Ã­ch chung cho há»‡ thá»‘ng
 */

/**
 * Sanitize input data
 */
function sanitize($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Validate email
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Hash password
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Verify password
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Generate random token
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Check user role
 */
function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

/**
 * Check if user is admin
 */
function isAdmin() {
    return hasRole('admin');
}

/**
 * Redirect to login if not logged in
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }

    // Ensure the account is still active; force logout if locked/banned
    $user = Database::fetch("SELECT status FROM users WHERE user_id = ?", [$_SESSION['user_id']]);
    if (!$user || $user['status'] !== 'active') {
        // Clear remember token if present
        if (isset($_COOKIE['remember_token'])) {
            Database::execute("UPDATE users SET remember_token = NULL WHERE remember_token = ?", [$_COOKIE['remember_token']]);
            setcookie('remember_token', '', time() - 3600, '/');
        }

        session_destroy();
        header('Location: login.php?message=account_locked');
        exit();
    }
}

/**
 * Redirect to admin if not admin
 */
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: index.php');
        exit();
    }
}

/**
 * Format date
 */
function formatDate($date, $format = 'd/m/Y H:i') {
    return date($format, strtotime($date));
}

/**
 * Format currency
 */
function formatCurrency($amount, $currency = 'VND') {
    return number_format($amount, 0, ',', '.') . ' ' . $currency;
}

/**
 * Map payment method to display text, normalizing legacy values
 */
function formatPaymentMethodLabel($method, $variant = 'full') {
    $normalized = strtolower((string)$method);
    if ($normalized === 'cash') {
        $normalized = 'cod';
    }

    switch ($normalized) {
        case 'bank_transfer':
            return $variant === 'short' ? 'Chuyen khoan' : 'Chuyen khoan ngan hang';
        case 'credit_card':
            return $variant === 'short' ? 'The tin dung' : 'Thanh toan bang the';
        case 'cod':
        default:
            return $variant === 'short' ? 'COD' : 'Thanh toan khi nhan hang (COD)';
    }
}

/**
 * Upload file
 */
function uploadFile($file, $uploadDir = 'uploads/', $allowedTypes = ['jpg', 'jpeg', 'png', 'gif']) {
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['success' => false, 'message' => 'Invalid file upload'];
    }

    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_NO_FILE:
            return ['success' => false, 'message' => 'No file uploaded'];
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return ['success' => false, 'message' => 'File too large'];
        default:
            return ['success' => false, 'message' => 'Unknown upload error'];
    }

    $fileSize = $file['size'];
    if ($fileSize > 5 * 1024 * 1024) { // 5MB limit
        return ['success' => false, 'message' => 'File too large (max 5MB)'];
    }

    $fileInfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $fileInfo->file($file['tmp_name']);
    $allowedMimes = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif'
    ];

    $ext = array_search($mimeType, $allowedMimes, true);
    if ($ext === false) {
        return ['success' => false, 'message' => 'Invalid file type'];
    }

    $fileName = uniqid() . '.' . $ext;
    $uploadPath = $uploadDir . $fileName;

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
        return ['success' => false, 'message' => 'Failed to move uploaded file'];
    }

    return ['success' => true, 'filename' => $fileName, 'path' => $uploadPath];
}

/**
 * Send email via Gmail SMTP (PHPMailer)
 */
function sendEmail($to, $subject, $message, $from = null, $fromName = null) {
    $configPath = __DIR__ . '/../config/email.php';
    if (!file_exists($configPath)) {
        error_log('Missing email config file.');
        return false;
    }

    $emailConfig = require $configPath;

    // Lazy-load PHPMailer classes
    require_once __DIR__ . '/PHPMailer/Exception.php';
    require_once __DIR__ . '/PHPMailer/PHPMailer.php';
    require_once __DIR__ . '/PHPMailer/SMTP.php';

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = $emailConfig['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $emailConfig['username'];
        $mail->Password = $emailConfig['password'];
        $mail->SMTPSecure = $emailConfig['encryption'];
        $mail->Port = $emailConfig['port'];
        $mail->CharSet = 'UTF-8';

        $mailFrom = $from ?? $emailConfig['from_email'];
        $mailFromName = $fromName ?? $emailConfig['from_name'];

        $mail->setFrom($mailFrom, $mailFromName);
        $mail->addAddress($to);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $message;

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Email send failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Log activity
 */
function logActivity($user_id, $action, $details = '') {
    global $pdo;
    
    try {
        $sql = "INSERT INTO activity_logs (user_id, action, details, ip_address, created_at) 
                VALUES (?, ?, ?, ?, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $user_id,
            $action,
            $details,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
    } catch (Exception $e) {
        error_log("Failed to log activity: " . $e->getMessage());
    }
}

/**
 * Get user by ID
 */
function getUserById($user_id) {
    global $pdo;
    
    $sql = "SELECT * FROM users WHERE user_id = ? AND status = 'active'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    return $stmt->fetch();
}

/**
 * Get statistics
 */
function getStatistics() {
    $stats = [
        'users' => 0,
        'donations' => 0,
        'items' => 0,
        'campaigns' => 0
    ];

    try {
        // Đếm tất cả để không bị 0 khi đang chờ duyệt
        $stats['users'] = (int) (Database::fetch("SELECT COUNT(*) AS total FROM users")['total'] ?? 0);
        $stats['donations'] = (int) (Database::fetch("SELECT COUNT(*) AS total FROM donations")['total'] ?? 0);

        $itemsTotal = (int) (Database::fetch("SELECT COALESCE(SUM(quantity), 0) AS total FROM inventory")['total'] ?? 0);
        if ($itemsTotal === 0) {
            $itemsTotal = (int) (Database::fetch("SELECT COALESCE(SUM(quantity), 0) AS total FROM donations")['total'] ?? 0);
        }
        $stats['items'] = $itemsTotal;

        $stats['campaigns'] = (int) (Database::fetch("SELECT COUNT(*) AS total FROM campaigns")['total'] ?? 0);

    } catch (Exception $e) {
        error_log("Error in getStatistics: " . $e->getMessage());
    }

    return $stats;
}

/**
 * Build donation trend data for dashboard and Excel export
 */
function getDonationTrendData($months = 6) {
    $months = max(1, (int)$months);
    $start = new DateTime('first day of this month');
    if ($months > 1) {
        $start->modify('-' . ($months - 1) . ' months');
    }

    $startDate = $start->format('Y-m-01');

    $rows = Database::fetchAll("
        SELECT DATE_FORMAT(created_at, '%Y-%m-01') AS period, COUNT(*) AS total
        FROM donations
        WHERE created_at >= ?
        GROUP BY period
        ORDER BY period
    ", [$startDate]);

    $indexed = [];
    foreach ($rows as $row) {
        $indexed[$row['period']] = (int) $row['total'];
    }

    $data = [];
    for ($i = 0; $i < $months; $i++) {
        $current = clone $start;
        if ($i > 0) {
            $current->modify('+' . $i . ' months');
        }

        $periodKey = $current->format('Y-m-01');
        $data[] = [
            'label' => 'Tháng ' . (int) $current->format('n'),
            'period' => $current->format('Y-m'),
            'total' => $indexed[$periodKey] ?? 0
        ];
    }

    return $data;
}

/**
 * Build category distribution data for dashboard donut chart & export
 */
function getCategoryDistributionData() {
    $rows = Database::fetchAll("
        SELECT c.name AS label, COUNT(*) AS total
        FROM donations d
        LEFT JOIN categories c ON d.category_id = c.category_id
        GROUP BY c.category_id, c.name
        HAVING total > 0
        ORDER BY total DESC, label ASC
    ");

    if (empty($rows)) {
        return [
            ['label' => 'Chưa có dữ liệu', 'total' => 0]
        ];
    }

    return array_map(function ($row) {
        return [
            'label' => $row['label'] ?? 'Không xác định',
            'total' => (int) $row['total']
        ];
    }, $rows);
}
function getRecentDonations($limit = 6) {
    global $pdo;
    
    // Chỉ lấy donation đã duyệt và chưa bị loại bỏ khỏi kho (inventory != disposed)
    $sql = "SELECT d.*, u.name as donor_name, u.avatar
            FROM donations d
            JOIN users u ON d.user_id = u.user_id
            LEFT JOIN inventory i ON i.donation_id = d.donation_id
            WHERE d.status IN ('approved', 'completed', 'distributed')
              AND (i.item_id IS NULL OR i.status <> 'disposed')
            ORDER BY d.created_at DESC 
            LIMIT ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

/**
 * Pagination helper
 */
function paginate($currentPage, $totalPages, $baseUrl) {
    $pagination = '';
    
    if ($totalPages <= 1) {
        return $pagination;
    }
    
    $pagination .= '<nav aria-label="Page navigation"><ul class="pagination justify-content-center">';
    
    // Previous button
    if ($currentPage > 1) {
        $pagination .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '?page=' . ($currentPage - 1) . '">TrÆ°á»›c</a></li>';
    }
    
    // Page numbers
    $start = max(1, $currentPage - 2);
    $end = min($totalPages, $currentPage + 2);
    
    for ($i = $start; $i <= $end; $i++) {
        $active = ($i == $currentPage) ? 'active' : '';
        $pagination .= '<li class="page-item ' . $active . '"><a class="page-link" href="' . $baseUrl . '?page=' . $i . '">' . $i . '</a></li>';
    }
    
    // Next button
    if ($currentPage < $totalPages) {
        $pagination .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '?page=' . ($currentPage + 1) . '">Sau</a></li>';
    }
    
    $pagination .= '</ul></nav>';
    
    return $pagination;
}

/**
 * CSRF token functions
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = generateToken();
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Flash message functions
 */
function setFlashMessage($type, $message) {
    $_SESSION['flash'][$type] = $message;
}

function getFlashMessage($type) {
    if (isset($_SESSION['flash'][$type])) {
        $message = $_SESSION['flash'][$type];
        unset($_SESSION['flash'][$type]);
        return $message;
    }
    return null;
}

function displayFlashMessages() {
    $types = ['success', 'error', 'warning', 'info'];
    $html = '';
    
    foreach ($types as $type) {
        $message = getFlashMessage($type);
        if ($message) {
            $alertClass = 'alert-' . ($type === 'error' ? 'danger' : $type);
            $html .= '<div class="alert ' . $alertClass . ' alert-dismissible fade show" role="alert">';
            $html .= htmlspecialchars($message);
            $html .= '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
            $html .= '</div>';
        }
    }
    
    return $html;
}

/**
 * OTP utilities (2FA)
 */
function generateOtpCode($length = 6) {
    $min = (int) pow(10, $length - 1);
    $max = (int) pow(10, $length) - 1;
    return (string) random_int($min, $max);
}

function setOtp($purpose, $email, $code, $ttlSeconds = 120) {
    $_SESSION['otp'][$purpose][$email] = [
        'code' => $code,
        'expires' => time() + $ttlSeconds,
        'attempts' => 0,
        'max_attempts' => 5
    ];
}

function verifyOtp($purpose, $email, $inputCode) {
    if (!isset($_SESSION['otp'][$purpose][$email])) {
        return ['success' => false, 'message' => 'OTP không tồn tại hoặc đã hết hạn.'];
    }

    $record = $_SESSION['otp'][$purpose][$email];

    if ($record['expires'] < time()) {
        unset($_SESSION['otp'][$purpose][$email]);
        return ['success' => false, 'message' => 'OTP đã hết hạn. Vui lòng gửi lại.'];
    }

    if ($record['attempts'] >= $record['max_attempts']) {
        unset($_SESSION['otp'][$purpose][$email]);
        return ['success' => false, 'message' => 'Bạn đã nhập sai quá số lần cho phép.'];
    }

    $_SESSION['otp'][$purpose][$email]['attempts']++;

    if ($record['code'] !== $inputCode) {
        return ['success' => false, 'message' => 'OTP không đúng.'];
    }

    unset($_SESSION['otp'][$purpose][$email]);
    return ['success' => true, 'message' => 'Xác thực thành công.'];
}
?>
