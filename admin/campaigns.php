<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireAdmin();

// Handle campaign approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $campaign_id = (int)($_POST['campaign_id'] ?? 0);
    $action = $_POST['action'];
    
    if ($campaign_id > 0) {
        try {
            if ($action === 'approve') {
                Database::beginTransaction();
                
                // Check if approved_by column exists, if not use status update only
                $columns = Database::fetchAll("SHOW COLUMNS FROM campaigns LIKE 'approved_by'");
                if (!empty($columns)) {
                    Database::execute(
                        "UPDATE campaigns SET status = 'active', approved_by = ?, approved_at = NOW(), updated_at = NOW() WHERE campaign_id = ?",
                        [$_SESSION['user_id'], $campaign_id]
                    );
                } else {
                    Database::execute(
                        "UPDATE campaigns SET status = 'active', updated_at = NOW() WHERE campaign_id = ?",
                        [$campaign_id]
                    );
                }
                
                Database::commit();
                setFlashMessage('success', 'Đã duyệt chiến dịch thành công.');
                logActivity($_SESSION['user_id'], 'approve_campaign', "Approved campaign #$campaign_id");
                
            } elseif ($action === 'reject') {
                $reject_reason = sanitize($_POST['reject_reason'] ?? 'Không đạt yêu cầu');
                Database::execute(
                    "UPDATE campaigns SET status = 'cancelled', updated_at = NOW() WHERE campaign_id = ?",
                    [$campaign_id]
                );
                setFlashMessage('success', 'Đã từ chối chiến dịch.');
                logActivity($_SESSION['user_id'], 'reject_campaign', "Rejected campaign #$campaign_id: $reject_reason");
            } elseif ($action === 'pause') {
                Database::execute(
                    "UPDATE campaigns SET status = 'paused', updated_at = NOW() WHERE campaign_id = ?",
                    [$campaign_id]
                );
                setFlashMessage('success', 'Đã tạm dừng chiến dịch.');
                logActivity($_SESSION['user_id'], 'pause_campaign', "Paused campaign #$campaign_id");
            } elseif ($action === 'resume') {
                Database::execute(
                    "UPDATE campaigns SET status = 'active', updated_at = NOW() WHERE campaign_id = ?",
                    [$campaign_id]
                );
                setFlashMessage('success', 'Đã tiếp tục chiến dịch.');
                logActivity($_SESSION['user_id'], 'resume_campaign', "Resumed campaign #$campaign_id");
            } elseif ($action === 'update') {
                $name = sanitize($_POST['name'] ?? '');
                $description = sanitize($_POST['description'] ?? '');
                $start_date = $_POST['start_date'] ?? '';
                $end_date = $_POST['end_date'] ?? '';
                $target_items = (int)($_POST['target_items'] ?? 0);
                
                if (empty($name) || empty($description) || empty($start_date) || empty($end_date)) {
                    throw new Exception('Vui lòng điền đầy đủ thông tin.');
                }
                
                Database::execute(
                    "UPDATE campaigns SET name = ?, description = ?, start_date = ?, end_date = ?, target_items = ?, updated_at = NOW() WHERE campaign_id = ?",
                    [$name, $description, $start_date, $end_date, $target_items, $campaign_id]
                );
                setFlashMessage('success', 'Đã cập nhật chiến dịch.');
                logActivity($_SESSION['user_id'], 'update_campaign', "Updated campaign #$campaign_id");
            } elseif ($action === 'delete') {
                // Check if campaign has donations
                $hasDonations = Database::fetch("SELECT COUNT(*) as count FROM campaign_donations WHERE campaign_id = ?", [$campaign_id])['count'];
                if ($hasDonations > 0) {
                    throw new Exception('Không thể xóa chiến dịch đã có quyên góp.');
                }
                
                Database::beginTransaction();
                // Delete campaign items first
                Database::execute("DELETE FROM campaign_items WHERE campaign_id = ?", [$campaign_id]);
                // Delete campaign
                Database::execute("DELETE FROM campaigns WHERE campaign_id = ?", [$campaign_id]);
                Database::commit();
                setFlashMessage('success', 'Đã xóa chiến dịch.');
                logActivity($_SESSION['user_id'], 'delete_campaign', "Deleted campaign #$campaign_id");
            }
        } catch (Exception $e) {
            Database::rollback();
            setFlashMessage('error', 'Có lỗi xảy ra: ' . $e->getMessage());
        }
    }
    
    header('Location: campaigns.php');
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
    $where .= " AND c.status = ?";
    $params[] = $status;
}

