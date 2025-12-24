<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

$item_id = (int)($_GET['id'] ?? 0);

if ($item_id <= 0) {
    header('Location: shop.php');
    exit();
}

// Get item details
$sql = "SELECT i.*,
        c.name as category_name, c.icon as category_icon,
        d.user_id as donor_id, u.name as donor_name, d.created_at as donation_date,
        GREATEST(i.quantity - COALESCE((SELECT SUM(quantity) FROM cart WHERE item_id = i.item_id), 0), 0) as available_quantity
        FROM inventory i
        LEFT JOIN categories c ON i.category_id = c.category_id
        LEFT JOIN donations d ON i.donation_id = d.donation_id
        LEFT JOIN users u ON d.user_id = u.user_id
        WHERE i.item_id = ?";
$item = Database::fetch($sql, [$item_id]);

if (!$item) {
    header('Location: shop.php');
    exit();
}

// Get related items (same category)
$relatedItems = Database::fetchAll(
    "SELECT i.*, c.name as category_name 
     FROM inventory i 
     LEFT JOIN categories c ON i.category_id = c.category_id
     WHERE i.category_id = ? AND i.item_id != ? AND i.status = 'available' AND i.is_for_sale = TRUE
     ORDER BY RAND()
     LIMIT 4",
    [$item['category_id'], $item_id]
);

// Check if in cart
$inCart = false;
if (isLoggedIn()) {
    $inCart = Database::fetch(
        "SELECT * FROM cart WHERE user_id = ? AND item_id = ?",
        [$_SESSION['user_id'], $item_id]
    ) !== false;
}

$images = json_decode($item['images'] ?? '[]', true);
$availableQty = max(0, (int)($item['available_quantity'] ?? 0));
$pageTitle = $item['name'];
include 'includes/header.php';
?>

