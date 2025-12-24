<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireAdmin();

// Handle price type update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $item_id = (int)($_POST['item_id'] ?? 0);
    $action = $_POST['action'];
    
    if ($item_id > 0) {
        try {
            if ($action === 'update_price') {
                $price_type = $_POST['price_type'];
                $sale_price = (float)($_POST['sale_price'] ?? 0);
                
                Database::execute(
                    "UPDATE inventory SET price_type = ?, sale_price = ?, updated_at = NOW() WHERE item_id = ?",
                    [$price_type, $sale_price, $item_id]
                );
                setFlashMessage('success', 'Đã cập nhật giá bán.');
            
            } elseif ($action === 'toggle_sale') {
                Database::execute(
                    "UPDATE inventory SET is_for_sale = NOT is_for_sale, updated_at = NOW() WHERE item_id = ?",
                    [$item_id]
                );
                setFlashMessage('success', 'Đã cập nhật trạng thái bán hàng.');
            }
            
            logActivity($_SESSION['user_id'], 'update_inventory', "Updated inventory item #$item_id");
        } catch (Exception $e) {
            setFlashMessage('error', 'Có lỗi xảy ra: ' . $e->getMessage());
        }
    }
    
    header('Location: inventory.php');
    exit();
}

// Get filters
$price_type = $_GET['price_type'] ?? '';
$category_id = (int)($_GET['category'] ?? 0);
$status = $_GET['status'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$per_page = 20;
$offset = ($page - 1) * $per_page;

$where = "1=1";
$params = [];

if ($price_type !== '') {
    $where .= " AND i.price_type = ?";
    $params[] = $price_type;
}

if ($category_id > 0) {
    $where .= " AND i.category_id = ?";
    $params[] = $category_id;
}

if ($status !== '') {
    $where .= " AND i.status = ?";
    $params[] = $status;
}

// Get categories
$categories = Database::fetchAll("SELECT * FROM categories WHERE status = 'active' ORDER BY name");

// Get total count
$totalSql = "SELECT COUNT(*) as count FROM inventory i WHERE $where";
$totalItems = Database::fetch($totalSql, $params)['count'];
$totalPages = ceil($totalItems / $per_page);

// Get items
$sql = "SELECT i.*, c.name as category_name, d.item_name as donation_name, u.name as donor_name
        FROM inventory i
        LEFT JOIN categories c ON i.category_id = c.category_id
        LEFT JOIN donations d ON i.donation_id = d.donation_id
        LEFT JOIN users u ON d.user_id = u.user_id
        WHERE $where
        ORDER BY i.created_at DESC
        LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$items = Database::fetchAll($sql, $params);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý kho hàng - Admin</title>
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
                    <h1 class="h2"><i class="bi bi-box-seam me-2"></i>Quản lý kho hàng</h1>
                </div>

                <?php echo displayFlashMessages(); ?>

                <!-- Filters -->
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Loại giá</label>
                                <select class="form-select" name="price_type">
                                    <option value="">Tất cả</option>
                                    <option value="free" <?php echo $price_type === 'free' ? 'selected' : ''; ?>>Miễn phí</option>
                                    <option value="cheap" <?php echo $price_type === 'cheap' ? 'selected' : ''; ?>>Giá rẻ</option>
                                    <option value="normal" <?php echo $price_type === 'normal' ? 'selected' : ''; ?>>Giá thường</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Danh mục</label>
                                <select class="form-select" name="category">
                                    <option value="">Tất cả danh mục</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat['category_id']; ?>" 
                                                <?php echo $category_id == $cat['category_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Trạng thái</label>
                                <select class="form-select" name="status">
                                    <option value="">Tất cả</option>
                                    <option value="available" <?php echo $status === 'available' ? 'selected' : ''; ?>>Có sẵn</option>
                                    <option value="reserved" <?php echo $status === 'reserved' ? 'selected' : ''; ?>>Đã đặt</option>
                                    <option value="sold" <?php echo $status === 'sold' ? 'selected' : ''; ?>>Đã bán</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-search me-1"></i>Lọc
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <h6>Có sẵn</h6>
                                <h3><?php echo Database::fetch("SELECT COUNT(*) as count FROM inventory WHERE status = 'available'")['count']; ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <h6>Miễn phí</h6>
                                <h3><?php echo Database::fetch("SELECT COUNT(*) as count FROM inventory WHERE price_type = 'free' AND status = 'available'")['count']; ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-dark">
                            <div class="card-body">
                                <h6>Giá rẻ</h6>
                                <h3><?php echo Database::fetch("SELECT COUNT(*) as count FROM inventory WHERE price_type = 'cheap' AND status = 'available'")['count']; ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <h6>Đã bán</h6>
                                <h3><?php echo Database::fetch("SELECT COUNT(*) as count FROM inventory WHERE status = 'sold'")['count']; ?></h3>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Items table -->
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Vật phẩm</th>
                                        <th>Danh mục</th>
                                        <th>Số lượng</th>
                                        <th>Loại giá</th>
                                        <th>Giá bán</th>
                                        <th>Trạng thái</th>
                                        <th>Hành động</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($items)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center text-muted">Không có vật phẩm nào.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($items as $item): ?>
                                            <tr>
                                                <td><?php echo $item['item_id']; ?></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($item['name']); ?></strong>
                                                    <br><small class="text-muted">Từ: <?php echo htmlspecialchars($item['donor_name']); ?></small>
                                                </td>
                                                <td><?php echo htmlspecialchars($item['category_name'] ?? 'N/A'); ?></td>
                                                <td><?php echo $item['quantity']; ?> <?php echo $item['unit']; ?></td>
                                                <td>
                                                    <?php
                                                    $typeMap = [
                                                        'free' => ['class' => 'success', 'text' => 'Miễn phí'],
                                                        'cheap' => ['class' => 'warning', 'text' => 'Giá rẻ'],
                                                        'normal' => ['class' => 'primary', 'text' => 'Giá thường']
                                                    ];
                                                    $type = $typeMap[$item['price_type']] ?? ['class' => 'secondary', 'text' => 'N/A'];
                                                    ?>
                                                    <span class="badge bg-<?php echo $type['class']; ?>">
                                                        <?php echo $type['text']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php echo $item['price_type'] === 'free' ? 'Miễn phí' : formatCurrency($item['sale_price']); ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $statusMap = [
                                                        'available' => ['class' => 'success', 'text' => 'Có sẵn'],
                                                        'reserved' => ['class' => 'warning', 'text' => 'Đã đặt'],
                                                        'sold' => ['class' => 'info', 'text' => 'Đã bán'],
                                                        'damaged' => ['class' => 'danger', 'text' => 'Hư hỏng']
                                                    ];
                                                    $st = $statusMap[$item['status']] ?? ['class' => 'secondary', 'text' => 'N/A'];
                                                    ?>
                                                    <span class="badge bg-<?php echo $st['class']; ?>">
                                                        <?php echo $st['text']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <button type="button" 
                                                                class="btn btn-primary" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#editModal<?php echo $item['item_id']; ?>">
                                                            <i class="bi bi-pencil"></i>
                                                        </button>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">
                                                            <input type="hidden" name="action" value="toggle_sale">
                                                            <button type="submit" 
                                                                    class="btn btn-<?php echo $item['is_for_sale'] ? 'warning' : 'success'; ?>"
                                                                    title="<?php echo $item['is_for_sale'] ? 'Ẩn khỏi shop' : 'Hiện trong shop'; ?>">
                                                                <i class="bi bi-<?php echo $item['is_for_sale'] ? 'eye-slash' : 'eye'; ?>"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if (!empty($items)): ?>
                            <?php foreach ($items as $item): ?>
                                <div class="modal fade" id="editModal<?php echo $item['item_id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form method="POST">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Cập nhật giá bán</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">
                                                    <input type="hidden" name="action" value="update_price">
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label">Vật phẩm</label>
                                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($item['name']); ?>" readonly>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label">Loại giá *</label>
                                                        <select class="form-select" name="price_type" required>
                                                            <option value="free" <?php echo $item['price_type'] === 'free' ? 'selected' : ''; ?>>Miễn phí</option>
                                                            <option value="cheap" <?php echo $item['price_type'] === 'cheap' ? 'selected' : ''; ?>>Giá rẻ</option>
                                                            <option value="normal" <?php echo $item['price_type'] === 'normal' ? 'selected' : ''; ?>>Giá thường</option>
                                                        </select>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label">Giá bán (VNĐ)</label>
                                                        <input type="number" 
                                                               class="form-control" 
                                                               name="sale_price" 
                                                               value="<?php echo $item['sale_price']; ?>"
                                                               min="0"
                                                               step="1000">
                                                        <small class="text-muted">Để 0 nếu miễn phí</small>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                                                    <button type="submit" class="btn btn-primary">Cập nhật</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>

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
    <script>
    // Ngăn tình trạng hiện nhiều backdrop/modal chồng nhau khi hover/scroll
    document.addEventListener('show.bs.modal', function (event) {
        // Đóng các modal khác đang mở (nếu có)
        document.querySelectorAll('.modal.show').forEach(function (opened) {
            if (opened !== event.target) {
                const instance = bootstrap.Modal.getInstance(opened);
                instance && instance.hide();
            }
        });
        // Xóa bớt backdrop thừa (Bootstrap đẩy khi tạo 2 lớp)
        const backdrops = Array.from(document.querySelectorAll('.modal-backdrop'));
        backdrops.forEach(function (bd, idx) {
            if (idx > 0) bd.remove();
        });
    });
    </script>
</body>
</html>