if ($search !== '') {
    $where .= " AND (c.name LIKE ? OR c.description LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
}

// Get total count
$totalSql = "SELECT COUNT(*) as count FROM campaigns c WHERE $where";
$totalCampaigns = Database::fetch($totalSql, $params)['count'];
$totalPages = ceil($totalCampaigns / $per_page);

// Get campaigns with creator info
$sql = "SELECT c.*, u.name as creator_name, u.email as creator_email,
               (SELECT COUNT(*) FROM campaign_items WHERE campaign_id = c.campaign_id) as items_count,
               (SELECT COUNT(*) FROM campaign_donations WHERE campaign_id = c.campaign_id) as donations_count
        FROM campaigns c
        LEFT JOIN users u ON c.created_by = u.user_id
        WHERE $where
        ORDER BY c.created_at DESC
        LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$campaigns = Database::fetchAll($sql, $params);

// Get statistics
$stats = [
    'total' => Database::fetch("SELECT COUNT(*) as count FROM campaigns")['count'],
    'pending' => Database::fetch("SELECT COUNT(*) as count FROM campaigns WHERE status = 'draft' OR status = 'pending'")['count'],
    'active' => Database::fetch("SELECT COUNT(*) as count FROM campaigns WHERE status = 'active'")['count'],
    'completed' => Database::fetch("SELECT COUNT(*) as count FROM campaigns WHERE status = 'completed'")['count'],
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý chiến dịch - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .campaign-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .campaign-action-btn {
            width: 48px;
            height: 40px;
            border: none;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 1.1rem;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }
        .campaign-action-btn:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.25);
        }
        .campaign-action-btn:hover {
            transform: translateY(-1px);
        }
        .campaign-action-btn.view { background-color: #0d6efd; }
        .campaign-action-btn.edit { background-color: #6c757d; box-shadow: 0 0 0 4px rgba(13, 110, 253, 0.18); }
        .campaign-action-btn.delete { background-color: #dc3545; }
        .campaign-action-btn.pause { background-color: #f1b600; color: #2d2d2d; }
        .campaign-action-btn.resume { background-color: #198754; }
        .campaign-action-btn i { pointer-events: none; }
        .modal-action-group {
            display: flex;
            gap: 10px;
            align-items: center;
            justify-content: flex-end;
        }
        .modal-action-btn {
            width: 48px;
            height: 40px;
            border: none;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 1.1rem;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }
        .modal-action-btn:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.25);
        }
        .modal-action-btn:hover {
            transform: translateY(-1px);
        }
        .modal-action-btn.cancel { background-color: #6c757d; }
        .modal-action-btn.reject { background-color: #dc3545; }
        .modal-action-btn.save { background-color: #0d6efd; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 admin-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="bi bi-trophy me-2"></i>Quản lý chiến dịch</h1>
                </div>

                <?php echo displayFlashMessages(); ?>

                <!-- Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <h6>Tổng chiến dịch</h6>
                                <h3><?php echo number_format($stats['total']); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-dark">
                            <div class="card-body">
                                <h6>Chờ duyệt</h6>
                                <h3><?php echo number_format($stats['pending']); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <h6>Đang hoạt động</h6>
                                <h3><?php echo number_format($stats['active']); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <h6>Hoàn thành</h6>
                                <h3><?php echo number_format($stats['completed']); ?></h3>
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
                                       placeholder="Tên chiến dịch...">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Trạng thái</label>
                                <select class="form-select" name="status">
                                    <option value="">Tất cả</option>
                                    <option value="draft" <?php echo $status === 'draft' ? 'selected' : ''; ?>>Nháp / Chờ duyệt</option>
                                    <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Đang hoạt động</option>
                                    <option value="paused" <?php echo $status === 'paused' ? 'selected' : ''; ?>>Tạm dừng</option>
                                    <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Hoàn thành</option>
                                    <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Đã hủy</option>
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

                <!-- Campaigns table -->
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Chiến dịch</th>
                                        <th>Người tạo</th>
                                        <th>Thời gian</th>
                                        <th>Mục tiêu</th>
                                        <th>Tiến độ</th>
                                        <th>Trạng thái</th>
                                        <th>Hành động</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($campaigns)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center text-muted">Không có chiến dịch nào.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($campaigns as $campaign): ?>
                                            <tr>
                                                <td><?php echo $campaign['campaign_id']; ?></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($campaign['name']); ?></strong>
                                                    <br><small class="text-muted">
                                                        <?php echo htmlspecialchars(substr($campaign['description'] ?? '', 0, 80)); ?>...
                                                    </small>
                                                    <br><small class="text-info">
                                                        <i class="bi bi-box-seam"></i> <?php echo $campaign['items_count']; ?> vật phẩm | 
                                                        <i class="bi bi-heart"></i> <?php echo $campaign['donations_count']; ?> quyên góp
                                                    </small>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($campaign['creator_name']); ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($campaign['creator_email']); ?></small>
                                                </td>
                                                <td>
                                                    <small>
                                                        Bắt đầu: <?php echo formatDate($campaign['start_date']); ?><br>
                                                        Kết thúc: <?php echo formatDate($campaign['end_date']); ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <?php echo number_format($campaign['target_items'] ?? 0); ?> vật phẩm
                                                </td>
                                                <td>
                                                    <?php
                                                    $progress = $campaign['target_items'] > 0 
                                                        ? min(100, round(($campaign['current_items'] / $campaign['target_items']) * 100))
                                                        : 0;
                                                    ?>
                                                    <div class="progress" style="height: 20px;">
                                                        <div class="progress-bar" 
                                                             role="progressbar" 
                                                             style="width: <?php echo $progress; ?>%"
                                                             aria-valuenow="<?php echo $progress; ?>" 
                                                             aria-valuemin="0" 
                                                             aria-valuemax="100">
                                                            <?php echo $progress; ?>%
                                                        </div>
                                                    </div>
                                                    <small><?php echo $campaign['current_items']; ?> / <?php echo $campaign['target_items']; ?></small>
                                                </td>
                                                <td>
                                                    <?php
                                                    $statusMap = [
                                                        'draft' => ['class' => 'secondary', 'text' => 'Nháp'],
                                                        'pending' => ['class' => 'warning', 'text' => 'Chờ duyệt'],
                                                        'active' => ['class' => 'success', 'text' => 'Hoạt động'],
                                                        'paused' => ['class' => 'info', 'text' => 'Tạm dừng'],
                                                        'completed' => ['class' => 'primary', 'text' => 'Hoàn thành'],
                                                        'cancelled' => ['class' => 'danger', 'text' => 'Đã hủy']
                                                    ];
                                                    $st = $statusMap[$campaign['status']] ?? ['class' => 'secondary', 'text' => 'N/A'];
                                                    ?>
                                                    <span class="badge bg-<?php echo $st['class']; ?>">
                                                        <?php echo $st['text']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php 
                                                        $campaignStatus = strtolower($campaign['status'] ?? '');
                                                        // Xem như \"chờ duyệt\" nếu KHÔNG thuộc các trạng thái đã hoạt động / kết thúc
                                                        $waitingForApproval = !in_array($campaignStatus, [
                                                            'active',
                                                            'approved',
                                                            'paused',
                                                            'completed',
                                                            'cancelled'
                                                        ]);
                                                    ?>
                                                    <div class="campaign-actions">
                                                        <!-- Luôn có nút Nhìn -->
                                                        <button type="button"
                                                                class="campaign-action-btn view"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#viewModal<?php echo $campaign['campaign_id']; ?>"
                                                                title="Xem chi tiết">
                                                            <i class="bi bi-eye"></i>
                                                        </button>

                                                        <?php if ($waitingForApproval): ?>
                                                            <!-- Trạng thái chờ duyệt: [Nhìn] [Đồng ý] [Từ chối] -->
                                                            <form method="POST" class="d-inline" onsubmit="return confirm('Duyệt và bắt đầu chiến dịch này?');">
                                                                <input type="hidden" name="campaign_id" value="<?php echo $campaign['campaign_id']; ?>">
                                                                <input type="hidden" name="action" value="approve">
                                                                <button type="submit"
                                                                        class="btn btn-success campaign-action-btn campaign-approve-btn"
                                                                        title="Chấp nhận chiến dịch">
                                                                    <i class="bi bi-check-lg"></i>
                                                                    <span>Đồng ý</span>
                                                                </button>
                                                            </form>

                                                            <button type="button"
                                                                    class="campaign-action-btn delete"
                                                                    data-bs-toggle="modal"
                                                                    data-bs-target="#rejectModal<?php echo $campaign['campaign_id']; ?>"
                                                                    title="Từ chối chiến dịch">
                                                                <i class="bi bi-x-lg"></i>
                                                            </button>

                                                        <?php else: ?>
                                                            <!-- Sau khi duyệt: [Nhìn] [Chỉnh sửa] [Xóa] + nút Dừng/Chạy -->
                                                            <button type="button"
                                                                    class="campaign-action-btn edit"
                                                                    data-bs-toggle="modal"
                                                                    data-bs-target="#editModal<?php echo $campaign['campaign_id']; ?>"
                                                                    title="Chỉnh sửa chiến dịch">
                                                                <i class="bi bi-pencil"></i>
                                                            </button>

                                                            <form method="POST" class="d-inline" onsubmit="return confirm('Xóa chiến dịch này? Hành động này không thể hoàn tác!');">
                                                                <input type="hidden" name="campaign_id" value="<?php echo $campaign['campaign_id']; ?>">
                                                                <input type="hidden" name="action" value="delete">
                                                                <button type="submit"
                                                                        class="campaign-action-btn delete"
                                                                        title="Xóa chiến dịch">
                                                                    <i class="bi bi-trash"></i>
                                                                </button>
                                                            </form>

                                                            <?php if ($campaignStatus === 'active'): ?>
                                                                <!-- Nút Dừng -->
                                                                <form method="POST" class="d-inline" onsubmit="return confirm('Tạm dừng chiến dịch này?');">
                                                                    <input type="hidden" name="campaign_id" value="<?php echo $campaign['campaign_id']; ?>">
                                                                    <input type="hidden" name="action" value="pause">
                                                                    <button type="submit"
                                                                            class="campaign-action-btn pause"
                                                                            title="Tạm dừng chiến dịch">
                                                                        <i class="bi bi-pause-fill"></i>
                                                                    </button>
                                                                </form>
                                                            <?php elseif ($campaignStatus === 'paused'): ?>
                                                                <!-- Nút Chạy lại -->
                                                                <form method="POST" class="d-inline" onsubmit="return confirm('Tiếp tục chiến dịch này?');">
                                                                    <input type="hidden" name="campaign_id" value="<?php echo $campaign['campaign_id']; ?>">
                                                                    <input type="hidden" name="action" value="resume">
                                                                    <button type="submit"
                                                                            class="campaign-action-btn resume"
                                                                            title="Tiếp tục chiến dịch">
                                                                        <i class="bi bi-play-fill"></i>
                                                                    </button>
                                                                </form>
                                                            <?php endif; ?>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>

                                            <!-- View Modal -->
                                            <div class="modal" id="viewModal<?php echo $campaign['campaign_id']; ?>" tabindex="-1">
                                                <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Chi tiết chiến dịch #<?php echo $campaign['campaign_id']; ?></h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <p><strong>Tên chiến dịch:</strong> <?php echo htmlspecialchars($campaign['name']); ?></p>
                                                                    <p><strong>Người tạo:</strong> <?php echo htmlspecialchars($campaign['creator_name']); ?></p>
                                                                    <p><strong>Email:</strong> <?php echo htmlspecialchars($campaign['creator_email']); ?></p>
                                                                    <p><strong>Ngày bắt đầu:</strong> <?php echo formatDate($campaign['start_date']); ?></p>
                                                                    <p><strong>Ngày kết thúc:</strong> <?php echo formatDate($campaign['end_date']); ?></p>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <p><strong>Mục tiêu:</strong> <?php echo number_format($campaign['target_items']); ?> vật phẩm</p>
                                                                    <p><strong>Đã nhận:</strong> <?php echo number_format($campaign['current_items']); ?> vật phẩm</p>
                                                                    <p><strong>Trạng thái:</strong> 
                                                                        <span class="badge bg-<?php echo $st['class']; ?>">
                                                                            <?php echo $st['text']; ?>
                                                                        </span>
                                                                    </p>
                                                                    <p><strong>Số vật phẩm cần:</strong> <?php echo $campaign['items_count']; ?></p>
                                                                    <p><strong>Số quyên góp:</strong> <?php echo $campaign['donations_count']; ?></p>
                                                                </div>
                                                            </div>
                                                            <p><strong>Mô tả:</strong></p>
                                                            <p><?php echo nl2br(htmlspecialchars($campaign['description'] ?? 'Không có mô tả')); ?></p>
                                                            
                                                            <?php if ($campaign['image']): ?>
                                                                <p><strong>Hình ảnh:</strong></p>
                                                                <img src="../uploads/campaigns/<?php echo $campaign['image']; ?>" 
                                                                     class="img-fluid rounded" 
                                                                     alt="Campaign Image">
                                                            <?php endif; ?>
                                                            
                                                            <?php
                                                            // Get campaign items
                                                            $campaignItems = Database::fetchAll(
                                                                "SELECT ci.*, c.name as category_name 
                                                                 FROM campaign_items ci 
                                                                 LEFT JOIN categories c ON ci.category_id = c.category_id 
                                                                 WHERE ci.campaign_id = ?",
                                                                [$campaign['campaign_id']]
                                                            );
                                                            if (!empty($campaignItems)):
                                                            ?>
                                                                <p><strong>Vật phẩm cần thiết:</strong></p>
                                                                <ul>
                                                                    <?php foreach ($campaignItems as $item): ?>
                                                                        <li>
                                                                            <?php echo htmlspecialchars($item['item_name']); ?> 
                                                                            - <?php echo $item['quantity_needed']; ?> 
                                                                            <?php echo htmlspecialchars($item['unit'] ?? 'cái'); ?>
                                                                            (<?php echo htmlspecialchars($item['category_name'] ?? 'N/A'); ?>)
                                                                        </li>
                                                                    <?php endforeach; ?>
                                                                </ul>
                                                            <?php endif; ?>
                                                        </div>
                                                        <?php if (in_array($campaign['status'], ['draft', 'pending'])): ?>
                                                            <div class="modal-footer">
                                                                <form method="POST" class="d-inline" onsubmit="return confirm('Bạn có chắc chắn muốn chấp nhận và kích hoạt chiến dịch này?');">
                                                                    <input type="hidden" name="campaign_id" value="<?php echo $campaign['campaign_id']; ?>">
                                                                    <input type="hidden" name="action" value="approve">
                                                                    <button type="submit" class="btn btn-success">
                                                                        <i class="bi bi-check-circle me-1"></i>Chấp nhận chiến dịch
                                                                    </button>
                                                                </form>
                                                                <button type="button"
                                                                        class="btn btn-outline-danger"
                                                                        data-bs-target="#rejectModal<?php echo $campaign['campaign_id']; ?>"
                                                                        data-bs-toggle="modal">
                                                                    <i class="bi bi-x-circle me-1"></i>Từ chối
                                                                </button>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Edit Modal -->
                                            <div class="modal" id="editModal<?php echo $campaign['campaign_id']; ?>" tabindex="-1">
                                                <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl">
                                                    <div class="modal-content">
                                                        <form method="POST">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Chỉnh sửa chiến dịch #<?php echo $campaign['campaign_id']; ?></h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <input type="hidden" name="campaign_id" value="<?php echo $campaign['campaign_id']; ?>">
                                                                <input type="hidden" name="action" value="update">
                                                                
                                                                <div class="mb-3">
                                                                    <label class="form-label">Tên chiến dịch *</label>
                                                                    <input type="text" 
                                                                           class="form-control" 
                                                                           name="name" 
                                                                           value="<?php echo htmlspecialchars($campaign['name']); ?>" 
                                                                           required>
                                                                </div>
                                                                
                                                                <div class="mb-3">
                                                                    <label class="form-label">Mô tả *</label>
                                                                    <textarea class="form-control" 
                                                                              name="description" 
                                                                              rows="4" 
                                                                              required><?php echo htmlspecialchars($campaign['description'] ?? ''); ?></textarea>
                                                                </div>
                                                                
                                                                <div class="row">
                                                                    <div class="col-md-6 mb-3">
                                                                        <label class="form-label">Ngày bắt đầu *</label>
                                                                        <input type="date" 
                                                                               class="form-control" 
                                                                               name="start_date" 
                                                                               value="<?php echo $campaign['start_date']; ?>" 
                                                                               required>
                                                                    </div>
                                                                    <div class="col-md-6 mb-3">
                                                                        <label class="form-label">Ngày kết thúc *</label>
                                                                        <input type="date" 
                                                                               class="form-control" 
                                                                               name="end_date" 
                                                                               value="<?php echo $campaign['end_date']; ?>" 
                                                                               required>
                                                                    </div>
                                                                </div>
                                                                
                                                                <div class="mb-3">
                                                                    <label class="form-label">Mục tiêu số lượng vật phẩm *</label>
                                                                    <input type="number" 
                                                                           class="form-control" 
                                                                           name="target_items" 
                                                                           value="<?php echo $campaign['target_items']; ?>" 
                                                                           min="1" 
                                                                           required>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <div class="modal-action-group">
                                                                    <button type="button" class="modal-action-btn cancel" data-bs-dismiss="modal" title="Há»§y">
                                                                        <i class="bi bi-x-lg"></i>
                                                                    </button>
                                                                    <button type="submit" class="modal-action-btn save" title="Cáº­p nháº­t">
                                                                        <i class="bi bi-pencil-square"></i>
                                                                    </button>
                                                                </div>
                                                            </div>
                                                </div>
                                            </div>

                                            <!-- Reject Modal -->
                                            <div class="modal" id="rejectModal<?php echo $campaign['campaign_id']; ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <form method="POST">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Từ chối chiến dịch</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <input type="hidden" name="campaign_id" value="<?php echo $campaign['campaign_id']; ?>">
                                                                <input type="hidden" name="action" value="reject">
                                                                <div class="mb-3">
                                                                    <label class="form-label">Lý do từ chối:</label>
                                                                    <textarea class="form-control" name="reject_reason" rows="3" required></textarea>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <div class="modal-action-group">
                                                                    <button type="button" class="modal-action-btn cancel" data-bs-dismiss="modal" title="Há»§y">
                                                                        <i class="bi bi-x-lg"></i>
                                                                    </button>
                                                                    <button type="submit" class="modal-action-btn reject" title="Tá»« chá»i">
                                                                        <i class="bi bi-x-octagon"></i>
                                                                    </button>
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
