<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Get filter parameters
$category_id = (int)($_GET['category'] ?? 0);
$price_type = $_GET['price_type'] ?? '';
$search = sanitize($_GET['search'] ?? '');
$page = (int)($_GET['page'] ?? 1);
$per_page = 12;
$offset = ($page - 1) * $per_page;

// Get categories
$categories = Database::fetchAll("SELECT * FROM categories WHERE status = 'active' ORDER BY sort_order, name");

$pageTitle = "Shop Bán Hàng";

// Build query - Only show items from approved donations
$where = ["i.is_for_sale = TRUE", "i.status = 'available'"];
$params = [];

if ($category_id > 0) {
    $where[] = "i.category_id = ?";
    $params[] = $category_id;
}

if ($price_type !== '') {
    $where[] = "i.price_type = ?";
    $params[] = $price_type;
}

if (!empty($search)) {
    $where[] = "(i.name LIKE ? OR i.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereClause = implode(' AND ', $where);

// Get total count
$countSql = "SELECT COUNT(*) as count FROM inventory i WHERE $whereClause";
$totalItems = Database::fetch($countSql, $params)['count'];
$totalPages = ceil($totalItems / $per_page);

// Get items
$sql = "SELECT i.*, c.name as category_name, c.icon as category_icon 
        FROM inventory i 
        LEFT JOIN categories c ON i.category_id = c.category_id 
        WHERE $whereClause 
        ORDER BY i.created_at DESC 
        LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$items = Database::fetchAll($sql, $params);

include 'includes/header.php';
?>

<!-- Main Content -->
<div class="container py-5 mt-5">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="display-5 fw-bold text-success mb-3">
                <i class="bi bi-shop me-2"></i>Shop Bán Hàng
            </h1>
            <p class="lead text-muted">Mua sắm những món đồ quyên góp với giá ưu đãi</p>
        </div>
    </div>

    <!-- Filters -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label for="category" class="form-label">Danh mục</label>
                            <select class="form-select" id="category" name="category">
                                <option value="">Tất cả danh mục</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['category_id']; ?>" 
                                            <?php echo ($category_id == $category['category_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label for="price_type" class="form-label">Loại giá</label>
                            <select class="form-select" id="price_type" name="price_type">
                                <option value="">Tất cả</option>
                                <option value="free" <?php echo ($price_type === 'free') ? 'selected' : ''; ?>>Miễn phí</option>
                                <option value="cheap" <?php echo ($price_type === 'cheap') ? 'selected' : ''; ?>>Giá rẻ</option>
                            </select>
                        </div>
                        
                        <div class="col-md-4">
                            <label for="search" class="form-label">Tìm kiếm</label>
                            <input type="text" class="form-control" id="search" name="search" 
                                   value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Tìm kiếm sản phẩm...">
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-success">
                                    <i class="bi bi-search me-1"></i>Tìm kiếm
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Results Info -->
    <div class="row mb-3">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <p class="mb-0 text-muted">
                    Hiển thị <?php echo count($items); ?> / <?php echo $totalItems; ?> sản phẩm
                </p>
                <div class="dropdown">
                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        Sắp xếp
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'newest'])); ?>">Mới nhất</a></li>
                        <li><a class="dropdown-item" href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'oldest'])); ?>">Cũ nhất</a></li>
                        <li><a class="dropdown-item" href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'name'])); ?>">Tên A-Z</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Items Grid -->
    <?php if (empty($items)): ?>
        <div class="row">
            <div class="col-12">
                <div class="text-center py-5">
                    <i class="bi bi-search display-1 text-muted"></i>
                    <h3 class="mt-3 text-muted">Không tìm thấy sản phẩm nào</h3>
                    <p class="text-muted">Hãy thử thay đổi bộ lọc hoặc từ khóa tìm kiếm</p>
                    <a href="shop.php" class="btn btn-success">Xem tất cả sản phẩm</a>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="row">
            <?php foreach ($items as $item): ?>
                <?php
                $images = json_decode($item['images'] ?? '[]', true);
                $firstImage = !empty($images) ? 'uploads/donations/' . $images[0] : 'uploads/donations/placeholder-default.svg';
                
                $priceDisplay = '';
                $badgeClass = '';
                
                if ($item['price_type'] === 'free') {
                    $priceDisplay = 'Miễn phí';
                    $badgeClass = 'bg-success';
                } elseif ($item['price_type'] === 'cheap') {
                    $priceDisplay = number_format($item['sale_price']) . ' VNĐ';
                    $badgeClass = 'bg-warning text-dark';
                } else {
                    $priceDisplay = 'Liên hệ';
                    $badgeClass = 'bg-info';
                }
                ?>
                <div class="col-lg-3 col-md-4 col-sm-6 mb-4">
                    <div class="card h-100 shadow-sm">
                        <div class="position-relative">
                            <img src="<?php echo htmlspecialchars($firstImage); ?>" 
                                 class="card-img-top" 
                                 style="height: 200px; object-fit: cover;"
                                 alt="<?php echo htmlspecialchars($item['name']); ?>"
                                 onerror="this.src='uploads/donations/placeholder-default.svg'">
                            <span class="badge <?php echo $badgeClass; ?> position-absolute top-0 start-0 m-2">
                                <?php echo $priceDisplay; ?>
                            </span>
                        </div>
                        
                        <div class="card-body d-flex flex-column">
                            <h6 class="card-title"><?php echo htmlspecialchars(substr($item['name'], 0, 40)); ?></h6>
                            <p class="text-muted small">
                                <i class="bi bi-tag"></i> <?php echo htmlspecialchars($item['category_name'] ?? 'Khác'); ?>
                            </p>
                            <p class="card-text small text-muted mb-3" style="min-height: 40px;">
                                <?php echo htmlspecialchars(substr($item['description'] ?? '', 0, 60)); ?>
                                <?php if (strlen($item['description'] ?? '') > 60): ?>...<?php endif; ?>
                            </p>
                            
                            <div class="mt-auto">
                                <div class="d-grid gap-2">
                                    <a href="item-detail.php?id=<?php echo $item['item_id']; ?>" 
                                       class="btn btn-outline-success btn-sm">
                                        <i class="bi bi-eye me-1"></i>Xem chi tiết
                                    </a>
                                    <?php if (isLoggedIn()): ?>
                                        <button type="button" 
                                                class="btn btn-success btn-sm add-to-cart" 
                                                data-item-id="<?php echo $item['item_id']; ?>">
                                            <i class="bi bi-cart-plus me-1"></i>Thêm vào giỏ
                                        </button>
                                    <?php else: ?>
                                        <a href="login.php?redirect=shop.php" class="btn btn-success btn-sm">
                                            <i class="bi bi-lock me-1"></i>Đăng nhập để mua
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="row mt-4">
                <div class="col-12">
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                        <i class="bi bi-chevron-left"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                        <i class="bi bi-chevron-right"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
// Add to cart functionality
document.addEventListener('DOMContentLoaded', function() {
    const addToCartButtons = document.querySelectorAll('.add-to-cart');
    
    addToCartButtons.forEach(button => {
        button.addEventListener('click', function() {
            const itemId = this.dataset.itemId;
            
            fetch('api/add-to-cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    item_id: itemId,
                    quantity: 1
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    const alert = document.createElement('div');
                    alert.className = 'alert alert-success alert-dismissible fade show position-fixed';
                    alert.style.top = '20px';
                    alert.style.right = '20px';
                    alert.style.zIndex = '9999';
                    alert.innerHTML = `
                        <i class="bi bi-check-circle me-2"></i>Đã thêm vào giỏ hàng!
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    `;
                    document.body.appendChild(alert);
                    
                    // Auto remove after 3 seconds
                    setTimeout(() => {
                        if (alert.parentNode) {
                            alert.parentNode.removeChild(alert);
                        }
                    }, 3000);
                    
                    // Update cart count
                    updateCartCount();
                } else {
                    alert('Lỗi: ' + (data.message || 'Không thể thêm vào giỏ hàng'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Có lỗi xảy ra khi thêm vào giỏ hàng');
            });
        });
    });
});

// Update cart count
function updateCartCount() {
    fetch('api/get-cart-count.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const cartCount = document.getElementById('cart-count');
                if (cartCount) {
                    cartCount.textContent = data.count;
                }
            }
        })
        .catch(error => console.error('Error updating cart count:', error));
}

// Load cart count on page load
updateCartCount();
</script>

<?php include 'includes/footer.php'; ?>
