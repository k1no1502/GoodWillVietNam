<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

requireLogin();

$pageTitle = "Thanh toán";
$hasOrderHistoryTable = !empty(Database::fetchAll("SHOW TABLES LIKE 'order_status_history'"));
$googleConfigPath = __DIR__ . '/config/google.php';
$googleConfig = file_exists($googleConfigPath) ? require $googleConfigPath : [];
$googleMapsKey = trim((string)($googleConfig['maps_api_key'] ?? ''));

$success = '';
$error = '';

// Get user info
$user = getUserById($_SESSION['user_id']);
$shipping_name = $user['name'] ?? '';
$shipping_phone = $user['phone'] ?? '';
$shipping_address = $user['address'] ?? '';
$shipping_note = '';
$payment_method = 'cod';

// Get cart items with explicit columns (avoid quantity/name collisions)
$sql = "SELECT 
            c.cart_id,
            c.user_id,
            c.item_id,
            c.quantity AS cart_quantity,
            c.created_at AS cart_created_at,
            i.name AS item_name,
            i.description,
            i.category_id,
            i.quantity AS inventory_quantity,
            i.condition_status,
            i.price_type,
            i.sale_price,
            i.unit,
            i.images,
            i.status AS inventory_status,
            cat.name as category_name
        FROM cart c
        JOIN inventory i ON c.item_id = i.item_id
        LEFT JOIN categories cat ON i.category_id = cat.category_id
        WHERE c.user_id = ? AND i.status = 'available'
        ORDER BY c.created_at DESC";
$cartItems = Database::fetchAll($sql, [$_SESSION['user_id']]);

if (empty($cartItems)) {
    header('Location: cart.php');
    exit();
}

// Calculate totals
$totalItems = 0;
$totalAmount = 0;
$freeItemsCount = 0;
$paidItemsCount = 0;

