<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

requireLogin();

$pageTitle = "Giỏ hàng";

// Get cart items with inventory check
$sql = "SELECT c.*, i.*, cat.name as category_name, i.name as item_name,
        i.quantity as inventory_quantity,
        (i.quantity - COALESCE((SELECT SUM(quantity) FROM cart WHERE item_id = i.item_id AND cart_id != c.cart_id), 0)) as available_quantity
        FROM cart c
        JOIN inventory i ON c.item_id = i.item_id
        LEFT JOIN categories cat ON i.category_id = cat.category_id
        WHERE c.user_id = ? AND i.status = 'available'
        ORDER BY c.created_at DESC";
$cartItems = Database::fetchAll($sql, [$_SESSION['user_id']]);

// Fix any items with quantity > available_quantity
foreach ($cartItems as $item) {
    if ($item['quantity'] > $item['available_quantity']) {
        Database::execute(
            "UPDATE cart SET quantity = ? WHERE cart_id = ?",
            [$item['available_quantity'], $item['cart_id']]
        );
    }
}

// Refresh cart items after fixing
$cartItems = Database::fetchAll($sql, [$_SESSION['user_id']]);

// Calculate totals
$totalItems = 0;
$totalAmount = 0;
$freeItemsCount = 0;
$paidItemsCount = 0;

foreach ($cartItems as $item) {
    $totalItems += $item['quantity'];
    $itemTotal = $item['sale_price'] * $item['quantity'];
    $totalAmount += $itemTotal;
    
    if ($item['price_type'] === 'free') {
        $freeItemsCount += $item['quantity'];
    } else {
        $paidItemsCount += $item['quantity'];
    }
}

$pageTitle = "Giỏ hàng";
include 'includes/header.php';
?>

