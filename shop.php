<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Params
$category_id = (int)($_GET['category'] ?? 0);
$price_type = $_GET['price_type'] ?? '';
$search = sanitize($_GET['search'] ?? '');
$sort = $_GET['sort'] ?? 'newest';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 12;
$offset = ($page - 1) * $per_page;

// Detect optional inventory columns (compatibility with older DB)
$inventoryCols = Database::fetchAll(
    "SELECT COLUMN_NAME FROM information_schema.COLUMNS 
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inventory'
           AND COLUMN_NAME IN ('is_for_sale','price_type','sale_price','unit')"
);
$hasCol = array_column($inventoryCols, 'COLUMN_NAME', 'COLUMN_NAME');
$hasIsForSale = isset($hasCol['is_for_sale']);
$hasPriceType = isset($hasCol['price_type']);
$hasSalePrice = isset($hasCol['sale_price']);
$hasUnit = isset($hasCol['unit']);

// Data
$categories = Database::fetchAll("SELECT * FROM categories WHERE status = 'active' ORDER BY sort_order, name");
$pageTitle = "Shop Ban Hang";

// Filters
$where = ["i.status = 'available'"];
$params = [];
if ($hasIsForSale) {
    $where[] = "i.is_for_sale = 1";
}
if ($category_id > 0) {
    $where[] = "i.category_id = ?";
    $params[] = $category_id;
}
if ($price_type !== '' && $hasPriceType) {
    $where[] = "i.price_type = ?";
    $params[] = $price_type;
}
if (!empty($search)) {
    $where[] = "(i.name LIKE ? OR i.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$whereClause = implode(' AND ', $where);

// Count
$countSql = "SELECT COUNT(*) as count FROM inventory i WHERE $whereClause";
$totalItems = (int)Database::fetch($countSql, $params)['count'];
$totalPages = max(1, (int)ceil($totalItems / $per_page));

// Sort
$orderBy = 'i.created_at DESC';
switch ($sort) {
    case 'oldest':
        $orderBy = 'i.created_at ASC';
        break;
    case 'name':
        $orderBy = 'i.name ASC';
        break;
    case 'price_asc':
        if ($hasSalePrice) $orderBy = 'i.sale_price ASC';
        break;
    case 'price_desc':
        if ($hasSalePrice) $orderBy = 'i.sale_price DESC';
        break;
    default:
        $orderBy = 'i.created_at DESC';
}

// Select fields with fallbacks if columns missing
$priceTypeSelect = $hasPriceType ? "i.price_type" : "'free' AS price_type";
$salePriceSelect = $hasSalePrice ? "i.sale_price" : "0 AS sale_price";
$unitSelect = $hasUnit ? "IFNULL(i.unit, 'Cai')" : "'Cai' AS unit";

// Items
$sql = "SELECT 
            i.*,
            $priceTypeSelect,
            $salePriceSelect,
            $unitSelect,
            c.name AS category_name,
            c.icon AS category_icon,
            GREATEST(i.quantity - COALESCE((SELECT SUM(quantity) FROM cart WHERE item_id = i.item_id), 0), 0) AS available_quantity
        FROM inventory i
        LEFT JOIN categories c ON i.category_id = c.category_id
        WHERE $whereClause
        ORDER BY $orderBy
        LIMIT ? OFFSET ?";
$paramsWithLimit = array_merge($params, [$per_page, $offset]);
$items = Database::fetchAll($sql, $paramsWithLimit);

include 'includes/header.php';
?>

<!-- Main Content -->
<div class="container py-5 mt-5">
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="display-5 fw-bold text-success mb-3">
                <i class="bi bi-shop me-2"></i>Shop Ban Hang
            </h1>
            <p class="lead text-muted">Mua sam cac mon do quyen gop voi gia uu dai</p>
        </div>
    </div>

    <!-- Filters -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label for="category" class="form-label">Danh muc</label>
                            <select class="form-select" id="category" name="category">
                                <option value="">Tat ca danh muc</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['category_id']; ?>" <?php echo ($category_id == $category['category_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if ($hasPriceType): ?>
                        <div class="col-md-3">
                            <label for="price_type" class="form-label">Loai gia</label>
                            <select class="form-select" id="price_type" name="price_type">
                                <option value="">Tat ca</option>
                                <option value="free" <?php echo ($price_type === 'free') ? 'selected' : ''; ?>>Mien phi</option>
                                <option value="cheap" <?php echo ($price_type === 'cheap') ? 'selected' : ''; ?>>Gia re</option>
                                <option value="normal" <?php echo ($price_type === 'normal') ? 'selected' : ''; ?>>Gia thuong</option>
                            </select>
                        </div>
                        <?php endif; ?>
                        <div class="col-md-4">
                            <label for="search" class="form-label">Tim kiem</label>
                            <input type="text" class="form-control" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Tim san pham...">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-success">
                                    <i class="bi bi-search me-1"></i>Tim kiem
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
                    Hien thi <?php echo count($items); ?> / <?php echo $totalItems; ?> san pham
                </p>
                <div class="dropdown">
                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        Sap xep
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'newest'])); ?>">Moi nhat</a></li>
                        <li><a class="dropdown-item" href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'oldest'])); ?>">Cu nhat</a></li>
                        <li><a class="dropdown-item" href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'name'])); ?>">Ten A-Z</a></li>
                        <?php if ($hasSalePrice): ?>
                        <li><a class="dropdown-item" href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'price_asc'])); ?>">Gia thap den cao</a></li>
                        <li><a class="dropdown-item" href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'price_desc'])); ?>">Gia cao den thap</a></li>
                        <?php endif; ?>
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
                    <h3 class="mt-3 text-muted">Khong tim thay san pham nao</h3>
                    <p class="text-muted">Thu thay doi bo loc hoac tu khoa tim kiem</p>
                    <a href="shop.php" class="btn btn-success">Xem tat ca san pham</a>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="row">
            <?php foreach ($items as $item): ?>
                <?php
                $images = json_decode($item['images'] ?? '[]', true);
                $firstImage = !empty($images) ? 'uploads/donations/' . $images[0] : 'uploads/donations/placeholder-default.svg';

                $priceDisplay = 'Lien he';
                $badgeClass = 'bg-info';
                if ($item['price_type'] === 'free') {
                    $priceDisplay = 'Mien phi';
                    $badgeClass = 'bg-success';
                } elseif ($item['price_type'] === 'cheap' || $item['price_type'] === 'normal') {
                    if ((float)$item['sale_price'] > 0) {
                        $priceDisplay = number_format($item['sale_price']) . ' VND';
                    }
                    $badgeClass = ($item['price_type'] === 'cheap') ? 'bg-warning text-dark' : 'bg-primary';
                }
                $availableQty = max(0, (int)$item['available_quantity']);
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
                                <i class="bi bi-tag"></i> <?php echo htmlspecialchars($item['category_name'] ?? 'Khac'); ?>
                            </p>

                            <div class="mb-2 available-quantity">
                                <small class="text-muted">
                                    <i class="bi bi-box me-1"></i>
                                    Con lai: <strong class="text-<?php echo $availableQty > 0 ? 'success' : 'danger'; ?>"><?php echo $availableQty; ?></strong> <?php echo htmlspecialchars($item['unit']); ?>
                                </small>
                            </div>
                            
                            <p class="card-text small text-muted mb-3" style="min-height: 40px;">
                                <?php echo htmlspecialchars(substr($item['description'] ?? '', 0, 60)); ?>
                                <?php if (strlen($item['description'] ?? '') > 60): ?>...<?php endif; ?>
                            </p>
                            
                            <div class="mt-auto">
                                <div class="d-grid gap-2">
                                    <a href="item-detail.php?id=<?php echo $item['item_id']; ?>" class="btn btn-outline-success btn-sm">
                                        <i class="bi bi-eye me-1"></i>Xem chi tiet
                                    </a>
                                    <?php if (isLoggedIn()): ?>
                                        <?php if ($availableQty > 0): ?>
                                            <button type="button" class="btn btn-success btn-sm add-to-cart" data-item-id="<?php echo $item['item_id']; ?>">
                                                <i class="bi bi-cart-plus me-1"></i>Them vao gio
                                            </button>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-secondary btn-sm" disabled>
                                                <i class="bi bi-x-circle me-1"></i>Het hang
                                            </button>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <a href="login.php?redirect=shop.php" class="btn btn-success btn-sm">
                                            <i class="bi bi-lock me-1"></i>Dang nhap de mua
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
document.addEventListener('DOMContentLoaded', function() {
    const addToCartButtons = document.querySelectorAll('.add-to-cart');
    addToCartButtons.forEach(button => {
        button.addEventListener('click', function() {
            const itemId = this.dataset.itemId;
            const buttonElement = this;
            buttonElement.disabled = true;
            buttonElement.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Dang them...';
            
            fetch('api/add-to-cart.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ item_id: itemId, quantity: 1 })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('Da them vao gio hang!', 'success');
                    updateCartCount();
                    setTimeout(() => location.reload(), 1200);
                } else {
                    showAlert('Loi: ' + (data.message || 'Khong the them vao gio hang'), 'danger');
                    buttonElement.disabled = false;
                    buttonElement.innerHTML = '<i class="bi bi-cart-plus me-1"></i>Them vao gio';
                }
            })
            .catch(() => {
                showAlert('Co loi xay ra khi them vao gio hang', 'danger');
                buttonElement.disabled = false;
                buttonElement.innerHTML = '<i class="bi bi-cart-plus me-1"></i>Them vao gio';
            });
        });
    });
    
    function showAlert(message, type) {
        const alert = document.createElement('div');
        alert.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
        alert.style.top = '20px';
        alert.style.right = '20px';
        alert.style.zIndex = '9999';
        alert.innerHTML = `<i class="bi bi-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} me-2"></i>${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
        document.body.appendChild(alert);
        setTimeout(() => alert.remove(), 3000);
    }
    
    function updateCartCount() {
        fetch('api/get-cart-count.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const cartCount = document.getElementById('cart-count');
                    if (cartCount) cartCount.textContent = data.count;
                }
            });
    }
    
    updateCartCount();
});
</script>

<?php include 'includes/footer.php'; ?>