foreach ($cartItems as $item) {
    $qty = (int)$item['cart_quantity'];
    $totalItems += $qty;

    $unitPrice = ($item['price_type'] === 'free') ? 0 : (float)$item['sale_price'];
    $itemTotal = $unitPrice * $qty;
    $totalAmount += $itemTotal;
    
    if ($item['price_type'] === 'free') {
        $freeItemsCount += $qty;
    } else {
        $paidItemsCount += $qty;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $shipping_name = sanitize($_POST['shipping_name'] ?? $shipping_name);
    $shipping_phone = sanitize($_POST['shipping_phone'] ?? $shipping_phone);
    $shipping_city = sanitize($_POST['shipping_city'] ?? '');
    $shipping_district = sanitize($_POST['shipping_district'] ?? '');
    $shipping_ward = sanitize($_POST['shipping_ward'] ?? '');
    $shipping_address = sanitize($_POST['shipping_address'] ?? $shipping_address);
    $shipping_note = sanitize($_POST['shipping_note'] ?? '');
    $payment_method = sanitize($_POST['payment_method'] ?? $payment_method);

    $shipping_place_id = trim((string)($_POST['shipping_place_id'] ?? ''));
    $shipping_lat = $_POST['shipping_lat'] ?? null;
    $shipping_lng = $_POST['shipping_lng'] ?? null;
    $shipping_lat = ($shipping_lat === '' || $shipping_lat === null) ? null : (float)$shipping_lat;
    $shipping_lng = ($shipping_lng === '' || $shipping_lng === null) ? null : (float)$shipping_lng;
    
    // Validation
    if (empty($shipping_name)) {
        $error = 'Vui lòng nhập họ tên người nhận.';
    } elseif (empty($shipping_phone)) {
        $error = 'Vui lòng nhập số điện thoại.';
    } elseif (empty($shipping_city) || empty($shipping_district) || empty($shipping_ward)) {
        $error = 'Vui lòng chọn Thành phố, Quận/Huyện và Phường/Xã.';
    } elseif (empty($shipping_address)) {
        $error = 'Vui lòng nhập địa chỉ giao hàng.';
    } elseif (empty($payment_method)) {
        $error = 'Vui lòng chọn phương thức thanh toán.';
    } elseif ($googleMapsKey !== '' && ($shipping_place_id === '' || $shipping_lat === null || $shipping_lng === null)) {
        $error = 'Vui lòng chọn địa chỉ từ gợi ý Google để định vị chính xác.';
    } else {
        try {
            Database::beginTransaction();

            $shipping_address_full = trim(implode(', ', array_filter([
                $shipping_address,
                $shipping_ward,
                $shipping_district,
                $shipping_city,
            ])));

            // Kiểm tra schema bảng orders (hỗ trợ cả 2 kiểu: update_schema & orders_system)
            $hasShippingName = !empty(Database::fetchAll("SHOW COLUMNS FROM orders LIKE 'shipping_name'"));
            $statusColumn = Database::fetch("SHOW COLUMNS FROM orders LIKE 'status'");
            $allowedStatuses = [];
            if (!empty($statusColumn['Type']) && strpos($statusColumn['Type'], "enum(") === 0) {
                preg_match_all("/'([^']+)'/", $statusColumn['Type'], $matches);
                $allowedStatuses = $matches[1] ?? [];
            }
            $orderStatus = in_array('pending', $allowedStatuses, true) ? 'pending' : ($allowedStatuses[0] ?? 'pending');
            $legacyPaymentMethod = $payment_method === 'cod' ? 'cash' : $payment_method;
            $allowedLegacyMethods = ['cash', 'bank_transfer', 'credit_card', 'free'];
            if (!in_array($legacyPaymentMethod, $allowedLegacyMethods, true)) {
                $legacyPaymentMethod = 'cash';
            }

            if ($hasShippingName) {
                // Schema mới: có shipping_name, shipping_note (orders_system.sql)
                $hasShippingGeo = !empty(Database::fetchAll("SHOW COLUMNS FROM orders LIKE 'shipping_lat'"));
                if ($hasShippingGeo) {
                    Database::execute(
                        "INSERT INTO orders (user_id, shipping_name, shipping_phone, shipping_address,
                                             shipping_place_id, shipping_lat, shipping_lng,
                                             shipping_note, payment_method, total_amount, status, created_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                        [
                            $_SESSION['user_id'],
                            $shipping_name,
                            $shipping_phone,
                            $shipping_address_full,
                            $shipping_place_id !== '' ? $shipping_place_id : null,
                            $shipping_lat,
                            $shipping_lng,
                            $shipping_note,
                            $payment_method,
                            $totalAmount,
                            $orderStatus
                        ]
                    );
                } else {
                    Database::execute(
                        "INSERT INTO orders (user_id, shipping_name, shipping_phone, shipping_address,
                                             shipping_note, payment_method, total_amount, status, created_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                        [
                            $_SESSION['user_id'],
                            $shipping_name,
                            $shipping_phone,
                            $shipping_address_full,
                            $shipping_note,
                            $payment_method,
                            $totalAmount,
                            $orderStatus
                        ]
                    );
                }
            } else {
                // Schema cũ trong update_schema.sql: dùng order_number, total_items, notes...
                $order_number = 'ORD-' . date('Ymd-His') . '-' . $_SESSION['user_id'];
                Database::execute(
                    "INSERT INTO orders (
                        order_number, user_id, total_amount, total_items, status, 
                        payment_method, shipping_address, shipping_phone, notes, created_at
                     ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                    [
                        $order_number,
                        $_SESSION['user_id'],
                        $totalAmount,
                        $totalItems,
                        $orderStatus,
                        $legacyPaymentMethod,
                        $shipping_address_full,
                        $shipping_phone,
                        $shipping_note
                    ]
                );
            }
            
            $order_id = Database::lastInsertId();
            
            // Kiểm tra schema bảng order_items
            $hasUnitPrice = !empty(Database::fetchAll("SHOW COLUMNS FROM order_items LIKE 'unit_price'"));

            // Create order items
            foreach ($cartItems as $item) {
                $qty       = (int)$item['cart_quantity'];
                $unitPrice = ($item['price_type'] === 'free') ? 0 : (float)$item['sale_price'];
                $itemTotal = $unitPrice * $qty;
                
                if ($hasUnitPrice) {
                    // Schema mới: unit_price + total_price
                    Database::execute(
                        "INSERT INTO order_items (order_id, item_id, item_name, quantity, unit_price, total_price, created_at) 
                         VALUES (?, ?, ?, ?, ?, ?, NOW())",
                        [
                            $order_id,
                            $item['item_id'],
                            $item['item_name'],
                            $qty,
                            $unitPrice,
                            $itemTotal
                        ]
                    );
                } else {
                    // Schema cũ: price, price_type, subtotal
                    Database::execute(
                        "INSERT INTO order_items (order_id, item_id, item_name, quantity, price, price_type, subtotal, created_at) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
                        [
                            $order_id,
                            $item['item_id'],
                            $item['item_name'],
                            $qty,
                            $unitPrice,
                            $item['price_type'],
                            $itemTotal
                        ]
                    );
                }
                
                // Update inventory (guard against oversell)
                $updateInventoryStmt = Database::execute(
                    "UPDATE inventory
                     SET quantity = quantity - ?
                     WHERE item_id = ? AND status = 'available' AND quantity >= ?",
                    [$qty, $item['item_id'], $qty]
                );
                if ($updateInventoryStmt->rowCount() !== 1) {
                    throw new Exception('So luong ton kho khong du de hoan tat don hang cho item #' . $item['item_id'] . '.');
                }
            }
            
            // Clear cart
            Database::execute("DELETE FROM cart WHERE user_id = ?", [$_SESSION['user_id']]);
            
            // Log activity
            logActivity($_SESSION['user_id'], 'create_order', "Created order #$order_id");

            // Save order history entry (pending) nếu có bảng
            if ($hasOrderHistoryTable) {
                Database::execute(
                    "INSERT INTO order_status_history (order_id, old_status, new_status, note, created_at)
                     VALUES (?, 'pending', 'pending', 'Tạo đơn hàng mới', NOW())",
                    [$order_id]
                );
            }
            
            Database::commit();
            
            // Redirect to success page
            header("Location: order-success.php?order_id=$order_id");
            exit();
            
        } catch (Exception $e) {
            Database::rollback();
            error_log("Checkout error: " . $e->getMessage());
            $error = 'Có lỗi xảy ra khi tạo đơn hàng. Vui lòng thử lại.';
        }
    }
}

include 'includes/header.php';
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>

<!-- Main Content -->
<div class="container py-5 mt-5">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="display-5 fw-bold text-success mb-3">
                <i class="bi bi-credit-card me-2"></i>Thanh toán
            </h1>
            <p class="lead text-muted">Hoan tat don hang của bạn</p>
        </div>
    </div>

    <div class="row">
        <!-- Checkout Form -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">
                        <i class="bi bi-person-lines-fill me-2"></i>Thông tin giao hàng
                    </h5>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="needs-validation" novalidate>
                        <!-- Shipping Information -->
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="shipping_name" class="form-label">Họ tên người nhận *</label>
                                <input type="text" 
                                       class="form-control" 
                                       id="shipping_name" 
                                       name="shipping_name" 
                                       value="<?php echo htmlspecialchars($shipping_name ?: $user['name']); ?>"
                                       required>
                                <div class="invalid-feedback">
                                    Vui lòng nhập họ tên người nhận.
                                </div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="shipping_phone" class="form-label">Số điện thoại *</label>
                                <input type="tel" 
                                       class="form-control" 
                                       id="shipping_phone" 
                                       name="shipping_phone" 
                                       value="<?php echo htmlspecialchars($shipping_phone ?: $user['phone']); ?>"
                                       required>
                                <div class="invalid-feedback">
                                    Vui lòng nhập số điện thoại.
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Thành phố *</label>
                                    <select class="form-select" id="shipping_city" name="shipping_city" required data-selected="<?php echo htmlspecialchars($_POST['shipping_city'] ?? ''); ?>">
                                        <option value="">-- Chọn Thành phố --</option>
                                    </select>
                                    <div class="invalid-feedback">Vui lòng chọn Thành phố</div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Quận/Huyện *</label>
                                    <select class="form-select" id="shipping_district" name="shipping_district" required data-selected="<?php echo htmlspecialchars($_POST['shipping_district'] ?? ''); ?>" disabled>
                                        <option value="">-- Chọn Quận/Huyện --</option>
                                    </select>
                                    <div class="invalid-feedback">Vui lòng chọn Quận/Huyện</div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Phường/Xã *</label>
                                    <select class="form-select" id="shipping_ward" name="shipping_ward" required data-selected="<?php echo htmlspecialchars($_POST['shipping_ward'] ?? ''); ?>" disabled>
                                        <option value="">-- Chọn Phường/Xã --</option>
                                    </select>
                                    <div class="invalid-feedback">Vui lòng chọn Phường/Xã</div>
                                </div>
                            </div>
                            <label for="shipping_address" class="form-label">Địa chỉ giao hàng *</label>
                            <input type="hidden" id="shipping_place_id" name="shipping_place_id" value="<?php echo htmlspecialchars($_POST['shipping_place_id'] ?? ''); ?>">
                            <input type="hidden" id="shipping_lat" name="shipping_lat" value="<?php echo htmlspecialchars($_POST['shipping_lat'] ?? ''); ?>">
                            <input type="hidden" id="shipping_lng" name="shipping_lng" value="<?php echo htmlspecialchars($_POST['shipping_lng'] ?? ''); ?>">
                            <textarea class="form-control" 
                                      id="shipping_address" 
                                      name="shipping_address" 
                                      rows="3" 
                                      placeholder="Nhập địa chỉ chi tiết (số nhà, tên đường, phường/xã, quận/huyện, tỉnh/thành phố)"
                                      required><?php echo htmlspecialchars($shipping_address ?: $user['address']); ?></textarea>
                            <?php if ($googleMapsKey !== ''): ?>
                                <div class="form-text">Gợi ý: gõ và chọn địa chỉ từ danh sách Google để map định vị đúng.</div>
                            <?php endif; ?>
                            <?php if ($googleMapsKey === ''): ?>
                                <div class="form-text">Free: nhập địa chỉ và điều chỉnh ghim trên bản đồ để đúng vị trí (lưu theo tọa độ).</div>
                            <?php endif; ?>
                            <div class="invalid-feedback">
                                Vui lòng nhập địa chỉ giao hàng.
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="shipping_note" class="form-label">Ghi chú giao hàng</label>
                            <textarea class="form-control" 
                                      id="shipping_note" 
                                      name="shipping_note" 
                                      rows="2" 
                                      placeholder="Ghi chú thêm cho đơn hàng (tùy chọn)"><?php echo htmlspecialchars($shipping_note); ?></textarea>
                        </div>

                        <!-- Payment Method -->
                        <div class="mb-4">
                            <label class="form-label">Phương thức thanh toán *</label>
                            <div class="row">
                                <div class="col-md-6 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" 
                                               type="radio" 
                                               name="payment_method" 
                                               id="cod" 
                                               value="cod"
                                               <?php echo ($payment_method === 'cod' || empty($payment_method)) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="cod">
                                            <i class="bi bi-cash-coin me-2"></i>Thanh toán khi nhận hàng (COD)
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" 
                                               type="radio" 
                                               name="payment_method" 
                                               id="bank_transfer" 
                                               value="bank_transfer"
                                               <?php echo $payment_method === 'bank_transfer' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="bank_transfer">
                                            <i class="bi bi-bank me-2"></i>Chuyển khoản ngân hàng
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="d-grid">
                            <button id="submitOrderBtn" type="submit" class="btn btn-success btn-lg">
                                <i class="bi bi-check-circle me-2"></i>Hoan tat don hang
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Order Summary -->
        <div class="col-lg-4">
            <div class="card sticky-top" style="top: 100px;">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <i class="bi bi-receipt me-2"></i>Tóm tắt đơn hàng
                    </h5>
                </div>
                <div class="card-body">
                    <!-- Order Items -->
                    <div class="mb-3">
                        <?php foreach ($cartItems as $item): ?>
                            <?php
                            $images = json_decode($item['images'] ?? '[]', true);
                            $firstImage = !empty($images) ? 'uploads/donations/' . $images[0] : 'uploads/donations/placeholder-default.svg';
                            $itemTotal = $item['price_type'] === 'free' ? 0 : $item['sale_price'] * $item['cart_quantity'];
                            ?>
                            <div class="d-flex align-items-center mb-2">
                                <img src="<?php echo htmlspecialchars($firstImage); ?>" 
                                     class="rounded me-2" 
                                     style="width: 40px; height: 40px; object-fit: cover;"
                                     alt="<?php echo htmlspecialchars($item['item_name']); ?>"
                                     onerror="this.src='uploads/donations/placeholder-default.svg'">
                                <div class="flex-grow-1">
                                    <h6 class="mb-0 small"><?php echo htmlspecialchars(substr($item['item_name'], 0, 30)); ?></h6>
                                    <small class="text-muted">x<?php echo $item['cart_quantity']; ?></small>
                                </div>
                                <div class="text-end">
                                    <small class="fw-bold">
                                        <?php echo $item['price_type'] === 'free' ? 'Miễn phí' : number_format($itemTotal) . ' VNĐ'; ?>
                                    </small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <hr>

                    <!-- Order Totals -->
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
                        <div class="d-flex justify-content-between mb-2">
                            <span>Phí vận chuyển:</span>
                            <span class="text-success">Miễn phí</span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between">
                            <span class="fw-bold">Tổng cộng:</span>
                            <span class="fw-bold text-success fs-5">
                                <?php echo $totalAmount > 0 ? number_format($totalAmount) . ' VNĐ' : 'Miễn phí'; ?>
                            </span>
                        </div>
                    </div>

                    <!-- Security Info -->
                    <div class="p-3 bg-light rounded">
                        <h6 class="text-success mb-2">
                            <i class="bi bi-shield-check me-1"></i>Cam kết
                        </h6>
                        <small class="text-muted">
                            • Giao hàng tận nơi miễn phí<br>
                            • Kiểm tra hàng trước khi thanh toán<br>
                            • Hỗ trợ đổi trả trong 7 ngày<br>
                            • Bảo mật thông tin khách hàng
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Form validation
(function() {
    'use strict';
    window.addEventListener('load', function() {
        var forms = document.getElementsByClassName('needs-validation');
        var validation = Array.prototype.filter.call(forms, function(form) {
            form.addEventListener('submit', function(event) {
                if (form.checkValidity() === false) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    }, false);
})();

// Toggle submit button label based on payment method
(function() {
    const submitBtn = document.getElementById('submitOrderBtn');
    const radios = document.querySelectorAll('input[name=\"payment_method\"]');
    const updateLabel = () => {
        if (!submitBtn) return;
        const bankSelected = document.getElementById('bank_transfer')?.checked;
        submitBtn.innerHTML = bankSelected
            ? '<i class="bi bi-check-circle me-2"></i>Hoan tat thanh toan'
            : '<i class="bi bi-check-circle me-2"></i>Hoan tat don hang';
    };
    radios.forEach(r => r.addEventListener('change', updateLabel));
    updateLabel();
})();

// Vietnamese address selects (City/District/Ward) via local JSON API
(function () {
    const cityEl = document.getElementById('shipping_city');
    const districtEl = document.getElementById('shipping_district');
    const wardEl = document.getElementById('shipping_ward');
    if (!cityEl || !districtEl || !wardEl) return;

    const API_BASE = 'api/vn-address.php';

    const clearSelect = (el, placeholder) => {
        el.innerHTML = '';
        const opt = document.createElement('option');
        opt.value = '';
        opt.textContent = placeholder;
        el.appendChild(opt);
        el.value = '';
    };

    const setSelectedByValue = (el, value) => {
        if (!value) return false;
        const options = Array.from(el.options);
        const found = options.find(o => (o.value || '').trim() === value.trim());
        if (found) {
            el.value = found.value;
            return true;
        }
        return false;
    };

    const populate = (el, items, placeholder) => {
        clearSelect(el, placeholder);
        for (const item of items) {
            const opt = document.createElement('option');
            opt.value = item.name;
            opt.textContent = item.name;
            opt.dataset.code = String(item.code);
            el.appendChild(opt);
        }
    };

    const fetchJson = async (url) => {
        const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        return res.json();
    };

    const loadCities = async () => {
        const provinces = await fetchJson(`${API_BASE}?type=provinces`);
        populate(cityEl, provinces, '-- Chọn Thành phố --');
        cityEl.disabled = false;
    };

    const loadDistricts = async (provinceCode) => {
        const districts = await fetchJson(`${API_BASE}?type=districts&province_code=${encodeURIComponent(provinceCode)}`);
        populate(districtEl, districts, '-- Chọn Quận/Huyện --');
        districtEl.disabled = false;
    };

    const loadWards = async (districtCode) => {
        const wards = await fetchJson(`${API_BASE}?type=wards&district_code=${encodeURIComponent(districtCode)}`);
        populate(wardEl, wards, '-- Chọn Phường/Xã --');
        wardEl.disabled = false;
    };

    const getSelectedCode = (el) => {
        const opt = el.options[el.selectedIndex];
        return opt ? (opt.dataset.code || '') : '';
    };

    const init = async () => {
        clearSelect(districtEl, '-- Chọn Quận/Huyện --');
        clearSelect(wardEl, '-- Chọn Phường/Xã --');
        districtEl.disabled = true;
        wardEl.disabled = true;

        try {
            await loadCities();
        } catch (e) {
            console.error('Failed to load provinces:', e);
            cityEl.disabled = false;
            return;
        }

        const selectedCity = cityEl.dataset.selected || '';
        const selectedDistrict = districtEl.dataset.selected || '';
        const selectedWard = wardEl.dataset.selected || '';

        if (setSelectedByValue(cityEl, selectedCity)) {
            const pCode = getSelectedCode(cityEl);
            if (pCode) {
                try {
                    await loadDistricts(pCode);
                    if (setSelectedByValue(districtEl, selectedDistrict)) {
                        const dCode = getSelectedCode(districtEl);
                        if (dCode) {
                            await loadWards(dCode);
                            setSelectedByValue(wardEl, selectedWard);
                        }
                    }
                } catch (e) {
                    console.error('Failed to restore address selects:', e);
                }
            }
        }
    };

    cityEl.addEventListener('change', async () => {
        clearSelect(districtEl, '-- Chọn Quận/Huyện --');
        clearSelect(wardEl, '-- Chọn Phường/Xã --');
        districtEl.disabled = true;
        wardEl.disabled = true;

        const provinceCode = getSelectedCode(cityEl);
        if (!provinceCode) return;

        try {
            await loadDistricts(provinceCode);
        } catch (e) {
            console.error('Failed to load districts:', e);
        }
    });

    districtEl.addEventListener('change', async () => {
        clearSelect(wardEl, '-- Chọn Phường/Xã --');
        wardEl.disabled = true;

        const districtCode = getSelectedCode(districtEl);
        if (!districtCode) return;

        try {
            await loadWards(districtCode);
        } catch (e) {
            console.error('Failed to load wards:', e);
        }
    });

    init();
})();

// Google Places Autocomplete (accurate VN address + lat/lng)
(function () {
    const apiKey = <?php echo json_encode($googleMapsKey); ?>;
    if (!apiKey) return;

    const addressEl = document.getElementById('shipping_address');
    const placeIdEl = document.getElementById('shipping_place_id');
    const latEl = document.getElementById('shipping_lat');
    const lngEl = document.getElementById('shipping_lng');
    const cityEl = document.getElementById('shipping_city');
    const districtEl = document.getElementById('shipping_district');
    const wardEl = document.getElementById('shipping_ward');
    const mapEl = document.getElementById('shippingMap');
    if (!addressEl || !placeIdEl || !latEl || !lngEl) return;

    let gMap = null;
    let gMarker = null;

    const updateMap = (lat, lng) => {
        if (!mapEl || !window.google || !google.maps) return;
        if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;
        const pos = { lat, lng };
        if (!gMap) {
            gMap = new google.maps.Map(mapEl, {
                center: pos,
                zoom: 17,
                mapTypeControl: false,
                streetViewControl: false,
                fullscreenControl: false,
            });
            gMarker = new google.maps.Marker({ position: pos, map: gMap });
        } else {
            gMap.setCenter(pos);
            if (gMarker) gMarker.setPosition(pos);
        }
    };

    let lastCommitted = addressEl.value || '';
    addressEl.addEventListener('input', () => {
        if ((addressEl.value || '') !== lastCommitted) {
            placeIdEl.value = '';
            latEl.value = '';
            lngEl.value = '';
        }
    });

    const normalize = (s) => (s || '')
        .toString()
        .trim()
        .toLowerCase()
        .replace(/^thành phố\\s+/i, '')
        .replace(/^tỉnh\\s+/i, '')
        .replace(/^quận\\s+/i, '')
        .replace(/^huyện\\s+/i, '')
        .replace(/^thị xã\\s+/i, '')
        .replace(/^phường\\s+/i, '')
        .replace(/^xã\\s+/i, '');

    const selectByName = (selectEl, name) => {
        if (!selectEl || !name) return false;
        const want = normalize(name);
        const opts = Array.from(selectEl.options || []);
        const found = opts.find(o => normalize(o.value || o.textContent) === want);
        if (found) {
            selectEl.value = found.value;
            selectEl.dispatchEvent(new Event('change', { bubbles: true }));
            return true;
        }
        return false;
    };

    const parseComponents = (place) => {
        const comps = place && Array.isArray(place.address_components) ? place.address_components : [];
        const get = (type) => {
            const c = comps.find(x => Array.isArray(x.types) && x.types.includes(type));
            return c ? (c.long_name || c.short_name || '') : '';
        };
        const streetNumber = get('street_number');
        const route = get('route');
        const ward = get('administrative_area_level_3') || get('sublocality_level_1') || get('sublocality') || get('neighborhood');
        const district = get('administrative_area_level_2');
        const city = get('administrative_area_level_1');
        const detail = [streetNumber, route].filter(Boolean).join(' ').trim();
        return { detail, ward, district, city };
    };

    window.__initGWPlaces = function () {
        if (!window.google || !google.maps || !google.maps.places) return;

        // Restore preview on reload (e.g. validation errors)
        const existingLat = parseFloat(latEl.value || '');
        const existingLng = parseFloat(lngEl.value || '');
        if (Number.isFinite(existingLat) && Number.isFinite(existingLng)) {
            updateMap(existingLat, existingLng);
        }

        const ac = new google.maps.places.Autocomplete(addressEl, {
            fields: ['place_id', 'geometry', 'address_components', 'formatted_address'],
            componentRestrictions: { country: ['vn'] },
            types: ['address'],
        });

        ac.addListener('place_changed', () => {
            const place = ac.getPlace();
            if (!place || !place.place_id || !place.geometry || !place.geometry.location) return;

            placeIdEl.value = place.place_id;
            const lat = place.geometry.location.lat();
            const lng = place.geometry.location.lng();
            latEl.value = lat;
            lngEl.value = lng;
            updateMap(lat, lng);

            const c = parseComponents(place);
            if (c.detail) {
                addressEl.value = c.detail;
                lastCommitted = c.detail;
            } else if (place.formatted_address) {
                addressEl.value = place.formatted_address;
                lastCommitted = place.formatted_address;
            }

            // Best-effort auto select (name match)
            if (cityEl && districtEl && wardEl) {
                const waitFor = (el, ms) => new Promise(resolve => {
                    const start = Date.now();
                    const t = setInterval(() => {
                        if ((el.options && el.options.length > 1) || (Date.now() - start) > ms) {
                            clearInterval(t);
                            resolve();
                        }
                    }, 100);
                });

                (async () => {
                    await waitFor(cityEl, 4000);
                    selectByName(cityEl, c.city);
                    await waitFor(districtEl, 4000);
                    selectByName(districtEl, c.district);
                    await waitFor(wardEl, 4000);
                    selectByName(wardEl, c.ward);
                })();
            }
        });
    };

    const scriptId = 'gw-google-places';
    if (!document.getElementById(scriptId)) {
        const s = document.createElement('script');
        s.id = scriptId;
        s.async = true;
        s.defer = true;
        s.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(apiKey)}&libraries=places&callback=__initGWPlaces`;
        document.head.appendChild(s);
    }
})();

// Free fallback: Leaflet map + manual pin (stores lat/lng). Uses Nominatim only as a helper (user can drag to correct).
(function () {
    const apiKey = <?php echo json_encode($googleMapsKey); ?>;
    if (apiKey) return; // Google mode already handles map

    const mapEl = document.getElementById('shippingMap');
    const addressEl = document.getElementById('shipping_address');
    const latEl = document.getElementById('shipping_lat');
    const lngEl = document.getElementById('shipping_lng');
    const wardEl = document.getElementById('shipping_ward');
    const districtEl = document.getElementById('shipping_district');
    const cityEl = document.getElementById('shipping_city');
    if (!mapEl || !latEl || !lngEl) return;

    const existingLat = parseFloat(latEl.value || '');
    const existingLng = parseFloat(lngEl.value || '');
    const start = (Number.isFinite(existingLat) && Number.isFinite(existingLng))
        ? [existingLat, existingLng]
        : [16.047079, 108.206230]; // Đà Nẵng fallback

    const map = L.map(mapEl, { zoomControl: true }).setView(start, 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    const marker = L.marker(start, { draggable: true }).addTo(map);

    const setLatLng = (lat, lng) => {
        latEl.value = String(lat.toFixed(6));
        lngEl.value = String(lng.toFixed(6));
    };
    setLatLng(start[0], start[1]);

    marker.on('dragend', () => {
        const p = marker.getLatLng();
        setLatLng(p.lat, p.lng);
    });
    map.on('click', (e) => {
        marker.setLatLng(e.latlng);
        setLatLng(e.latlng.lat, e.latlng.lng);
    });

    const buildQuery = () => {
        const parts = [
            (addressEl?.value || '').trim(),
            (wardEl?.value || '').trim(),
            (districtEl?.value || '').trim(),
            (cityEl?.value || '').trim(),
            'Vietnam'
        ].filter(Boolean);
        return parts.join(', ');
    };

    let geocodeTimer = null;
    const geocode = async () => {
        const q = buildQuery();
        if (!q) return;
        try {
            const url = 'https://nominatim.openstreetmap.org/search?format=json&limit=1&countrycodes=vn&q=' + encodeURIComponent(q);
            const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
            if (!res.ok) return;
            const data = await res.json();
            const first = Array.isArray(data) ? data[0] : null;
            if (!first || !first.lat || !first.lon) return;
            const lat = parseFloat(first.lat);
            const lng = parseFloat(first.lon);
            if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;
            marker.setLatLng([lat, lng]);
            map.setView([lat, lng], 16);
            setLatLng(lat, lng);
        } catch (e) {}
    };

    const scheduleGeocode = () => {
        if (geocodeTimer) clearTimeout(geocodeTimer);
        geocodeTimer = setTimeout(geocode, 600);
    };

    addressEl?.addEventListener('blur', geocode);
    wardEl?.addEventListener('change', scheduleGeocode);
    districtEl?.addEventListener('change', scheduleGeocode);
    cityEl?.addEventListener('change', scheduleGeocode);
})();
</script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

<?php include 'includes/footer.php'; ?>
