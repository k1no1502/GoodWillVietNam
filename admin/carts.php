<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireAdmin();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $cart_id = (int)($_POST['cart_id'] ?? 0);
    $action = $_POST['action'];
    
    if ($cart_id > 0) {
        try {
            if ($action === 'update_quantity') {
                $quantity = (int)($_POST['quantity'] ?? 1);
                
                if ($quantity <= 0) {
                    throw new Exception('Số lượng phải lớn hơn 0.');
                }
                
                // Check available quantity
                $cart = Database::fetch("
                    SELECT c.*, i.quantity as inventory_quantity,
                           (i.quantity - COALESCE((SELECT SUM(quantity) FROM cart WHERE item_id = i.item_id AND cart_id != ?), 0)) as available_quantity
                    FROM cart c
                    JOIN inventory i ON c.item_id = i.item_id
                    WHERE c.cart_id = ?
                ", [$cart_id, $cart_id]);
                
                if ($quantity > $cart['available_quantity']) {
                    throw new Exception('Số lượng vượt quá số lượng có sẵn.');
                }
                
                Database::execute(
                    "UPDATE cart SET quantity = ?, updated_at = NOW() WHERE cart_id = ?",
                    [$quantity, $cart_id]
                );
                setFlashMessage('success', 'Đã cập nhật số lượng.');
                logActivity($_SESSION['user_id'], 'update_cart', "Updated cart #$cart_id quantity to $quantity");
                
            } elseif ($action === 'delete') {
                Database::execute("DELETE FROM cart WHERE cart_id = ?", [$cart_id]);
                setFlashMessage('success', 'Đã xóa sản phẩm khỏi giỏ hàng.');
                logActivity($_SESSION['user_id'], 'delete_cart', "Deleted cart item #$cart_id");
            } elseif ($action === 'clear_user_cart') {
                $user_id = (int)($_POST['user_id'] ?? 0);
                if ($user_id > 0) {
                    Database::execute("DELETE FROM cart WHERE user_id = ?", [$user_id]);
                    setFlashMessage('success', 'Đã xóa toàn bộ giỏ hàng của người dùng.');
                    logActivity($_SESSION['user_id'], 'clear_user_cart', "Cleared cart for user #$user_id");
                }
            }
        } catch (Exception $e) {
            setFlashMessage('error', 'Có lỗi xảy ra: ' . $e->getMessage());
        }
    }
    
    header('Location: carts.php');
    exit();
}

