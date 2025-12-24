<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

$pageTitle = "Shop Bán Hàng";

// Get filter parameters
$category_id = (int)($_GET['category'] ?? 0);
$price_type = $_GET['price_type'] ?? '';
$search = sanitize($_GET['search'] ?? '');
$page = (int)($_GET['page'] ?? 1);
$per_page = 12;
$offset = ($page - 1) * $per_page;

// Get categories
$categories = Database::fetchAll("SELECT * FROM categories WHERE status = 'active' ORDER BY sort_order, name");

// Build query
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

// Include header
include 'includes/header.php';
?>

    <!-- Hero Section -->
    <section class="bg-gradient-primary text-white py-5 mt-5">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h1 class="display-4 fw-bold">
                        <i class="bi bi-shop me-3"></i>Shop Bán Hàng Thiện Nguyện
                    </h1>
                    <p class="lead">Mua sắm vật phẩm giá rẻ và miễn phí từ các quyên góp đã được duyệt</p>
                </div>
                <div class="col-lg-4 text-center d-none d-lg-block">
                    <i class="bi bi-shop-window display-1"></i>
                </div>
            </div>
        </div>
    </section>

    <!-- Filter Section -->
    <section class="py-4 bg-light">
        <div class="container">
            <form method="GET" action="shop-simple.php" id="filterForm">
                <div class="row g-3">
                    <div class="col-md-4">
                        <input type="text" class="form-control" name="search" 
                               placeholder="🔍 Tìm kiếm..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" name="category" onchange="this.form.submit();">
                            <option value="">📦 Tất cả danh mục</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['category_id']; ?>" 
                                        <?php echo $category_id == $cat['category_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" name="price_type" onchange="this.form.submit();">
                            <option value="">💵 Tất cả giá</option>
                            <option value="free" <?php echo $price_type === 'free' ? 'selected' : ''; ?>>
                                🎁 Miễn phí
                            </option>
                            <option value="cheap" <?php echo $price_type === 'cheap' ? 'selected' : ''; ?>>
                                💰 Giá rẻ
                            </option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-success w-100">
                            <i class="bi bi-search"></i> Lọc
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </section>

    <!-- Items Grid -->
    <section class="py-5">
        <div class="container">
            <div class="mb-4">
                <h4>Tìm thấy <strong><?php echo $totalItems; ?></strong> sản phẩm</h4>
            </div>

            <?php if (empty($items)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-inbox display-1 text-muted"></i>
                    <h4 class="mt-3">Không tìm thấy sản phẩm</h4>
                    <a href="shop-simple.php" class="btn btn-success mt-3">Xem tất cả</a>
                </div>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($items as $item): ?>
                        <?php
                        $images = json_decode($item['images'] ?? '[]', true);
                        $imageUrl = !empty($images) ? 'uploads/donations/' . $images[0] : 'uploads/donations/placeholder-default.svg';
                        $priceDisplay = $item['price_type'] === 'free' ? 'MIỄN PHÍ' : formatCurrency($item['sale_price']);
                        $badgeClass = $item['price_type'] === 'free' ? 'bg-success' : 'bg-warning text-dark';
                        ?>
                        <div class="col-6 col-md-4 col-lg-3">
                            <div class="card h-100 shadow-sm">
                                <img src="<?php echo $imageUrl; ?>" 
                                     class="card-img-top" 
                                     style="height: 200px; object-fit: cover;"
                                     onerror="this.src='uploads/donations/placeholder-default.svg'">
                                <span class="badge <?php echo $badgeClass; ?> position-absolute top-0 start-0 m-2">
                                    <?php echo $priceDisplay; ?>
                                </span>
                                <div class="card-body">
                                    <h6 class="card-title"><?php echo htmlspecialchars(substr($item['name'], 0, 40)); ?></h6>
                                    <p class="text-muted small">
                                        <i class="bi bi-tag"></i> <?php echo htmlspecialchars($item['category_name'] ?? 'Khác'); ?>
                                    </p>
                                    <div class="d-grid gap-2">
                                        <a href="item-detail.php?id=<?php echo $item['item_id']; ?>" 
                                           class="btn btn-outline-success btn-sm">
                                            <i class="bi bi-eye"></i> Xem chi tiết
                                        </a>
                                        <?php if (isLoggedIn()): ?>
                                            <button class="btn btn-success btn-sm add-to-cart" 
                                                    data-item-id="<?php echo $item['item_id']; ?>">
                                                <i class="bi bi-cart-plus"></i> Thêm vào giỏ
                                            </button>
                                        <?php else: ?>
                                            <a href="login.php" class="btn btn-success btn-sm">
                                                <i class="bi bi-lock"></i> Đăng nhập
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <nav class="mt-5">
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
            <?php endif; ?>
        </div>
    </section>

<?php
// Additional scripts for this page
$additionalScripts = "
<script>
document.querySelectorAll('.add-to-cart').forEach(btn => {
    btn.addEventListener('click', function() {
        const itemId = this.dataset.itemId;
        this.disabled = true;
        this.innerHTML = '<span class=\"spinner-border spinner-border-sm\"></span> Đang thêm...';
        
        fetch('api/add-to-cart.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ item_id: itemId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('cartCount').textContent = data.cart_count;
                GoodwillVietnam.showAlert('Đã thêm vào giỏ hàng!', 'success');
                this.innerHTML = '<i class=\"bi bi-check\"></i> Đã thêm';
                setTimeout(() => {
                    this.innerHTML = '<i class=\"bi bi-cart-plus\"></i> Thêm vào giỏ';
                    this.disabled = false;
                }, 2000);
            } else {
                GoodwillVietnam.showAlert(data.message, 'error');
                this.innerHTML = '<i class=\"bi bi-cart-plus\"></i> Thêm vào giỏ';
                this.disabled = false;
            }
        });
    });
});
</script>
";

// Include footer
include 'includes/footer.php';
?>