<div class="container mt-5 pt-5">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php">Trang chủ</a></li>
            <li class="breadcrumb-item"><a href="shop.php">Shop</a></li>
            <li class="breadcrumb-item">
                <a href="shop.php?category=<?php echo $item['category_id']; ?>">
                    <?php echo htmlspecialchars($item['category_name'] ?? 'Khác'); ?>
                </a>
            </li>
            <li class="breadcrumb-item active"><?php echo htmlspecialchars(substr($item['name'], 0, 30)); ?></li>
        </ol>
    </nav>

    <div class="row">
        <!-- Image Gallery -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow-sm border-0">
                <div class="card-body p-0">
                    <?php if (!empty($images)): ?>
                        <!-- Main Image -->
                        <div id="mainImageContainer" class="position-relative">
                            <img id="mainImage" 
                                 src="uploads/donations/<?php echo $images[0]; ?>" 
                                 class="img-fluid w-100 rounded-top" 
                                 style="height: 500px; object-fit: cover;"
                                 onerror="this.src='uploads/donations/placeholder-default.svg'"
                                 alt="<?php echo htmlspecialchars($item['name']); ?>">
                            
                            <!-- Status Badge -->
                            <?php if ($item['status'] !== 'available'): ?>
                                <div class="position-absolute top-0 start-0 m-3">
                                    <span class="badge bg-danger fs-5">
                                        <?php echo $item['status'] === 'sold' ? 'Đã bán' : 'Không có sẵn'; ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Thumbnails -->
                        <?php if (count($images) > 1): ?>
                            <div class="p-3">
                                <div class="row g-2" id="thumbnails">
                                    <?php foreach ($images as $index => $img): ?>
                                        <div class="col-3">
                                            <img src="uploads/donations/<?php echo $img; ?>" 
                                                 class="img-fluid rounded thumbnail-img <?php echo $index === 0 ? 'active' : ''; ?>" 
                                                 style="cursor: pointer; height: 100px; object-fit: cover; border: 2px solid transparent;"
                                                 onclick="changeMainImage('uploads/donations/<?php echo $img; ?>', this)"
                                                 onerror="this.src='uploads/donations/placeholder-default.svg'">
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <img src="uploads/donations/placeholder-default.svg" 
                             class="img-fluid w-100 rounded" 
                             style="height: 500px; object-fit: cover;">
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Item Details -->
        <div class="col-lg-6">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <!-- Title -->
                    <h2 class="fw-bold mb-3"><?php echo htmlspecialchars($item['name']); ?></h2>
                    
                    <!-- Price -->
                    <div class="mb-4">
                        <?php if ($item['price_type'] === 'free'): ?>
                            <div class="d-flex align-items-center">
                                <span class="badge bg-success fs-3 px-4 py-2">
                                    <i class="bi bi-gift me-2"></i>MIỄN PHÍ
                                </span>
                                <span class="text-muted ms-3">
                                    <del><?php echo formatCurrency($item['estimated_value']); ?></del>
                                </span>
                            </div>
                        <?php else: ?>
                            <div class="d-flex align-items-center">
                                <h3 class="text-warning fw-bold mb-0 me-3">
                                    <?php echo formatCurrency($item['sale_price']); ?>
                                </h3>
                                <span class="badge bg-warning text-dark fs-6">
                                    <i class="bi bi-cash me-1"></i>Giá rẻ
                                </span>
                            </div>
                            <?php if ($item['estimated_value'] > $item['sale_price']): ?>
                                <small class="text-muted">
                                    Giá gốc: <del><?php echo formatCurrency($item['estimated_value']); ?></del>
                                    <span class="badge bg-danger ms-2">
                                        Tiết kiệm <?php echo round((($item['estimated_value'] - $item['sale_price']) / $item['estimated_value']) * 100); ?>%
                                    </span>
                                </small>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Info -->
                    <div class="mb-4">
                        <div class="row g-3">
                            <div class="col-6">
                                <div class="border rounded p-3 text-center">
                                    <i class="bi bi-tag text-primary fs-4"></i>
                                    <p class="mb-0 mt-2 small text-muted">Danh mục</p>
                                    <strong><?php echo htmlspecialchars($item['category_name'] ?? 'Khác'); ?></strong>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="border rounded p-3 text-center">
                                    <i class="bi bi-star text-warning fs-4"></i>
                                    <p class="mb-0 mt-2 small text-muted">Tình trạng</p>
                                    <strong>
                                        <?php
                                        $conditionMap = [
                                            'new' => 'Mới 100%',
                                            'like_new' => 'Như mới',
                                            'good' => 'Tốt',
                                            'fair' => 'Khá',
                                            'poor' => 'Cũ'
                                        ];
                                        echo $conditionMap[$item['condition_status']] ?? 'N/A';
                                        ?>
                                    </strong>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="border rounded p-3 text-center">
                                    <i class="bi bi-box-seam text-success fs-4"></i>
                                    <p class="mb-0 mt-2 small text-muted">Số lượng</p>
                                    <strong><?php echo $availableQty; ?> <?php echo $item['unit']; ?></strong>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="border rounded p-3 text-center">
                                    <i class="bi bi-geo-alt text-danger fs-4"></i>
                                    <p class="mb-0 mt-2 small text-muted">Vị trí</p>
                                    <strong><?php echo htmlspecialchars($item['location'] ?? 'Kho chính'); ?></strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="mb-4">
                        <?php if ($item['status'] === 'available'): ?>
                            <?php if (isLoggedIn()): ?>
                                <?php if ($inCart): ?>
                                    <div class="d-grid gap-2">
                                        <button class="btn btn-secondary btn-lg" disabled>
                                            <i class="bi bi-check-circle me-2"></i>Đã có trong giỏ hàng
                                        </button>
                                        <a href="cart.php" class="btn btn-outline-success btn-lg">
                                            <i class="bi bi-cart3 me-2"></i>Xem giỏ hàng
                                        </a>
                                    </div>
                                <?php elseif ($availableQty <= 0): ?>
                                    <div class="d-grid gap-2">
                                        <button class="btn btn-secondary btn-lg" disabled>
                                            <i class="bi bi-x-circle me-2"></i>Hết hàng
                                        </button>
                                        <a href="shop.php" class="btn btn-outline-secondary">
                                            <i class="bi bi-arrow-left me-2"></i>Ti §¨p t ¯c mua s §_m
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <div class="d-grid gap-2">
                                        <button class="btn btn-success btn-lg add-to-cart" 
                                                data-item-id="<?php echo $item_id; ?>">
                                            <i class="bi bi-cart-plus me-2"></i>Thêm vào giỏ hàng
                                        </button>
                                        <a href="shop.php" class="btn btn-outline-secondary">
                                            <i class="bi bi-arrow-left me-2"></i>Tiếp tục mua sắm
                                        </a>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="d-grid">
                                    <a href="login.php?redirect=item-detail.php?id=<?php echo $item_id; ?>" 
                                       class="btn btn-success btn-lg">
                                        <i class="bi bi-lock me-2"></i>Đăng nhập để đặt hàng
                                    </a>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="alert alert-danger text-center">
                                <i class="bi bi-x-circle-fill me-2"></i>
                                <strong>Vật phẩm này đã được bán</strong>
                            </div>
                            <div class="d-grid">
                                <a href="shop.php" class="btn btn-outline-success btn-lg">
                                    <i class="bi bi-arrow-left me-2"></i>Xem vật phẩm khác
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Description -->
                    <?php if ($item['description']): ?>
                        <div class="mb-4">
                            <h5 class="fw-bold mb-3">
                                <i class="bi bi-file-text me-2"></i>Mô tả chi tiết
                            </h5>
                            <div class="border-start border-primary border-4 ps-3">
                                <p class="text-muted mb-0">
                                    <?php echo nl2br(htmlspecialchars($item['description'])); ?>
                                </p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Additional Info -->
                    <div class="border-top pt-4">
                        <h6 class="fw-bold mb-3">Thông tin khác</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <p class="small mb-2">
                                    <i class="bi bi-calendar-event me-2 text-muted"></i>
                                    <strong>Ngày quyên góp:</strong><br>
                                    <span class="ms-4"><?php echo formatDate($item['donation_date'], 'd/m/Y'); ?></span>
                                </p>
                            </div>
                            <div class="col-md-6">
                                <p class="small mb-2">
                                    <i class="bi bi-person-heart me-2 text-muted"></i>
                                    <strong>Người quyên góp:</strong><br>
                                    <span class="ms-4"><?php echo htmlspecialchars($item['donor_name'] ?? 'Ẩn danh'); ?></span>
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Share -->
                    <div class="border-top pt-4 mt-4">
                        <h6 class="fw-bold mb-3">Chia sẻ:</h6>
                        <div class="d-flex gap-2">
                            <button class="btn btn-outline-primary" onclick="shareOnFacebook()">
                                <i class="bi bi-facebook"></i>
                            </button>
                            <button class="btn btn-outline-info" onclick="shareOnTwitter()">
                                <i class="bi bi-twitter"></i>
                            </button>
                            <button class="btn btn-outline-success" onclick="copyLink()">
                                <i class="bi bi-link-45deg"></i> Copy link
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Related Items -->
    <?php if (!empty($relatedItems)): ?>
        <div class="row mt-5">
            <div class="col-12">
                <h3 class="fw-bold mb-4">
                    <i class="bi bi-star me-2"></i>Vật phẩm tương tự
                </h3>
            </div>
            
            <?php foreach ($relatedItems as $relatedItem): ?>
                <?php
                $relatedImages = json_decode($relatedItem['images'] ?? '[]', true);
                $relatedImageUrl = !empty($relatedImages) ? 'uploads/donations/' . $relatedImages[0] : 'uploads/donations/placeholder-default.svg';
                $relatedPriceDisplay = $relatedItem['price_type'] === 'free' ? 'MIỄN PHÍ' : formatCurrency($relatedItem['sale_price']);
                $relatedBadgeClass = $relatedItem['price_type'] === 'free' ? 'bg-success' : 'bg-warning text-dark';
                ?>
                <div class="col-6 col-md-4 col-lg-3 mb-4">
                    <div class="card h-100 shadow-sm">
                        <div class="position-relative">
                            <img src="<?php echo $relatedImageUrl; ?>" 
                                 class="card-img-top" 
                                 style="height: 180px; object-fit: cover;"
                                 onerror="this.src='uploads/donations/placeholder-default.svg'">
                            <span class="badge <?php echo $relatedBadgeClass; ?> position-absolute top-0 start-0 m-2">
                                <?php echo $relatedPriceDisplay; ?>
                            </span>
                        </div>
                        <div class="card-body">
                            <h6 class="card-title mb-2">
                                <?php echo htmlspecialchars(substr($relatedItem['name'], 0, 40)); ?>
                            </h6>
                            <p class="card-text small text-muted">
                                <i class="bi bi-tag me-1"></i>
                                <?php echo htmlspecialchars($relatedItem['category_name']); ?>
                            </p>
                            <div class="d-grid">
                                <a href="item-detail.php?id=<?php echo $relatedItem['item_id']; ?>" 
                                   class="btn btn-outline-success btn-sm">
                                    <i class="bi bi-eye me-1"></i>Xem chi tiết
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php
$additionalScripts = "
<script>
// Change main image when clicking thumbnail
function changeMainImage(src, element) {
    document.getElementById('mainImage').src = src;
    
    // Remove active class from all thumbnails
    document.querySelectorAll('.thumbnail-img').forEach(img => {
        img.style.borderColor = 'transparent';
        img.classList.remove('active');
    });
    
    // Add active class to clicked thumbnail
    element.style.borderColor = '#198754';
    element.classList.add('active');
}

