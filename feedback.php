<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

$pageTitle = "Gửi phản hồi";
$success = '';
$error = '';

$defaultName = '';
$defaultEmail = '';
$userFeedback = [];

if (isLoggedIn()) {
    $user = Database::fetch("SELECT name, email FROM users WHERE user_id = ?", [$_SESSION['user_id']]);
    if ($user) {
        $defaultName = $user['name'] ?? '';
        $defaultEmail = $user['email'] ?? '';
    }
    $userFeedback = Database::fetchAll(
        "SELECT fb_id, subject, content, admin_reply, status, created_at, replied_at 
         FROM feedback 
         WHERE user_id = ? 
         ORDER BY created_at DESC 
         LIMIT 50",
        [$_SESSION['user_id']]
    );
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name'] ?? $defaultName);
    $email = sanitize($_POST['email'] ?? $defaultEmail);
    $subject = sanitize($_POST['subject'] ?? '');
    $content = sanitize($_POST['content'] ?? '');
    $rating = (int)($_POST['rating'] ?? 0);

    if ($name === '' || $email === '' || $subject === '' || $content === '') {
        $error = 'Vui lòng điền đầy đủ các trường bắt buộc.';
    } elseif (!validateEmail($email)) {
        $error = 'Email không hợp lệ.';
    } elseif ($rating !== 0 && ($rating < 1 || $rating > 5)) {
        $error = 'Đánh giá phải từ 1 đến 5 sao.';
    }

    if (empty($error)) {
        try {
            Database::execute(
                "INSERT INTO feedback (user_id, name, email, subject, content, rating, status, created_at) 
                 VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())",
                [
                    isLoggedIn() ? $_SESSION['user_id'] : null,
                    $name,
                    $email,
                    $subject,
                    $content,
                    $rating > 0 ? $rating : null
                ]
            );

            $success = 'Cảm ơn bạn đã gửi phản hồi! Chúng tôi sẽ liên hệ lại trong thời gian sớm nhất.';
            $_POST = [];
        } catch (Exception $e) {
            $error = 'Không thể gửi phản hồi lúc này. Vui lòng thử lại sau.';
        }
    }
}

include 'includes/header.php';
?>

<div class="container py-5">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-success text-white">
                    <h4 class="mb-0"><i class="bi bi-chat-dots me-2"></i>Gửi phản hồi</h4>
                </div>
                <div class="card-body p-4">
                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                    <?php endif; ?>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>

                    <form method="POST" class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Họ và tên *</label>
                            <input type="text"
                                   class="form-control"
                                   name="name"
                                   value="<?php echo htmlspecialchars($_POST['name'] ?? $defaultName); ?>"
                                   required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email *</label>
                            <input type="email"
                                   class="form-control"
                                   name="email"
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? $defaultEmail); ?>"
                                   required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Tiêu đề *</label>
                            <input type="text"
                                   class="form-control"
                                   name="subject"
                                   value="<?php echo htmlspecialchars($_POST['subject'] ?? ''); ?>"
                                   required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Nội dung *</label>
                            <textarea class="form-control"
                                      name="content"
                                      rows="5"
                                      required><?php echo htmlspecialchars($_POST['content'] ?? ''); ?></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Đánh giá (tùy chọn)</label>
                            <select class="form-select" name="rating">
                                <option value="0">Chọn số sao</option>
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo ((int)($_POST['rating'] ?? 0) === $i) ? 'selected' : ''; ?>>
                                        <?php echo $i; ?> sao
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-success btn-lg">
                                <i class="bi bi-send me-2"></i>Gửi phản hồi
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h5 class="mb-3">Chúng tôi luôn lắng nghe</h5>
                    <p class="text-muted mb-0">
                        Phản hồi của bạn giúp Goodwill Vietnam cải thiện trải nghiệm và phục vụ cộng đồng tốt hơn.
                        Nếu cần hỗ trợ khẩn cấp, bạn có thể liên hệ qua email <strong>support@goodwill.vn</strong>.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (isLoggedIn()): ?>
<div class="container pb-5">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card shadow-sm border-0 mt-4">
                <div class="card-header bg-white border-0 d-flex align-items-center">
                    <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Lịch sử phản hồi của bạn</h5>
                </div>
                <div class="card-body p-4">
                    <?php if (empty($userFeedback)): ?>
                        <div class="text-muted">Bạn chưa gửi phản hồi nào.</div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($userFeedback as $fb): ?>
                                <?php
                                $statusMeta = [
                                    'pending' => ['class' => 'warning', 'text' => 'Chờ xử lý'],
                                    'read' => ['class' => 'info', 'text' => 'Đã đọc'],
                                    'replied' => ['class' => 'success', 'text' => 'Đã phản hồi'],
                                ];
                                $st = $statusMeta[$fb['status']] ?? ['class' => 'secondary', 'text' => ucfirst((string)$fb['status'])];
                                ?>
                                <div class="list-group-item px-0">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <div class="fw-semibold"><?php echo htmlspecialchars($fb['subject']); ?></div>
                                            <div class="text-muted small">Gửi lúc <?php echo formatDate($fb['created_at']); ?></div>
                                        </div>
                                        <span class="badge bg-<?php echo $st['class']; ?>"><?php echo $st['text']; ?></span>
                                    </div>
                                    <div class="mt-2">
                                        <div class="text-muted">Nội dung:</div>
                                        <div><?php echo nl2br(htmlspecialchars($fb['content'])); ?></div>
                                    </div>
                                    <?php if (!empty($fb['admin_reply'])): ?>
                                        <div class="mt-3 p-3 bg-light border rounded">
                                            <div class="d-flex justify-content-between">
                                                <div class="fw-semibold"><i class="bi bi-reply-fill me-1"></i>Phản hồi từ Admin</div>
                                                <?php if (!empty($fb['replied_at'])): ?>
                                                    <div class="text-muted small"><?php echo formatDate($fb['replied_at']); ?></div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="mt-2"><?php echo nl2br(htmlspecialchars($fb['admin_reply'])); ?></div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php else: ?>
<div class="container pb-5">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card shadow-sm border-0 mt-4">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-lock-fill text-secondary fs-4 me-3"></i>
                        <div>
                            <div class="fw-semibold">Đăng nhập để xem lịch sử phản hồi của bạn</div>
                            <div class="text-muted">Sau khi đăng nhập, bạn sẽ xem được phản hồi từ hệ thống và admin dành cho tài khoản của mình.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
