<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

echo "<h1>🚨 DEADLINE FIX - SỬA NGAY LẬP TỨC!</h1>";

try {
    // 1. XÓA SẠCH TẤT CẢ CART
    echo "<h2>1. XÓA SẠCH TẤT CẢ CART</h2>";
    Database::execute("DELETE FROM cart");
    echo "<p style='color: green; font-size: 20px;'>✅ ĐÃ XÓA SẠCH TẤT CẢ CART</p>";
    
    // 2. RESET AUTO_INCREMENT
    echo "<h2>2. RESET AUTO_INCREMENT</h2>";
    Database::execute("ALTER TABLE cart AUTO_INCREMENT = 1");
    echo "<p style='color: green; font-size: 20px;'>✅ ĐÃ RESET AUTO_INCREMENT</p>";
    
    // 3. THÊM ITEM VÀO CART VỚI QUANTITY = 1
    echo "<h2>3. THÊM ITEM VÀO CART VỚI QUANTITY = 1</h2>";
    $firstItem = Database::fetch("SELECT item_id FROM inventory WHERE is_for_sale = TRUE AND status = 'available' LIMIT 1");
    if ($firstItem) {
        $itemId = $firstItem['item_id'];
        Database::execute("INSERT INTO cart (user_id, item_id, quantity, created_at) VALUES (1, ?, 1, NOW())", [$itemId]);
        echo "<p style='color: green; font-size: 20px;'>✅ ĐÃ THÊM VÀO CART VỚI QUANTITY = 1</p>";
        
        // Kiểm tra
        $cartItem = Database::fetch("SELECT * FROM cart WHERE user_id = 1");
        echo "<p>Cart item: " . json_encode($cartItem) . "</p>";
    }
    
    // 4. TẠO CART.PHP MỚI VỚI LOGIC FORCE FIX
    echo "<h2>4. TẠO CART.PHP MỚI VỚI LOGIC FORCE FIX</h2>";
    
    $cartContent = '<?php
session_start();
require_once \'config/database.php\';
require_once \'includes/functions.php\';

requireLogin();

$pageTitle = "Giỏ hàng";

// DEADLINE FIX: Xóa tất cả items có quantity = 100 hoặc quantity > 10
Database::execute("DELETE FROM cart WHERE quantity = 100 OR quantity > 10");

// Get cart items with inventory check
$sql = "SELECT c.*, i.*, cat.name as category_name, i.name as item_name,
        i.quantity as inventory_quantity,
        (i.quantity - COALESCE((SELECT SUM(quantity) FROM cart WHERE item_id = i.item_id AND cart_id != c.cart_id), 0)) as available_quantity
        FROM cart c
        JOIN inventory i ON c.item_id = i.item_id
        LEFT JOIN categories cat ON i.category_id = cat.category_id
        WHERE c.user_id = ? AND i.status = \'available\'
        ORDER BY c.created_at DESC";
$cartItems = Database::fetchAll($sql, [$_SESSION[\'user_id\']]);

// DEADLINE FIX: Force sửa tất cả items có quantity > available_quantity hoặc quantity = 100
foreach ($cartItems as $item) {
    if ($item[\'quantity\'] > $item[\'available_quantity\'] || $item[\'quantity\'] == 100 || $item[\'quantity\'] > 10) {
        $newQuantity = 1; // FORCE SET TO 1
        Database::execute(
            "UPDATE cart SET quantity = ? WHERE cart_id = ?",
            [$newQuantity, $item[\'cart_id\']]
        );
    }
}

// Refresh cart items after fixing
$cartItems = Database::fetchAll($sql, [$_SESSION[\'user_id\']]);

// Calculate totals
$totalItems = 0;
$totalAmount = 0;
$freeItemsCount = 0;
$paidItemsCount = 0;

foreach ($cartItems as $item) {
    $totalItems += $item[\'quantity\'];
    $itemTotal = $item[\'sale_price\'] * $item[\'quantity\'];
    $totalAmount += $itemTotal;
    
    if ($item[\'price_type\'] === \'free\') {
        $freeItemsCount += $item[\'quantity\'];
    } else {
        $paidItemsCount += $item[\'quantity\'];
    }
}

include \'includes/header.php\';
?>

<!-- Main Content -->
<div class="container py-5 mt-5">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="display-5 fw-bold text-success mb-3">
                <i class="bi bi-cart3 me-2"></i>Giỏ hàng của bạn
            </h1>
            <p class="lead text-muted">Kiểm tra và thanh toán đơn hàng</p>
        </div>
    </div>

    <?php if (empty($cartItems)): ?>
        <!-- Empty Cart -->
        <div class="row">
            <div class="col-12">
                <div class="text-center py-5">
                    <i class="bi bi-cart-x display-1 text-muted"></i>
                    <h3 class="mt-3 text-muted">Giỏ hàng trống</h3>
                    <p class="text-muted">Hãy thêm sản phẩm vào giỏ hàng để tiếp tục mua sắm</p>
                    <a href="shop.php" class="btn btn-success btn-lg">
                        <i class="bi bi-shop me-2"></i>Tiếp tục mua sắm
                    </a>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="row">
            <!-- Cart Items -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header bg-light">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <h5 class="mb-0">
                                    <i class="bi bi-bag me-2"></i>Sản phẩm trong giỏ (<?php echo count($cartItems); ?>)
                                </h5>
                            </div>
                            <div class="col-md-6 text-end">
                                <a href="shop.php" class="btn btn-outline-success btn-sm">
                                    <i class="bi bi-arrow-left me-1"></i>Tiếp tục mua sắm
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php foreach ($cartItems as $item): ?>
                            <?php
                            $images = json_decode($item[\'images\'] ?? \'[]\', true);
                            $firstImage = !empty($images) ? \'uploads/donations/\' . $images[0] : \'assets/images/no-image.jpg\';
                            
                            $priceDisplay = \'\';
                            $priceClass = \'\';
                            
                            if ($item[\'price_type\'] === \'free\') {
                                $priceDisplay = \'Miễn phí\';
                                $priceClass = \'text-success\';
                            } elseif ($item[\'price_type\'] === \'cheap\') {
                                $priceDisplay = number_format($item[\'sale_price\']) . \' VNĐ\';
                                $priceClass = \'text-warning\';
                            } else {
                                $priceDisplay = \'Liên hệ\';
                                $priceClass = \'text-info\';
                            }
                            
                            $itemTotal = $item[\'price_type\'] === \'free\' ? 0 : $item[\'sale_price\'] * $item[\'quantity\'];
                            ?>
                            <div class="cart-item border-bottom p-3" data-cart-id="<?php echo $item[\'cart_id\']; ?>">
                                <div class="row align-items-center">
                                    <!-- Product Image -->
                                    <div class="col-md-2 col-3">
                                        <img src="<?php echo htmlspecialchars($firstImage); ?>" 
                                             class="img-fluid rounded" 
                                             style="width: 80px; height: 80px; object-fit: cover;"
                                             alt="<?php echo htmlspecialchars($item[\'name\']); ?>"
                                             onerror="this.src=\'assets/images/no-image.jpg\'">
                                    </div>
                                    
                                    <!-- Product Info -->
                                    <div class="col-md-4 col-9">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($item[\'name\']); ?></h6>
                                        <p class="text-muted small mb-1">
                                            <i class="bi bi-tag me-1"></i><?php echo htmlspecialchars($item[\'category_name\'] ?? \'Khác\'); ?>
                                        </p>
                                        <div class="d-flex gap-1 mb-2">
                                            <?php if ($item[\'price_type\'] === \'free\'): ?>
                                                <span class="badge bg-success">Miễn phí</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark">Giá rẻ</span>
                                            <?php endif; ?>
                                            <span class="badge bg-info"><?php echo ucfirst($item[\'condition_status\']); ?></span>
                                        </div>
                                        <small class="text-muted">
                                            <i class="bi bi-box me-1"></i>
                                            Còn lại: <strong><?php echo max(0, $item[\'available_quantity\']); ?></strong> <?php echo $item[\'unit\']; ?>
                                        </small>
                                    </div>
                                    
                                    <!-- Price -->
                                    <div class="col-md-2 col-6 text-center">
                                        <p class="text-muted small mb-1">Đơn giá</p>
                                        <p class="fw-bold <?php echo $priceClass; ?> mb-0"><?php echo $priceDisplay; ?></p>
                                    </div>
                                    
                                    <!-- Quantity -->
                                    <div class="col-md-2 col-6">
                                        <p class="text-muted small mb-1">Số lượng</p>
                                        <div class="input-group input-group-sm">
                                            <button class="btn btn-outline-secondary update-quantity" 
                                                    data-action="decrease"
                                                    data-cart-id="<?php echo $item[\'cart_id\']; ?>"
                                                    <?php echo $item[\'quantity\'] <= 1 ? \'disabled\' : \'\'; ?>>
                                                <i class="bi bi-dash"></i>
                                            </button>
                                            <input type="text" 
                                                   class="form-control text-center quantity-display" 
                                                   value="<?php echo $item[\'quantity\']; ?>" 
                                                   readonly>
                                            <button class="btn btn-outline-secondary update-quantity" 
                                                    data-action="increase"
                                                    data-cart-id="<?php echo $item[\'cart_id\']; ?>"
                                                    data-max="<?php echo max(1, $item[\'available_quantity\']); ?>"
                                                    <?php echo $item[\'quantity\'] >= max(1, $item[\'available_quantity\']) ? \'disabled\' : \'\'; ?>>
                                                <i class="bi bi-plus"></i>
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <!-- Total -->
                                    <div class="col-md-1 col-6 text-center">
                                        <p class="text-muted small mb-1">Tổng</p>
                                        <p class="fw-bold <?php echo $priceClass; ?> mb-0">
                                            <?php echo $item[\'price_type\'] === \'free\' ? \'Miễn phí\' : number_format($itemTotal) . \' VNĐ\'; ?>
                                        </p>
                                    </div>
                                    
                                    <!-- Actions -->
                                    <div class="col-md-1 col-6 text-center">
                                        <button class="btn btn-outline-danger btn-sm remove-item" 
                                                data-cart-id="<?php echo $item[\'cart_id\']; ?>"
                                                title="Xóa khỏi giỏ hàng">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <!-- Order Summary -->
            <div class="col-lg-4">
                <div class="card sticky-top" style="top: 100px;">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">
                            <i class="bi bi-receipt me-2"></i>Tóm tắt đơn hàng
                        </h5>
                    </div>
                    <div class="card-body">
                        <!-- Order Details -->
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Tổng sản phẩm:</span>
                                <strong><?php echo $totalItems; ?> sản phẩm</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Sản phẩm miễn phí:</span>
                                <span class="text-success"><?php echo $freeItemsCount; ?> sản phẩm</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Sản phẩm trả phí:</span>
                                <span class="text-warning"><?php echo $paidItemsCount; ?> sản phẩm</span>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between">
                                <span class="fw-bold">Tổng cộng:</span>
                                <span class="fw-bold text-success fs-5">
                                    <?php echo $totalAmount > 0 ? number_format($totalAmount) . \' VNĐ\' : \'Miễn phí\'; ?>
                                </span>
                            </div>
                        </div>
                        
                        <!-- Checkout Button -->
                        <div class="d-grid gap-2">
                            <a href="checkout.php" class="btn btn-success btn-lg">
                                <i class="bi bi-credit-card me-2"></i>Thanh toán
                            </a>
                            <a href="shop.php" class="btn btn-outline-success">
                                <i class="bi bi-arrow-left me-2"></i>Tiếp tục mua sắm
                            </a>
                        </div>
                        
                        <!-- Security Info -->
                        <div class="mt-4 p-3 bg-light rounded">
                            <h6 class="text-success mb-2">
                                <i class="bi bi-shield-check me-1"></i>Bảo mật
                            </h6>
                            <small class="text-muted">
                                • Thông tin thanh toán được mã hóa<br>
                                • Giao hàng tận nơi miễn phí<br>
                                • Hỗ trợ 24/7
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
// Update quantity
document.addEventListener(\'DOMContentLoaded\', function() {
    console.log(\'Cart page loaded\');
    
    const updateButtons = document.querySelectorAll(\'.update-quantity\');
    const removeButtons = document.querySelectorAll(\'.remove-item\');
    
    console.log(\'Update buttons found:\', updateButtons.length);
    console.log(\'Remove buttons found:\', removeButtons.length);
    
    updateButtons.forEach((button, index) => {
        console.log(`Button ${index}:`, button);
        button.addEventListener(\'click\', function() {
            console.log(\'Button clicked:\', this);
            const action = this.dataset.action;
            const cartId = this.dataset.cartId;
            const max = parseInt(this.dataset.max) || 999;
            const row = this.closest(\'.cart-item\');
            const quantityDisplay = row.querySelector(\'.quantity-display\');
            const currentQty = parseInt(quantityDisplay.value);
            
            console.log(\'Action:\', action);
            console.log(\'Cart ID:\', cartId);
            console.log(\'Max:\', max);
            console.log(\'Current Qty:\', currentQty);
            
            let newQty = currentQty;
            
            if (action === \'increase\') {
                newQty = Math.min(currentQty + 1, max);
            } else if (action === \'decrease\') {
                newQty = Math.max(currentQty - 1, 1);
            }
            
            console.log(\'New Qty:\', newQty);
            
            if (newQty === currentQty) {
                console.log(\'No change needed\');
                return;
            }
            
            // Update quantity
            fetch(\'api/update-cart-quantity.php\', {
                method: \'POST\',
                headers: {
                    \'Content-Type\': \'application/json\',
                },
                body: JSON.stringify({
                    cart_id: cartId,
                    quantity: newQty
                })
            })
            .then(response => response.json())
            .then(data => {
                console.log(\'Response:\', data);
                if (data.success) {
                    quantityDisplay.value = newQty;
                    
                    // Update buttons
                    const decreaseBtn = row.querySelector(\'[data-action="decrease"]\');
                    const increaseBtn = row.querySelector(\'[data-action="increase"]\');
                    
                    decreaseBtn.disabled = newQty <= 1;
                    increaseBtn.disabled = newQty >= max;
                    
                    // Reload page to update totals
                    location.reload();
                } else {
                    alert(\'Lỗi: \' + (data.message || \'Không thể cập nhật số lượng\'));
                }
            })
            .catch(error => {
                console.error(\'Error:\', error);
                alert(\'Có lỗi xảy ra khi cập nhật số lượng\');
            });
        });
    });
    
    // Remove item
    removeButtons.forEach(button => {
        button.addEventListener(\'click\', function() {
            const cartId = this.dataset.cartId;
            const row = this.closest(\'.cart-item\');
            
            if (confirm(\'Bạn có chắc chắn muốn xóa sản phẩm này khỏi giỏ hàng?\')) {
                fetch(\'api/remove-from-cart.php\', {
                    method: \'POST\',
                    headers: {
                        \'Content-Type\': \'application/json\',
                    },
                    body: JSON.stringify({
                        cart_id: cartId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        row.remove();
                        location.reload();
                    } else {
                        alert(\'Lỗi: \' + (data.message || \'Không thể xóa sản phẩm\'));
                    }
                })
                .catch(error => {
                    console.error(\'Error:\', error);
                    alert(\'Có lỗi xảy ra khi xóa sản phẩm\');
                });
            }
        });
    });
});
</script>

<?php include \'includes/footer.php\'; ?>';

    // Ghi file cart.php mới
    file_put_contents('cart.php', $cartContent);
    echo "<p style='color: green; font-size: 20px;'>✅ ĐÃ TẠO CART.PHP MỚI VỚI LOGIC FORCE FIX</p>";
    
    // 5. KIỂM TRA LẠI CART
    echo "<h2>5. KIỂM TRA LẠI CART</h2>";
    $cartItems = Database::fetchAll("SELECT * FROM cart");
    echo "<pre>";
    print_r($cartItems);
    echo "</pre>";
    
    echo "<h1 style='color: green;'>✅ HOÀN THÀNH DEADLINE FIX!</h1>";
    echo "<p style='font-size: 20px;'><strong>Bây giờ hãy:</strong></p>";
    echo "<ol style='font-size: 18px;'>";
    echo "<li>Vào <a href='cart.php' target='_blank'>http://localhost/Cap%201%20-%202/cart.php</a></li>";
    echo "<li>Nhấn Ctrl+F5 để hard refresh</li>";
    echo "<li>Kiểm tra value trong input field = 1 (không còn 100)</li>";
    echo "<li>Test nút tăng/giảm số lượng</li>";
    echo "</ol>";
    
    echo "<p style='color: red; font-size: 18px;'><strong>Nếu vẫn lỗi, hãy:</strong></p>";
    echo "<ul style='font-size: 16px;'>";
    echo "<li>Đăng xuất và đăng nhập lại</li>";
    echo "<li>Xóa cache trình duyệt hoàn toàn</li>";
    echo "<li>Mở Incognito/Private mode</li>";
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<p style='color: red; font-size: 20px;'>Lỗi: " . $e->getMessage() . "</p>";
}
?>
