<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

requireLogin();

$pageTitle = "Đặt hàng thành công";

$order_id = (int)($_GET['order_id'] ?? 0);

if ($order_id <= 0) {
    header('Location: cart.php');
    exit();
}

// Get order details
$order = Database::fetch(
    "SELECT o.*, u.name as user_name, u.email as user_email 
     FROM orders o 
     JOIN users u ON o.user_id = u.user_id 
     WHERE o.order_id = ? AND o.user_id = ?",
    [$order_id, $_SESSION['user_id']]
);

if (!$order) {
    header('Location: cart.php');
    exit();
}

// Compat cho cA3 hai phiA�n bA?n schema
$shippingName = isset($order['shipping_name']) && $order['shipping_name'] !== '' ? $order['shipping_name'] : ($order['user_name'] ?? '');
$shippingNote = $order['shipping_note'] ?? ($order['notes'] ?? '');
$paymentMethodLabel = formatPaymentMethodLabel($order['payment_method'] ?? '');
$isBankTransfer = strtolower((string)($order['payment_method'] ?? '')) === 'bank_transfer';
$qrPlaceholder = 'data:image/svg+xml;base64,' . base64_encode('
<svg xmlns="http://www.w3.org/2000/svg" width="420" height="420" viewBox="0 0 420 420">
  <rect width="420" height="420" fill="#0f5132" rx="24" />
  <rect x="16" y="16" width="388" height="388" fill="#f8f9fa" rx="20" />
  <rect x="42" y="42" width="336" height="336" fill="#0f5132" rx="16" opacity="0.06"/>
  <text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle"
        font-family="Arial, Helvetica, sans-serif" font-size="28" fill="#0f5132">
    QR chuyen khoan
  </text>
</svg>');

// Get order items
$orderItems = Database::fetchAll(
    "SELECT oi.*, i.images, i.condition_status, i.unit
     FROM order_items oi
     LEFT JOIN inventory i ON oi.item_id = i.item_id
     WHERE oi.order_id = ?
     ORDER BY oi.created_at",
    [$order_id]
);

include 'includes/header.php';
?>

<!-- Main Content -->
<div class="container py-5 mt-5">
    <!-- Success Header -->
    <div class="row justify-content-center">
        <div class="col-lg-8 text-center">
            <div class="mb-4">
                <i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>
            </div>
            <h1 class="display-4 fw-bold text-success mb-3">Đặt hàng thành công!</h1>
            <p class="lead text-muted mb-4">
                Cảm ơn bạn đã tin tưởng và ủng hộ Goodwill Vietnam. 
                Chúng tôi sẽ liên hệ với bạn trong thời gian sớm nhất.
            </p>
            <div class="alert alert-info" role="alert">
                <i class="bi bi-info-circle me-2"></i>
                <strong>Mã đơn hàng:</strong> #<?php echo str_pad($order_id, 6, '0', STR_PAD_LEFT); ?>
            </div>
        </div>
    </div>

    <?php if ($isBankTransfer): ?>
    <div class="row justify-content-center mb-4">
        <div class="col-lg-10">
            <div class="card border-success shadow-sm">
                <div class="card-body">
                    <div class="row align-items-center gy-3">
                        <div class="col-md-6">
                            <div class="d-flex align-items-center mb-3">
                                <div class="badge bg-success me-2" style="padding:10px 12px;"><i class="bi bi-bank"></i></div>
                                <div>
                                    <div class="fw-bold text-success mb-0">Chuyển khoản ngân hàng</div>
                                    <small class="text-muted">Quét mã hoặc nhập thông tin dưới đây</small>
                                </div>
                            </div>
                            <ul class="list-unstyled mb-3">
                                <li class="mb-1"><strong>Ngân hàng:</strong> ACB (demo)</li>
                                <li class="mb-1"><strong>Số tài khoản:</strong> 123 456 789</li>
                                <li class="mb-1"><strong>Chủ tài khoản:</strong> Goodwill Vietnam</li>
                                <li class="mb-1"><strong>Số tiền:</strong> <?php echo $order['total_amount'] > 0 ? number_format($order['total_amount']) . ' VNĐ' : '0 VNĐ'; ?></li>
                                <li class="mb-1"><strong>Nội dung:</strong> DON-<?php echo str_pad($order_id, 6, '0', STR_PAD_LEFT); ?></li>
                            </ul>
                            <div class="alert alert-warning mb-0">
                                <i class="bi bi-clock-history me-1"></i>
                                Sau khi quét, vui lòng chờ 1-5 phút để hệ thống xác nhận thanh toán tự động.
                            </div>
                        </div>
                        <div class="col-md-6 text-center">
                            <div class="d-inline-block p-3 border border-success rounded-4" style="background:#f1f5f2;">
                                <img src="<?php echo $qrPlaceholder; ?>" alt="QR chuyển khoản" class="img-fluid mb-2" style="max-width: 220px;">
                            </div>
                            <div class="text-muted small mt-2">Quét mã QR để thanh toán nhanh</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="row justify-content-center">
        <div class="col-lg-10">
            <!-- Order Details -->
            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">
                        <i class="bi bi-receipt me-2"></i>Chi tiết đơn hàng
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-success mb-3">Thông tin giao hàng</h6>
                            <p class="mb-1"><strong>Người nhận:</strong> <?php echo htmlspecialchars($order['shipping_name']); ?></p>
                            <p class="mb-1"><strong>Số điện thoại:</strong> <?php echo htmlspecialchars($order['shipping_phone']); ?></p>
                            <p class="mb-1"><strong>Địa chỉ:</strong> <?php echo htmlspecialchars($order['shipping_address']); ?></p>
                            <?php if ($order['shipping_note']): ?>
                                <p class="mb-1"><strong>Ghi chú:</strong> <?php echo htmlspecialchars($order['shipping_note']); ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-success mb-3">Thông tin thanh toán</h6>
                            <p class="mb-1"><strong>Phương thức:</strong> 
                                <?php 
                                echo $order['payment_method'] === 'cod' ? 'Thanh toán khi nhận hàng (COD)' : 'Chuyển khoản ngân hàng';
                                ?>
                            </p>
                            <p class="mb-1"><strong>Trạng thái:</strong> 
                                <span class="badge bg-warning">Đang xử lý</span>
                            </p>
                            <p class="mb-1"><strong>Ngày đặt:</strong> <?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></p>
                            <p class="mb-1"><strong>Tổng tiền:</strong> 
                                <span class="fw-bold text-success">
                                    <?php echo $order['total_amount'] > 0 ? number_format($order['total_amount']) . ' VNĐ' : 'Miễn phí'; ?>
                                </span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Order Items -->
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <i class="bi bi-box me-2"></i>Sản phẩm đã đặt (<?php echo count($orderItems); ?>)
                    </h5>
                </div>
                <div class="card-body p-0">
                    <?php foreach ($orderItems as $item): ?>
                        <?php
                        $images = json_decode($item['images'] ?? '[]', true);
                        $firstImage = !empty($images) ? 'uploads/donations/' . $images[0] : 'uploads/donations/placeholder-default.svg';
                        ?>
                        <div class="border-bottom p-3">
                            <div class="row align-items-center">
                                <div class="col-md-2 col-3">
                                    <img src="<?php echo htmlspecialchars($firstImage); ?>" 
                                         class="img-fluid rounded" 
                                         style="width: 60px; height: 60px; object-fit: cover;"
                                         alt="<?php echo htmlspecialchars($item['item_name']); ?>"
                                         onerror="this.src='uploads/donations/placeholder-default.svg'">
                                </div>
                                <div class="col-md-4 col-9">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($item['item_name']); ?></h6>
                                    <small class="text-muted">
                                        Tình trạng: <?php echo ucfirst($item['condition_status'] ?? 'Mới'); ?> | 
                                        Đơn vị: <?php echo $item['unit'] ?? 'Cái'; ?>
                                    </small>
                                </div>
                                <div class="col-md-2 col-6 text-center">
                                    <small class="text-muted">Số lượng</small>
                                    <p class="mb-0 fw-bold"><?php echo $item['quantity']; ?></p>
                                </div>
                                <div class="col-md-2 col-6 text-center">
                                    <small class="text-muted">Đơn giá</small>
                                    <p class="mb-0 fw-bold">
                                        <?php echo $item['unit_price'] > 0 ? number_format($item['unit_price']) . ' VNĐ' : 'Miễn phí'; ?>
                                    </p>
                                </div>
                                <div class="col-md-2 col-12 text-center">
                                    <small class="text-muted">Thành tiền</small>
                                    <p class="mb-0 fw-bold text-success">
                                        <?php echo $item['total_price'] > 0 ? number_format($item['total_price']) . ' VNĐ' : 'Miễn phí'; ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Next Steps -->
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">
                        <i class="bi bi-clock me-2"></i>Bước tiếp theo
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 text-center mb-3">
                            <div class="p-3 border rounded">
                                <i class="bi bi-telephone text-primary" style="font-size: 2rem;"></i>
                                <h6 class="mt-2">1. Xác nhận đơn hàng</h6>
                                <small class="text-muted">Chúng tôi sẽ gọi điện xác nhận trong 30 phút</small>
                            </div>
                        </div>
                        <div class="col-md-4 text-center mb-3">
                            <div class="p-3 border rounded">
                                <i class="bi bi-truck text-warning" style="font-size: 2rem;"></i>
                                <h6 class="mt-2">2. Chuẩn bị hàng</h6>
                                <small class="text-muted">Đóng gói và chuẩn bị giao hàng</small>
                            </div>
                        </div>
                        <div class="col-md-4 text-center mb-3">
                            <div class="p-3 border rounded">
                                <i class="bi bi-house-check text-success" style="font-size: 2rem;"></i>
                                <h6 class="mt-2">3. Giao hàng</h6>
                                <small class="text-muted">Giao hàng tận nơi trong 1-3 ngày</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Contact Info -->
            <div class="card mb-4">
                <div class="card-body text-center">
                    <h5 class="text-success mb-3">
                        <i class="bi bi-headset me-2"></i>Hỗ trợ khách hàng
                    </h5>
                    <p class="text-muted mb-3">
                        Nếu bạn có bất kỳ câu hỏi nào về đơn hàng, vui lòng liên hệ với chúng tôi:
                    </p>
                    <div class="row">
                        <div class="col-md-4 mb-2">
                            <i class="bi bi-telephone text-primary me-2"></i>
                            <strong>Hotline:</strong> 1900 1234
                        </div>
                        <div class="col-md-4 mb-2">
                            <i class="bi bi-envelope text-primary me-2"></i>
                            <strong>Email:</strong> support@goodwillvietnam.org
                        </div>
                        <div class="col-md-4 mb-2">
                            <i class="bi bi-clock text-primary me-2"></i>
                            <strong>Giờ làm việc:</strong> 8:00 - 22:00
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="text-center">
                <a href="my-orders.php" class="btn btn-success btn-lg me-3">
                    <i class="bi bi-list-ul me-2"></i>Xem đơn hàng của tôi
                </a>
                <a href="shop.php" class="btn btn-outline-success btn-lg">
                    <i class="bi bi-shop me-2"></i>Tiếp tục mua sắm
                </a>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