<div class="container mt-5 pt-5">
    <div class="row">
        <div class="col-lg-12">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0">
                    <i class="bi bi-cart3 text-success me-2"></i>Giỏ hàng của bạn
                </h2>
                <a href="shop.php" class="btn btn-outline-success">
                    <i class="bi bi-arrow-left me-2"></i>Tiếp tục mua sắm
                </a>
            </div>

            <?php if (empty($cartItems)): ?>
                <!-- Empty Cart -->
                <div class="card shadow-sm border-0">
                    <div class="card-body text-center py-5">
                        <div class="mb-4">
                            <i class="bi bi-cart-x display-1 text-muted"></i>
                        </div>
                        <h3 class="fw-bold mb-3">Giỏ hàng trống</h3>
                        <p class="text-muted mb-4">Bạn chưa có vật phẩm nào trong giỏ hàng.</p>
                        <div class="d-flex gap-3 justify-content-center">
                            <a href="shop.php" class="btn btn-success btn-lg">
                                <i class="bi bi-shop me-2"></i>Khám phá Shop
                            </a>
                            <a href="campaigns.php" class="btn btn-outline-success btn-lg">
                                <i class="bi bi-trophy me-2"></i>Xem chiến dịch
                            </a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="row">
                    <!-- Cart Items -->
                    <div class="col-lg-8">
                        <!-- Items List -->
                        <?php foreach ($cartItems as $item): ?>
                            <?php
                            $images = json_decode($item['images'] ?? '[]', true);
                            $imageUrl = !empty($images) ? 'uploads/donations/' . $images[0] : 'uploads/donations/placeholder-default.svg';
                            $itemTotal = $item['sale_price'] * $item['quantity'];
                            ?>
                            <div class="card shadow-sm mb-3 cart-item" data-cart-id="<?php echo $item['cart_id']; ?>">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <!-- Image -->
                                        <div class="col-md-2 col-3">
                                            <img src="<?php echo $imageUrl; ?>" 
                                                 class="img-fluid rounded" 
                                                 alt="<?php echo htmlspecialchars($item['item_name']); ?>"
                                                 onerror="this.src='uploads/donations/placeholder-default.svg'"
                                                 style="width: 100%; height: 80px; object-fit: cover;">
                                        </div>

                                        <!-- Item Info -->
                                        <div class="col-md-4 col-9">
                                            <h6 class="fw-bold mb-1"><?php echo htmlspecialchars($item['item_name']); ?></h6>
                                            <div class="d-flex flex-wrap gap-2 mb-2">
                                                <span class="badge bg-<?php echo $item['price_type'] === 'free' ? 'success' : 'warning text-dark'; ?>">
                                                    <i class="bi bi-<?php echo $item['price_type'] === 'free' ? 'gift' : 'cash'; ?> me-1"></i>
                                                    <?php echo $item['price_type'] === 'free' ? 'Miễn phí' : 'Giá rẻ'; ?>
                                                </span>
                                                <span class="badge bg-info">
                                                    <i class="bi bi-tag me-1"></i>
                                                    <?php echo htmlspecialchars($item['category_name'] ?? 'Khác'); ?>
                                                </span>
                                                <?php if ($item['condition_status']): ?>
                                                    <span class="badge bg-secondary">
                                                        <?php
                                                        $conditionMap = ['new' => 'Mới', 'like_new' => 'Như mới', 'good' => 'Tốt', 'fair' => 'Khá', 'poor' => 'Cũ'];
                                                        echo $conditionMap[$item['condition_status']] ?? 'N/A';
                                                        ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <small class="text-muted">
                                                <i class="bi bi-box me-1"></i>
                                                Còn lại: <strong><?php echo max(0, $item['available_quantity']); ?></strong> <?php echo $item['unit']; ?>
                                            </small>
                                        </div>

                                        <!-- Price -->
                                        <div class="col-md-2 col-4 text-center">
                                            <p class="text-muted small mb-0">Đơn giá</p>
                                            <strong class="fs-5 text-<?php echo $item['price_type'] === 'free' ? 'success' : 'warning'; ?>">
                                                <?php echo $item['price_type'] === 'free' ? 'Miễn phí' : formatCurrency($item['sale_price']); ?>
                                            </strong>
                                        </div>

                                        <!-- Quantity -->
                                        <div class="col-md-2 col-4">
                                            <label class="form-label small text-muted mb-1">Số lượng</label>
                                            <div class="input-group input-group-sm">
                                                <button class="btn btn-outline-secondary update-quantity" 
                                                        data-action="decrease"
                                                        data-cart-id="<?php echo $item['cart_id']; ?>"
                                                        <?php echo $item['quantity'] <= 1 ? 'disabled' : ''; ?>>
                                                    <i class="bi bi-dash"></i>
                                                </button>
                                                <input type="text" 
                                                       class="form-control text-center quantity-display" 
                                                       value="<?php echo $item['quantity']; ?>" 
                                                       readonly>
                                                <button class="btn btn-outline-secondary update-quantity" 
                                                        data-action="increase"
                                                        data-cart-id="<?php echo $item['cart_id']; ?>"
                                                        data-max="<?php echo max(1, $item['available_quantity']); ?>"
                                                        <?php echo $item['quantity'] >= max(1, $item['available_quantity']) ? 'disabled' : ''; ?>>
                                                    <i class="bi bi-plus"></i>
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Subtotal -->
                                        <div class="col-md-2 col-4 text-center">
                                            <p class="text-muted small mb-0">Tổng</p>
                                            <strong class="fs-5 item-total">
                                                <?php echo $item['price_type'] === 'free' ? 'Miễn phí' : formatCurrency($itemTotal); ?>
                                            </strong>
                                        </div>

                                        <!-- Remove -->
                                        <div class="col-12 col-md-12 mt-2">
                                            <div class="d-flex justify-content-end">
                                                <button class="btn btn-sm btn-outline-danger remove-item" 
                                                        data-cart-id="<?php echo $item['cart_id']; ?>"
                                                        data-item-name="<?php echo htmlspecialchars($item['item_name']); ?>">
                                                    <i class="bi bi-trash me-1"></i>Xóa
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Order Summary -->
                    <div class="col-lg-4">
                        <div class="card shadow-sm border-0 sticky-top" style="top: 100px;">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0">
                                    <i class="bi bi-receipt me-2"></i>Tóm tắt đơn hàng
                                </h5>
                            </div>
                            <div class="card-body">
                                <!-- Summary Details -->
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="text-muted">Tổng số vật phẩm:</span>
                                        <strong id="totalItemsDisplay"><?php echo $totalItems; ?></strong>
                                    </div>
                                    
                                    <?php if ($freeItemsCount > 0): ?>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-success">
                                                <i class="bi bi-gift me-1"></i>Miễn phí:
                                            </span>
                                            <span class="text-success"><?php echo $freeItemsCount; ?> món</span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($paidItemsCount > 0): ?>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-warning">
                                                <i class="bi bi-cash me-1"></i>Giá rẻ:
                                            </span>
                                            <span class="text-warning"><?php echo $paidItemsCount; ?> món</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <hr>
                                
                                <div class="d-flex justify-content-between mb-3">
                                    <span class="fs-5 fw-bold">Tổng tiền:</span>
                                    <strong class="fs-4 text-success" id="totalAmountDisplay">
                                        <?php echo formatCurrency($totalAmount); ?>
                                    </strong>
                                </div>
                                
                                <!-- Info Alert -->
                                <div class="alert alert-info small mb-3">
                                    <i class="bi bi-info-circle me-1"></i>
                                    <?php if ($freeItemsCount > 0 && $paidItemsCount === 0): ?>
                                        Tất cả vật phẩm đều miễn phí. Bạn chỉ cần thanh toán phí vận chuyển (nếu có).
                                    <?php elseif ($freeItemsCount > 0 && $paidItemsCount > 0): ?>
                                        Bạn có <?php echo $freeItemsCount; ?> món miễn phí và <?php echo $paidItemsCount; ?> món giá rẻ.
                                    <?php else: ?>
                                        Vật phẩm giá rẻ chỉ tính phí tối thiểu để duy trì hoạt động.
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Checkout Button -->
                                <div class="d-grid gap-2">
                                    <a href="checkout.php" class="btn btn-success btn-lg">
                                        <i class="bi bi-check-circle me-2"></i>Thanh toán
                                    </a>
                                    <button type="button" class="btn btn-outline-danger" id="clearCart">
                                        <i class="bi bi-trash me-2"></i>Xóa tất cả
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Delivery Info -->
                            <div class="card-footer bg-light">
                                <small class="text-muted">
                                    <i class="bi bi-truck me-1"></i>
                                    Giao hàng trong 2-3 ngày
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$additionalScripts = "
<script>
// Update quantity
document.querySelectorAll('.update-quantity').forEach(button => {
    button.addEventListener('click', function() {
        const cartId = this.dataset.cartId;
        const action = this.dataset.action;
        const maxQty = parseInt(this.dataset.max) || 999;
        const row = this.closest('.cart-item');
        const input = row.querySelector('.quantity-display');
        const currentQty = parseInt(input.value);
        
        // Check limits
        if (action === 'increase' && currentQty >= maxQty) {
            GoodwillVietnam.showAlert('Đã đạt số lượng tối đa có sẵn!', 'warning');
            return;
        }
        
        if (action === 'decrease' && currentQty <= 1) {
            GoodwillVietnam.showAlert('Số lượng tối thiểu là 1. Dùng nút Xóa để bỏ khỏi giỏ.', 'warning');
            return;
        }
        
        // Disable buttons
        const allBtns = row.querySelectorAll('.update-quantity');
        allBtns.forEach(btn => btn.disabled = true);
        
        fetch('api/update-cart.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ cart_id: cartId, action: action })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                GoodwillVietnam.showAlert(data.message || 'Có lỗi xảy ra!', 'error');
                allBtns.forEach(btn => btn.disabled = false);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            GoodwillVietnam.showAlert('Lỗi kết nối!', 'error');
            allBtns.forEach(btn => btn.disabled = false);
        });
    });
});

