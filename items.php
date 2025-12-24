<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

$pageTitle = "Vật phẩm thiện nguyện";

// Get filter parameters
$category_id = (int)($_GET['category'] ?? 0);
$search = sanitize($_GET['search'] ?? '');
$page = (int)($_GET['page'] ?? 1);
$per_page = 12;
$offset = ($page - 1) * $per_page;

// Get categories
$categories = Database::fetchAll("SELECT * FROM categories WHERE status = 'active' ORDER BY sort_order, name");

// Build query for donations (not inventory)
$where = ["d.status = 'approved'"];
$params = [];

if ($category_id > 0) {
    $where[] = "d.category_id = ?";
    $params[] = $category_id;
}

if (!empty($search)) {
    $where[] = "(d.item_name LIKE ? OR d.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereClause = implode(' AND ', $where);

// Get total count
$countSql = "SELECT COUNT(*) as count FROM donations d WHERE $whereClause";
$totalItems = Database::fetch($countSql, $params)['count'];
$totalPages = ceil($totalItems / $per_page);

// Get donations (not inventory)
$sql = "SELECT d.*, c.name as category_name, c.icon as category_icon, u.name as donor_name
        FROM donations d 
        LEFT JOIN categories c ON d.category_id = c.category_id 
        LEFT JOIN users u ON d.user_id = u.user_id
        WHERE $whereClause 
        ORDER BY d.created_at DESC 
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
                <i class="bi bi-gift me-2"></i>Vật phẩm thiện nguyện
            </h1>
            <p class="lead text-muted">Tìm kiếm và nhận các vật phẩm giá rẻ hoặc miễn phí từ cộng đồng</p>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <i class="bi bi-funnel me-2"></i>Bộ lọc tìm kiếm
                    </h5>
                </div>
                <div class="card-body">
                    <form method="GET" action="items.php" id="filterForm">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label fw-bold">Tìm kiếm</label>
                                <input type="text" 
                                       class="form-control" 
                                       name="search" 
                                       placeholder="Tìm vật phẩm..."
                                       value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label fw-bold">Danh mục</label>
                                <select class="form-select" name="category" onchange="document.getElementById('filterForm').submit();">
                                    <option value="">Tất cả danh mục</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat['category_id']; ?>" 
                                                <?php echo $category_id == $cat['category_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <button type="submit" class="btn btn-success w-100">
                                    <i class="bi bi-search me-2"></i>Tìm kiếm
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Active Filters -->
    <?php if ($category_id > 0 || !empty($search)): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="alert alert-info">
                    <h6 class="d-inline me-2">Bộ lọc đang áp dụng:</h6>
                    <?php if (!empty($search)): ?>
                        <span class="badge bg-secondary me-2">
                            Tìm kiếm: "<?php echo htmlspecialchars($search); ?>"
                            <a href="?<?php echo http_build_query(array_diff_key($_GET, ['search' => ''])); ?>" class="text-white ms-1">×</a>
                        </span>
                    <?php endif; ?>
                    <?php if ($category_id > 0): ?>
                        <span class="badge bg-primary me-2">
                            Danh mục: <?php 
                                $cat = array_filter($categories, fn($c) => $c['category_id'] == $category_id);
                                echo htmlspecialchars(reset($cat)['name'] ?? '');
                            ?>
                            <a href="?<?php echo http_build_query(array_diff_key($_GET, ['category' => ''])); ?>" class="text-white ms-1">×</a>
                        </span>
                    <?php endif; ?>
                    <a href="items.php" class="btn btn-sm btn-outline-secondary">Xóa tất cả</a>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Results Info -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <p class="text-muted mb-0">
                    Tìm thấy <strong><?php echo $totalItems; ?></strong> vật phẩm
                    <?php if ($totalPages > 1): ?>
                        - Trang <strong><?php echo $page; ?></strong> / <strong><?php echo $totalPages; ?></strong>
                    <?php endif; ?>
                </p>
                <div class="btn-group" role="group">
                    <a href="shop.php" class="btn btn-outline-success">
                        <i class="bi bi-shop me-1"></i>Shop Bán Hàng
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Items Grid -->
    <?php if (empty($items)): ?>
        <div class="row">
            <div class="col-12">
                <div class="text-center py-5">
                    <i class="bi bi-inbox display-1 text-muted"></i>
                    <h4 class="mt-3 text-muted">Không tìm thấy vật phẩm nào</h4>
                    <p class="text-muted">Vui lòng thử thay đổi bộ lọc hoặc tìm kiếm khác.</p>
                    <a href="items.php" class="btn btn-success">
                        <i class="bi bi-arrow-left me-1"></i>Xem tất cả vật phẩm
                    </a>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($items as $item): ?>
                <?php
                $images = json_decode($item['images'] ?? '[]', true);
                $imageUrl = !empty($images) ? 'uploads/donations/' . $images[0] : 'uploads/donations/placeholder-default.svg';
                
                $conditionMap = [
                    'new' => 'Mới',
                    'like_new' => 'Như mới',
                    'good' => 'Tốt',
                    'fair' => 'Khá',
                    'poor' => 'Cũ'
                ];
                $conditionText = $conditionMap[$item['condition_status']] ?? 'Không xác định';
                ?>
                <div class="col-md-6 col-lg-4 col-xl-3">
                    <div class="card h-100 shadow-sm">
                        <div class="position-relative">
                            <img src="<?php echo $imageUrl; ?>" 
                                 class="card-img-top" 
                                 style="height: 200px; object-fit: cover;"
                                 alt="<?php echo htmlspecialchars($item['item_name']); ?>"
                                 onerror="this.src='uploads/donations/placeholder-default.svg'">
                            <span class="badge bg-success position-absolute top-0 start-0 m-2">
                                ĐÃ NHẬN
                            </span>
                            <?php if ($item['condition_status']): ?>
                                <span class="badge bg-info position-absolute top-0 end-0 m-2">
                                    <?php echo $conditionText; ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="card-body d-flex flex-column">
                            <h6 class="card-title fw-bold">
                                <?php echo htmlspecialchars($item['item_name']); ?>
                            </h6>
                            
                            <p class="card-text text-muted small flex-grow-1">
                                <i class="bi bi-tag me-1"></i>
                                <?php echo htmlspecialchars($item['category_name'] ?? 'Khác'); ?>
                            </p>
                            
                            <p class="card-text small text-muted">
                                <i class="bi bi-person me-1"></i>
                                <strong>Người quyên góp:</strong> <?php echo htmlspecialchars($item['donor_name'] ?? 'Ẩn danh'); ?>
                            </p>
                            
                            <p class="card-text small text-muted">
                                <i class="bi bi-box me-1"></i>
                                <strong>Số lượng:</strong> <?php echo $item['quantity']; ?> <?php echo $item['unit'] ?? 'Cái'; ?>
                            </p>
                            
                            <p class="card-text small text-muted">
                                <?php echo htmlspecialchars(substr($item['description'] ?? '', 0, 80)); ?>
                                <?php if (strlen($item['description'] ?? '') > 80): ?>...<?php endif; ?>
                            </p>
                            
                            <div class="mt-auto">
                                <div class="d-grid gap-2">
                                    <div class="text-center">
                                        <small class="text-muted">
                                            <i class="bi bi-calendar me-1"></i>
                                            Nhận ngày: <?php echo date('d/m/Y', strtotime($item['created_at'])); ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <nav aria-label="Page navigation" class="mt-5">
                <ul class="pagination justify-content-center">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                <i class="bi bi-chevron-left"></i> Trước
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <?php
                    $start = max(1, $page - 2);
                    $end = min($totalPages, $page + 2);
                    
                    for ($i = $start; $i <= $end; $i++):
                    ?>
                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                Sau <i class="bi bi-chevron-right"></i>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
