<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

requireLogin();

// Get filter
$status = $_GET['status'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Build query (always prefix columns to avoid ambiguity)
$where = "d.user_id = ?";
$params = [$_SESSION['user_id']];

if ($status !== '') {
    $where .= " AND d.status = ?";
    $params[] = $status;
}

// Get total count
$countSql = "SELECT COUNT(*) as count FROM donations d WHERE $where";
$totalDonations = Database::fetch($countSql, $params)['count'];
$totalPages = ceil($totalDonations / $per_page);

// Get donations
$limit = (int)$per_page;
$offset = (int)$offset; // avoid PDO emulated prepare issue with LIMIT/OFFSET
$sql = "SELECT d.*, c.name as category_name 
        FROM donations d 
        LEFT JOIN categories c ON d.category_id = c.category_id 
        WHERE $where 
        ORDER BY d.created_at DESC 
        LIMIT $limit OFFSET $offset";
$donations = Database::fetchAll($sql, $params);

// Get statistics
$stats = [
    'total' => Database::fetch("SELECT COUNT(*) as count FROM donations WHERE user_id = ?", [$_SESSION['user_id']])['count'],
    'pending' => Database::fetch("SELECT COUNT(*) as count FROM donations WHERE user_id = ? AND status = 'pending'", [$_SESSION['user_id']])['count'],
    'approved' => Database::fetch("SELECT COUNT(*) as count FROM donations WHERE user_id = ? AND status = 'approved'", [$_SESSION['user_id']])['count'],
    'rejected' => Database::fetch("SELECT COUNT(*) as count FROM donations WHERE user_id = ? AND status = 'rejected'", [$_SESSION['user_id']])['count']
];

$pageTitle = "Quyên góp của tôi";
include 'includes/header.php';
?>

<div class="container mt-5 pt-5">
    <div class="row">
        <div class="col-lg-12">
            <h2 class="mb-4">
                <i class="bi bi-heart-fill text-success me-2"></i>Quyên góp của tôi
            </h2>

            <!-- Statistics -->
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="card border-primary">
                        <div class="card-body text-center">
                            <h3 class="text-primary"><?php echo $stats['total']; ?></h3>
                            <p class="text-muted mb-0">Tổng quyên góp</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card border-warning">
                        <div class="card-body text-center">
                            <h3 class="text-warning"><?php echo $stats['pending']; ?></h3>
                            <p class="text-muted mb-0">Chờ duyệt</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card border-success">
                        <div class="card-body text-center">
                            <h3 class="text-success"><?php echo $stats['approved']; ?></h3>
                            <p class="text-muted mb-0">Đã duyệt</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card border-danger">
                        <div class="card-body text-center">
                            <h3 class="text-danger"><?php echo $stats['rejected']; ?></h3>
                            <p class="text-muted mb-0">Từ chối</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter -->
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="my-donations.php" class="btn btn-<?php echo $status === '' ? 'primary' : 'outline-primary'; ?>">
                            Tất cả (<?php echo $stats['total']; ?>)
                        </a>
                        <a href="my-donations.php?status=pending" class="btn btn-<?php echo $status === 'pending' ? 'warning' : 'outline-warning'; ?>">
                            Chờ duyệt (<?php echo $stats['pending']; ?>)
                        </a>
                        <a href="my-donations.php?status=approved" class="btn btn-<?php echo $status === 'approved' ? 'success' : 'outline-success'; ?>">
                            Đã duyệt (<?php echo $stats['approved']; ?>)
                        </a>
                        <a href="my-donations.php?status=rejected" class="btn btn-<?php echo $status === 'rejected' ? 'danger' : 'outline-danger'; ?>">
                            Từ chối (<?php echo $stats['rejected']; ?>)
                        </a>
                        <a href="donate.php" class="btn btn-success ms-auto">
                            <i class="bi bi-plus-circle me-1"></i>Quyên góp mới
                        </a>
                    </div>
                </div>
            </div>

            <!-- Donations List -->
            <?php if (empty($donations)): ?>
                <div class="card shadow-sm">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-inbox display-1 text-muted"></i>
                        <h4 class="mt-3 text-muted">Chưa có quyên góp nào</h4>
                        <p class="text-muted">Hãy bắt đầu chia sẻ yêu thương với cộng đồng!</p>
                        <a href="donate.php" class="btn btn-success mt-3">
                            <i class="bi bi-heart-fill me-2"></i>Quyên góp ngay
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Vật phẩm</th>
                                        <th>Danh mục</th>
                                        <th>Số lượng</th>
                                        <th>Trạng thái</th>
                                        <th>Ngày tạo</th>
                                        <th>Hành động</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($donations as $donation): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <?php
                                                    $images = json_decode($donation['images'] ?? '[]', true);
                                                    if (!empty($images)):
                                                    ?>
                                                        <img src="uploads/donations/<?php echo $images[0]; ?>" 
                                                             class="rounded me-2" 
                                                             style="width: 50px; height: 50px; object-fit: cover;"
                                                             onerror="this.src='uploads/donations/placeholder-default.svg'">
                                                    <?php endif; ?>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($donation['item_name']); ?></strong>
                                                        <?php if ($donation['description']): ?>
                                                            <br><small class="text-muted">
                                                                <?php echo htmlspecialchars(substr($donation['description'], 0, 50)); ?>...
                                                            </small>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($donation['category_name'] ?? 'N/A'); ?></td>
                                            <td><?php echo $donation['quantity']; ?> <?php echo $donation['unit']; ?></td>
                                            <td>
                                                <?php
                                                $statusMap = [
                                                    'pending' => ['class' => 'warning', 'text' => 'Chờ duyệt', 'icon' => 'clock'],
                                                    'approved' => ['class' => 'success', 'text' => 'Đã duyệt', 'icon' => 'check-circle'],
                                                    'rejected' => ['class' => 'danger', 'text' => 'Từ chối', 'icon' => 'x-circle'],
                                                    'cancelled' => ['class' => 'secondary', 'text' => 'Đã hủy', 'icon' => 'dash-circle']
                                                ];
                                                $st = $statusMap[$donation['status']] ?? ['class' => 'secondary', 'text' => 'N/A', 'icon' => 'question'];
                                                ?>
                                                <span class="badge bg-<?php echo $st['class']; ?>">
                                                    <i class="bi bi-<?php echo $st['icon']; ?> me-1"></i>
                                                    <?php echo $st['text']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo formatDate($donation['created_at'], 'd/m/Y H:i'); ?></td>
                                            <td class="d-flex gap-2">
                                                <button type="button" 
                                                        class="btn btn-sm btn-info" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#viewModal<?php echo $donation['donation_id']; ?>">
                                                    <i class="bi bi-eye"></i> Xem
                                                </button>
                                                <a href="donation-tracking.php?id=<?php echo $donation['donation_id']; ?>" 
                                                   class="btn btn-sm btn-outline-success">
                                                    <i class="bi bi-geo-alt"></i> Theo dõi
                                                </a>
                                            </td>
                                        </tr>

                                        <!-- View Modal -->
                                        <div class="modal fade" id="viewModal<?php echo $donation['donation_id']; ?>" tabindex="-1">
                                            <div class="modal-dialog modal-lg">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Chi tiết quyên góp #<?php echo $donation['donation_id']; ?></h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <p><strong>Vật phẩm:</strong> <?php echo htmlspecialchars($donation['item_name']); ?></p>
                                                                <p><strong>Danh mục:</strong> <?php echo htmlspecialchars($donation['category_name'] ?? 'N/A'); ?></p>
                                                                <p><strong>Số lượng:</strong> <?php echo $donation['quantity']; ?> <?php echo $donation['unit']; ?></p>
                                                                <p><strong>Tình trạng:</strong> <?php echo htmlspecialchars($donation['condition_status']); ?></p>
                                                                <?php if ($donation['estimated_value']): ?>
                                                                    <p><strong>Giá trị ước tính:</strong> <?php echo formatCurrency($donation['estimated_value']); ?></p>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <p><strong>Trạng thái:</strong> 
                                                                    <span class="badge bg-<?php echo $st['class']; ?>">
                                                                        <?php echo $st['text']; ?>
                                                                    </span>
                                                                </p>
                                                                <p><strong>Ngày tạo:</strong> <?php echo formatDate($donation['created_at']); ?></p>
                                                                <?php if ($donation['pickup_address']): ?>
                                                                    <p><strong>Địa chỉ:</strong> <?php echo htmlspecialchars($donation['pickup_address']); ?></p>
                                                                <?php endif; ?>
                                                                <?php if ($donation['contact_phone']): ?>
                                                                    <p><strong>SĐT:</strong> <?php echo htmlspecialchars($donation['contact_phone']); ?></p>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                        
                                                        <?php if ($donation['description']): ?>
                                                            <hr>
                                                            <p><strong>Mô tả:</strong></p>
                                                            <p><?php echo nl2br(htmlspecialchars($donation['description'])); ?></p>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($donation['admin_notes'] && $donation['status'] === 'rejected'): ?>
                                                            <hr>
                                                            <div class="alert alert-danger">
                                                                <strong>Lý do từ chối:</strong><br>
                                                                <?php echo nl2br(htmlspecialchars($donation['admin_notes'])); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                        
                                                        <?php
                                                        $images = json_decode($donation['images'] ?? '[]', true);
                                                        if (!empty($images)):
                                                        ?>
                                                            <hr>
                                                            <p><strong>Hình ảnh:</strong></p>
                                                            <div class="row">
                                                                <?php foreach ($images as $img): ?>
                                                                    <div class="col-md-3 mb-2">
                                                                        <img src="uploads/donations/<?php echo $img; ?>" 
                                                                             class="img-fluid rounded" 
                                                                             onerror="this.src='uploads/donations/placeholder-default.svg'">
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
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
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
