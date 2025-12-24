<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireAdmin();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $fb_id = (int)($_POST['fb_id'] ?? 0);
    $action = $_POST['action'];
    
    if ($fb_id > 0) {
        try {
            if ($action === 'reply') {
                $admin_reply = sanitize($_POST['admin_reply'] ?? '');
                
                if (empty($admin_reply)) {
                    throw new Exception('Nội dung phản hồi không được để trống.');
                }
                
                Database::execute(
                    "UPDATE feedback SET admin_reply = ?, status = 'replied', replied_by = ?, replied_at = NOW(), updated_at = NOW() 
                     WHERE fb_id = ?",
                    [$admin_reply, $_SESSION['user_id'], $fb_id]
                );
                setFlashMessage('success', 'Đã gửi phản hồi.');
                logActivity($_SESSION['user_id'], 'reply_feedback', "Replied to feedback #$fb_id");
                
            } elseif ($action === 'update_status') {
                $status = $_POST['status'];
                Database::execute(
                    "UPDATE feedback SET status = ?, updated_at = NOW() WHERE fb_id = ?",
                    [$status, $fb_id]
                );
                setFlashMessage('success', 'Đã cập nhật trạng thái.');
                logActivity($_SESSION['user_id'], 'update_feedback_status', "Updated feedback #$fb_id status to $status");
            }
        } catch (Exception $e) {
            setFlashMessage('error', 'Có lỗi xảy ra: ' . $e->getMessage());
        }
    }
    
    header('Location: feedback.php');
    exit();
}

// Get filters
$status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$per_page = 20;
$offset = ($page - 1) * $per_page;

$where = "1=1";
$params = [];

if ($status !== '') {
    $where .= " AND f.status = ?";
    $params[] = $status;
}