// Get filters
$user_id = (int)($_GET['user_id'] ?? 0);
$search = $_GET['search'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$per_page = 20;
$offset = ($page - 1) * $per_page;

$where = "1=1";
$params = [];

if ($user_id > 0) {
    $where .= " AND c.user_id = ?";
    $params[] = $user_id;
}

if ($search !== '') {
    $where .= " AND (u.name LIKE ? OR u.email LIKE ? OR i.name LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

// Get total count
$totalSql = "SELECT COUNT(*) as count FROM cart c 
             LEFT JOIN users u ON c.user_id = u.user_id 
             LEFT JOIN inventory i ON c.item_id = i.item_id 
             WHERE $where";
$totalCarts = Database::fetch($totalSql, $params)['count'];
$totalPages = ceil($totalCarts / $per_page);

// Get cart items
$sql = "SELECT c.*, 
               u.name as user_name, u.email as user_email,
               i.name as item_name, i.sale_price, i.price_type, i.status as item_status,
               i.quantity as inventory_quantity,
               cat.name as category_name,
               (i.quantity - COALESCE((SELECT SUM(quantity) FROM cart WHERE item_id = i.item_id AND cart_id != c.cart_id), 0)) as available_quantity
        FROM cart c
        LEFT JOIN users u ON c.user_id = u.user_id
        LEFT JOIN inventory i ON c.item_id = i.item_id
        LEFT JOIN categories cat ON i.category_id = cat.category_id
        WHERE $where
        ORDER BY c.created_at DESC
        LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$cartItems = Database::fetchAll($sql, $params);

// Get all users for filter
$users = Database::fetchAll("SELECT user_id, name, email FROM users ORDER BY name");

// Get statistics
$stats = [
    'total_carts' => Database::fetch("SELECT COUNT(*) as count FROM cart")['count'],
    'total_users' => Database::fetch("SELECT COUNT(DISTINCT user_id) as count FROM cart")['count'],
    'total_items' => Database::fetch("SELECT SUM(quantity) as count FROM cart")['count'] ?? 0,
    'total_value' => Database::fetch("
        SELECT SUM(c.quantity * COALESCE(i.sale_price, 0)) as total
        FROM cart c
        LEFT JOIN inventory i ON c.item_id = i.item_id
    ")['total'] ?? 0,
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý giỏ hàng - Admin</title>
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
                    <h1 class="h2"><i class="bi bi-cart3 me-2"></i>Quản lý giỏ hàng</h1>
                </div>

                <?php echo displayFlashMessages(); ?>

                <!-- Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <h6>Tổng giỏ hàng</h6>
                                <h3><?php echo number_format($stats['total_carts']); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <h6>Người dùng có giỏ hàng</h6>
                                <h3><?php echo number_format($stats['total_users']); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <h6>Tổng sản phẩm</h6>
                                <h3><?php echo number_format($stats['total_items']); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-dark">
                            <div class="card-body">
                                <h6>Tổng giá trị</h6>
                                <h3><?php echo formatCurrency($stats['total_value']); ?></h3>
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
                                       placeholder="Tên người dùng, email, sản phẩm...">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Người dùng</label>
                                <select class="form-select" name="user_id">
                                    <option value="">Tất cả người dùng</option>
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?php echo $user['user_id']; ?>" 
                                                <?php echo $user_id == $user['user_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($user['name'] . ' (' . $user['email'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
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

                <!-- Cart items table -->
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Người dùng</th>
                                        <th>Sản phẩm</th>
                                        <th>Danh mục</th>
                                        <th>Số lượng</th>
                                        <th>Giá</th>
                                        <th>Tổng</th>
                                        <th>Trạng thái</th>
                                        <th>Ngày thêm</th>
                                        <th>Hành động</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($cartItems)): ?>
                                        <tr>
                                            <td colspan="10" class="text-center text-muted">Không có sản phẩm nào trong giỏ hàng.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($cartItems as $item): ?>
                                            <tr>
                                                <td><?php echo $item['cart_id']; ?></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($item['user_name'] ?? 'N/A'); ?></strong>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($item['user_email'] ?? ''); ?></small>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($item['item_name'] ?? 'N/A'); ?></strong>
                                                </td>
                                                <td><?php echo htmlspecialchars($item['category_name'] ?? 'N/A'); ?></td>
                                                <td>
                                                    <span class="badge bg-info"><?php echo $item['quantity']; ?></span>
                                                    <br><small class="text-muted">Có sẵn: <?php echo $item['available_quantity']; ?></small>
                                                </td>
                                                <td>
                                                    <?php if ($item['price_type'] === 'free'): ?>
                                                        <span class="badge bg-success">Miễn phí</span>
                                                    <?php else: ?>
                                                        <?php echo formatCurrency($item['sale_price'] ?? 0); ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <strong>
                                                        <?php 
                                                        $subtotal = ($item['sale_price'] ?? 0) * $item['quantity'];
                                                        echo formatCurrency($subtotal);
                                                        ?>
                                                    </strong>
                                                </td>
                                                <td>
                                                    <?php
                                                    $statusMap = [
                                                        'available' => ['class' => 'success', 'text' => 'Có sẵn'],
                                                        'reserved' => ['class' => 'warning', 'text' => 'Đã đặt'],
                                                        'sold' => ['class' => 'danger', 'text' => 'Đã bán']
                                                    ];
                                                    $st = $statusMap[$item['item_status']] ?? ['class' => 'secondary', 'text' => 'N/A'];
                                                    ?>
                                                    <span class="badge bg-<?php echo $st['class']; ?>">
                                                        <?php echo $st['text']; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo formatDate($item['created_at']); ?></td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <button type="button" 
                                                                class="btn btn-primary" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#editModal<?php echo $item['cart_id']; ?>">
                                                            <i class="bi bi-pencil"></i>
                                                        </button>
                                                        <form method="POST" class="d-inline" onsubmit="return confirm('Xóa sản phẩm này khỏi giỏ hàng?');">
                                                            <input type="hidden" name="cart_id" value="<?php echo $item['cart_id']; ?>">
                                                            <input type="hidden" name="action" value="delete">
                                                            <button type="submit" class="btn btn-danger">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>

                                            <!-- Edit Modal -->
                                            <div class="modal" id="editModal<?php echo $item['cart_id']; ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <form method="POST">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Chỉnh sửa giỏ hàng</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <input type="hidden" name="cart_id" value="<?php echo $item['cart_id']; ?>">
                                                                <input type="hidden" name="action" value="update_quantity">
                                                                
                                                                <div class="mb-3">
                                                                    <label class="form-label">Người dùng</label>
                                                                    <input type="text" 
                                                                           class="form-control" 
                                                                           value="<?php echo htmlspecialchars($item['user_name'] ?? 'N/A'); ?>" 
                                                                           readonly>
                                                                </div>
                                                                
                                                                <div class="mb-3">
                                                                    <label class="form-label">Sản phẩm</label>
                                                                    <input type="text" 
                                                                           class="form-control" 
                                                                           value="<?php echo htmlspecialchars($item['item_name'] ?? 'N/A'); ?>" 
                                                                           readonly>
                                                                </div>
                                                                
                                                                <div class="mb-3">
                                                                    <label class="form-label">Số lượng hiện tại</label>
                                                                    <input type="text" 
                                                                           class="form-control" 
                                                                           value="<?php echo $item['quantity']; ?>" 
                                                                           readonly>
                                                                </div>
                                                                
                                                                <div class="mb-3">
                                                                    <label class="form-label">Số lượng có sẵn</label>
                                                                    <input type="text" 
                                                                           class="form-control" 
                                                                           value="<?php echo $item['available_quantity']; ?>" 
                                                                           readonly>
                                                                </div>
                                                                
                                                                <div class="mb-3">
                                                                    <label class="form-label">Số lượng mới *</label>
                                                                    <input type="number" 
                                                                           class="form-control" 
                                                                           name="quantity" 
                                                                           value="<?php echo $item['quantity']; ?>"
                                                                           min="1"
                                                                           max="<?php echo $item['available_quantity']; ?>"
                                                                           required>
                                                                    <small class="text-muted">Tối đa: <?php echo $item['available_quantity']; ?> sản phẩm</small>
                                                                </div>
                                                                
                                                                <div class="mb-3">
                                                                    <label class="form-label">Giá</label>
                                                                    <input type="text" 
                                                                           class="form-control" 
                                                                           value="<?php echo $item['price_type'] === 'free' ? 'Miễn phí' : formatCurrency($item['sale_price'] ?? 0); ?>" 
                                                                           readonly>
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

                <!-- User Cart Summary -->
                <?php if ($user_id > 0): ?>
                    <?php
                    $userCartSummary = Database::fetchAll("
                        SELECT c.*, 
                               i.name as item_name, i.sale_price, i.price_type,
                               cat.name as category_name
                        FROM cart c
                        LEFT JOIN inventory i ON c.item_id = i.item_id
                        LEFT JOIN categories cat ON i.category_id = cat.category_id
                        WHERE c.user_id = ?
                        ORDER BY c.created_at DESC
                    ", [$user_id]);
                    
                    if (!empty($userCartSummary)):
                        $userTotal = 0;
                        $userItems = 0;
                        foreach ($userCartSummary as $cartItem) {
                            $userItems += $cartItem['quantity'];
                            $userTotal += ($cartItem['sale_price'] ?? 0) * $cartItem['quantity'];
                        }
                    ?>
                        <div class="card shadow-sm mt-4">
                            <div class="card-header bg-primary text-white">
                                <h6 class="mb-0">
                                    <i class="bi bi-cart-check me-2"></i>Tổng kết giỏ hàng người dùng
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>Tổng sản phẩm:</strong> <?php echo number_format($userItems); ?></p>
                                        <p><strong>Tổng giá trị:</strong> <?php echo formatCurrency($userTotal); ?></p>
                                    </div>
                                    <div class="col-md-6 text-end">
                                        <form method="POST" onsubmit="return confirm('Xóa toàn bộ giỏ hàng của người dùng này?');">
                                            <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                                            <input type="hidden" name="action" value="clear_user_cart">
                                            <button type="submit" class="btn btn-danger">
                                                <i class="bi bi-trash me-1"></i>Xóa toàn bộ giỏ hàng
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