// Remove item
document.querySelectorAll('.remove-item').forEach(button => {
    button.addEventListener('click', function() {
        const itemName = this.dataset.itemName;
        
        if (!confirm('Bạn có chắc muốn xóa \"' + itemName + '\" khỏi giỏ hàng?')) {
            return;
        }
        
        const cartId = this.dataset.cartId;
        const btn = this;
        
        btn.disabled = true;
        btn.innerHTML = '<span class=\"spinner-border spinner-border-sm me-1\"></span>Đang xóa...';
        
        fetch('api/remove-from-cart.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ cart_id: cartId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Fade out and remove
                const cartItem = btn.closest('.cart-item');
                cartItem.style.opacity = '0';
                cartItem.style.transform = 'translateX(-20px)';
                
                setTimeout(() => {
                    cartItem.remove();
                    
                    // Reload if no items left
                    if (document.querySelectorAll('.cart-item').length === 0) {
                        location.reload();
                    }
                }, 300);
                
                GoodwillVietnam.showAlert('Đã xóa khỏi giỏ hàng', 'success');
            } else {
                GoodwillVietnam.showAlert(data.message || 'Có lỗi xảy ra!', 'error');
                btn.innerHTML = '<i class=\"bi bi-trash me-1\"></i>Xóa';
                btn.disabled = false;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            GoodwillVietnam.showAlert('Lỗi kết nối!', 'error');
            btn.innerHTML = '<i class=\"bi bi-trash me-1\"></i>Xóa';
            btn.disabled = false;
        });
    });
});

// Clear all cart
document.getElementById('clearCart').addEventListener('click', function() {
    if (!confirm('Bạn có chắc muốn xóa tất cả vật phẩm khỏi giỏ hàng?')) {
        return;
    }
    
    const btn = this;
    btn.disabled = true;
    btn.innerHTML = '<span class=\"spinner-border spinner-border-sm me-2\"></span>Đang xóa...';
    
    fetch('api/clear-cart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            GoodwillVietnam.showAlert(data.message || 'Có lỗi xảy ra!', 'error');
            btn.innerHTML = '<i class=\"bi bi-trash me-2\"></i>Xóa tất cả';
            btn.disabled = false;
        }
    });
});
</script>

<style>
.cart-item {
    transition: all 0.3s ease;
}

.cart-item:hover {
    transform: translateX(5px);
    box-shadow: 0 5px 20px rgba(0,0,0,0.1) !important;
}

.sticky-top {
    position: sticky;
}

@media (max-width: 768px) {
    .sticky-top {
        position: relative;
        top: 0 !important;
    }
}
</style>
";

include 'includes/footer.php';
?>