if ($search !== '') {
    $where .= " AND (f.name LIKE ? OR f.email LIKE ? OR f.subject LIKE ? OR f.content LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

// Get total count
$totalSql = "SELECT COUNT(*) as count FROM feedback f WHERE $where";
$totalFeedback = Database::fetch($totalSql, $params)['count'];
$totalPages = ceil($totalFeedback / $per_page);

// Get feedback
$sql = "SELECT f.*, u.name as user_name, u.email as user_email,
               admin.name as admin_name
        FROM feedback f
        LEFT JOIN users u ON f.user_id = u.user_id
        LEFT JOIN users admin ON f.replied_by = admin.user_id
        WHERE $where
        ORDER BY f.created_at DESC
        LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$feedbackList = Database::fetchAll($sql, $params);

// Get statistics
$stats = [
    'total' => Database::fetch("SELECT COUNT(*) as count FROM feedback")['count'],
    'pending' => Database::fetch("SELECT COUNT(*) as count FROM feedback WHERE status = 'pending'")['count'],
    'read' => Database::fetch("SELECT COUNT(*) as count FROM feedback WHERE status = 'read'")['count'],
    'replied' => Database::fetch("SELECT COUNT(*) as count FROM feedback WHERE status = 'replied'")['count'],
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý phản hồi - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">    <style>
        .feedback-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .feedback-action-btn {
            width: 44px;
            height: 38px;
            border-radius: 12px;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            color: #fff;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }
        .feedback-action-btn:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.25);
        }
        .feedback-action-btn:hover {
            transform: translateY(-1px);
        }
        .feedback-action-btn.view { background-color: #0dcaf0; }
        .feedback-action-btn.reply { background-color: #0d6efd; }
        .feedback-action-btn i { pointer-events: none; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 admin-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="bi bi-chat-dots me-2"></i>Quản lý phản hồi</h1>
                </div>

                <?php echo displayFlashMessages(); ?>

                <!-- Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <h6>Tổng phản hồi</h6>
                                <h3><?php echo number_format($stats['total']); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-dark">
                            <div class="card-body">
                                <h6>Chờ xử lý</h6>
                                <h3><?php echo number_format($stats['pending']); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <h6>Đã đọc</h6>
                                <h3><?php echo number_format($stats['read']); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <h6>Đã phản hồi</h6>
                                <h3><?php echo number_format($stats['replied']); ?></h3>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Tìm kiếm</label>
                                <input type="text" 
                                       class="form-control" 
                                       name="search" 
                                       value="<?php echo htmlspecialchars($search); ?>"
                                       placeholder="Tên, email, tiêu đề...">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Trạng thái</label>
                                <select class="form-select" name="status">
                                    <option value="">Tất cả</option>
                                    <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Chờ xử lý</option>
                                    <option value="read" <?php echo $status === 'read' ? 'selected' : ''; ?>>Đã đọc</option>
                                    <option value="replied" <?php echo $status === 'replied' ? 'selected' : ''; ?>>Đã phản hồi</option>
                                    <option value="closed" <?php echo $status === 'closed' ? 'selected' : ''; ?>>Đã đóng</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-search me-1"></i>Lọc
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Feedback table -->
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Người gửi</th>
                                        <th>Tiêu đề</th>
                                        <th>Nội dung</th>
                                        <th>Đánh giá</th>
                                        <th>Trạng thái</th>
                                        <th>Ngày gửi</th>
                                        <th>Hành động</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($feedbackList)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center text-muted">Không có phản hồi nào.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($feedbackList as $fb): ?>
                                            <tr class="<?php echo $fb['status'] === 'pending' ? 'table-warning' : ''; ?>">
                                                <td><?php echo $fb['fb_id']; ?></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($fb['name'] ?? $fb['user_name'] ?? 'Khách'); ?></strong>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($fb['email'] ?? $fb['user_email'] ?? 'N/A'); ?></small>
                                                </td>
                                                <td><?php echo htmlspecialchars($fb['subject'] ?? 'Không có tiêu đề'); ?></td>
                                                <td>
                                                    <?php echo htmlspecialchars(substr($fb['content'], 0, 80)); ?>...
                                                </td>
                                                <td>
                                                    <?php if ($fb['rating']): ?>
                                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                                            <i class="bi bi-star<?php echo $i <= $fb['rating'] ? '-fill text-warning' : ''; ?>"></i>
                                                        <?php endfor; ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">N/A</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $statusMap = [
                                                        'pending' => ['class' => 'warning', 'text' => 'Chờ xử lý'],
                                                        'read' => ['class' => 'info', 'text' => 'Đã đọc'],
                                                        'replied' => ['class' => 'success', 'text' => 'Đã phản hồi'],
                                                        'closed' => ['class' => 'secondary', 'text' => 'Đã đóng']
                                                    ];
                                                    $st = $statusMap[$fb['status']] ?? ['class' => 'secondary', 'text' => 'N/A'];
                                                    ?>
                                                    <span class="badge bg-<?php echo $st['class']; ?>">
                                                        <?php echo $st['text']; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo formatDate($fb['created_at']); ?></td>
                                                <td>
                                                    <div class="feedback-actions">
                                                        <button type="button" 
                                                                class="feedback-action-btn view" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#viewModal<?php echo $fb['fb_id']; ?>">
                                                            <i class="bi bi-eye"></i>
                                                        </button>
                                                        <?php if ($fb['status'] !== 'replied' && $fb['status'] !== 'closed'): ?>
                                                            <button type="button" 
                                                                    class="feedback-action-btn reply" 
                                                                    data-bs-toggle="modal" 
                                                                    data-bs-target="#replyModal<?php echo $fb['fb_id']; ?>">
                                                                <i class="bi bi-reply"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>

                                            <!-- View Modal -->
                                            <div class="modal" id="viewModal<?php echo $fb['fb_id']; ?>" tabindex="-1">
                                                <div class="modal-dialog modal-lg">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Chi tiết phản hồi #<?php echo $fb['fb_id']; ?></h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <div class="row mb-3">
                                                                <div class="col-md-6">
                                                                    <p><strong>Người gửi:</strong> <?php echo htmlspecialchars($fb['name'] ?? $fb['user_name'] ?? 'Khách'); ?></p>
                                                                    <p><strong>Email:</strong> <?php echo htmlspecialchars($fb['email'] ?? $fb['user_email'] ?? 'N/A'); ?></p>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <p><strong>Tiêu đề:</strong> <?php echo htmlspecialchars($fb['subject'] ?? 'Không có tiêu đề'); ?></p>
                                                                    <p><strong>Đánh giá:</strong> 
                                                                        <?php if ($fb['rating']): ?>
                                                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                                                <i class="bi bi-star<?php echo $i <= $fb['rating'] ? '-fill text-warning' : ''; ?>"></i>
                                                                            <?php endfor; ?>
                                                                        <?php else: ?>
                                                                            <span class="text-muted">N/A</span>
                                                                        <?php endif; ?>
                                                                    </p>
                                                                </div>
                                                            </div>
                                                            <p><strong>Nội dung:</strong></p>
                                                            <div class="border p-3 rounded">
                                                                <?php echo nl2br(htmlspecialchars($fb['content'])); ?>
                                                            </div>
                                                            
                                                            <?php if ($fb['admin_reply']): ?>
                                                                <hr>
                                                                <p><strong>Phản hồi từ admin:</strong></p>
                                                                <div class="border p-3 rounded bg-light">
                                                                    <?php echo nl2br(htmlspecialchars($fb['admin_reply'])); ?>
                                                                </div>
                                                                <small class="text-muted">
                                                                    Phản hồi bởi: <?php echo htmlspecialchars($fb['admin_name'] ?? 'Admin'); ?> 
                                                                    vào <?php echo formatDate($fb['replied_at']); ?>
                                                                </small>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Reply Modal -->
                                            <div class="modal" id="replyModal<?php echo $fb['fb_id']; ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <form method="POST">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Trả lời phản hồi</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <input type="hidden" name="fb_id" value="<?php echo $fb['fb_id']; ?>">
                                                                <input type="hidden" name="action" value="reply">
                                                                
                                                                <div class="mb-3">
                                                                    <label class="form-label">Người gửi</label>
                                                                    <input type="text" 
                                                                           class="form-control" 
                                                                           value="<?php echo htmlspecialchars($fb['name'] ?? $fb['user_name'] ?? 'Khách'); ?>" 
                                                                           readonly>
                                                                </div>
                                                                
                                                                <div class="mb-3">
                                                                    <label class="form-label">Nội dung gốc</label>
                                                                    <textarea class="form-control" rows="3" readonly><?php echo htmlspecialchars($fb['content']); ?></textarea>
                                                                </div>
                                                                
                                                                <div class="mb-3">
                                                                    <label class="form-label">Phản hồi *</label>
                                                                    <textarea class="form-control" name="admin_reply" rows="5" required></textarea>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                                                                <button type="submit" class="btn btn-primary">Gửi phản hồi</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                            <nav class="mt-4">
                                <ul class="pagination justify-content-center">
                                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>