// Add to cart
const addToCartBtn = document.querySelector('.add-to-cart');
if (addToCartBtn) {
    addToCartBtn.addEventListener('click', function() {
        const itemId = this.dataset.itemId;
        const btn = this;
        
        btn.disabled = true;
        btn.innerHTML = '<span class=\"spinner-border spinner-border-sm me-2\"></span>Đang thêm...';
        
        fetch('api/add-to-cart.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ item_id: itemId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const cartCountEl = document.getElementById('cart-count');
                if (cartCountEl) {
                    cartCountEl.textContent = data.cart_count;
                }
                GoodwillVietnam.showAlert('Đã thêm vào giỏ hàng!', 'success');
                
                // Update button
                btn.outerHTML = `
                    <a href=\"cart.php\" class=\"btn btn-success btn-lg w-100\">
                        <i class=\"bi bi-cart-check me-2\"></i>Xem giỏ hàng
                    </a>
                `;
            } else {
                GoodwillVietnam.showAlert(data.message || 'Có lỗi xảy ra!', 'error');
                btn.innerHTML = '<i class=\"bi bi-cart-plus me-2\"></i>Thêm vào giỏ hàng';
                btn.disabled = false;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            GoodwillVietnam.showAlert('Lỗi kết nối!', 'error');
            btn.innerHTML = '<i class=\"bi bi-cart-plus me-2\"></i>Thêm vào giỏ hàng';
            btn.disabled = false;
        });
    });
}

// Share functions
function shareOnFacebook() {
    const url = window.location.href;
    const shareUrl = 'https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(url);
    window.open(shareUrl, '_blank', 'width=600,height=400');
}

function shareOnTwitter() {
    const url = window.location.href;
    const text = '" . addslashes($item['name']) . "';
    const shareUrl = 'https://twitter.com/intent/tweet?url=' + encodeURIComponent(url) + '&text=' + encodeURIComponent(text);
    window.open(shareUrl, '_blank', 'width=600,height=400');
}

function copyLink() {
    const url = window.location.href;
    navigator.clipboard.writeText(url).then(() => {
        GoodwillVietnam.showAlert('Đã copy link vào clipboard!', 'success');
    }).catch(err => {
        console.error('Failed to copy:', err);
        GoodwillVietnam.showAlert('Không thể copy link!', 'error');
    });
}
</script>

<style>
.thumbnail-img.active {
    border-color: #198754 !important;
}

.thumbnail-img:hover {
    opacity: 0.8;
    transform: scale(1.05);
    transition: all 0.3s ease;
}

#mainImage {
    transition: all 0.3s ease;
}
</style>
";

include 'includes/footer.php';
?>
