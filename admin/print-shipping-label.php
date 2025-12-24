<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireAdmin();

$cols = Database::fetchAll("SHOW COLUMNS FROM orders");
$existing = array_fill_keys(array_map(fn($c) => $c['Field'], $cols), true);
$add = [];
if (!isset($existing['shipping_carrier'])) $add[] = "ADD COLUMN shipping_carrier VARCHAR(50) NULL";
if (!isset($existing['shipping_tracking_code'])) $add[] = "ADD COLUMN shipping_tracking_code VARCHAR(100) NULL";
if (!isset($existing['shipping_fee'])) $add[] = "ADD COLUMN shipping_fee DECIMAL(10,2) NOT NULL DEFAULT 0";
if (!empty($add)) {
    try {
        Database::execute("ALTER TABLE orders " . implode(", ", $add));
    } catch (Exception $e) {
        error_log('print-shipping-label ensure columns failed: ' . $e->getMessage());
    }
}

$orderId = (int)($_GET['order_id'] ?? 0);
if ($orderId <= 0) {
    http_response_code(400);
    echo 'Invalid order_id';
    exit();
}

$order = Database::fetch(
    "SELECT o.*, u.name AS customer_name, u.email AS customer_email
     FROM orders o
     JOIN users u ON o.user_id = u.user_id
     WHERE o.order_id = ?",
    [$orderId]
);

if (!$order) {
    http_response_code(404);
    echo 'Order not found';
    exit();
}

$items = Database::fetchAll(
    "SELECT item_name, quantity, price, price_type, subtotal
     FROM order_items
     WHERE order_id = ?
     ORDER BY order_item_id ASC",
    [$orderId]
);

$carrier = $order['shipping_carrier'] ?? '';
$tracking = $order['shipping_tracking_code'] ?? '';
if (trim((string)$tracking) === '') {
    $tracking = 'GW' . str_pad((string)$orderId, 10, '0', STR_PAD_LEFT);
}

$displayModes = ['minimal' => 'Ít thông tin', 'balanced' => 'Trung bình', 'full' => 'Đầy đủ'];
$modeParam = $_GET['mode'] ?? 'balanced';
$displayMode = array_key_exists($modeParam, $displayModes) ? $modeParam : 'balanced';
$statusLabels = [
    'pending' => 'Cho xử lý',
    'confirmed' => 'Đã xác nhận',
    'processing' => 'Đang xử lý',
    'shipping' => 'Đang giao',
    'delivered' => 'Đã giao',
    'completed' => 'Hoàn tất',
    'cancelled' => 'Đã huỷ',
];
$statusLabel = $statusLabels[$order['status']] ?? ucfirst((string)$order['status']);
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Shipping Label #<?php echo htmlspecialchars($orderId); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @media print {
            .no-print { display: none !important; }
            body { margin: 0; }
        }
        .label {
            width: 100%;
            max-width: 100mm;
            border: 1px solid #000;
            padding: 10px;
        }
        .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }
        .balanced-only {
            margin-bottom: 0;
        }
        .full-only {
            margin-bottom: 0;
        }
        body.mode-minimal .balanced-only,
        body.mode-minimal .full-only {
            display: none;
        }
        body.mode-balanced .full-only {
            display: none;
        }
    </style>
</head>
<body class="p-3 mode-<?php echo htmlspecialchars($displayMode); ?>">
    <div class="no-print mb-3 d-flex flex-wrap gap-2">
        <div class="d-flex gap-2 align-items-center">
            <button class="btn btn-primary" onclick="window.print()">Print</button>
            <a class="btn btn-outline-secondary" href="orders.php">Back</a>
        </div>
        <form method="get" class="d-flex align-items-center gap-2 mb-0">
            <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($orderId); ?>">
            <label class="mb-0 small text-muted" for="displayModeSelect">Mức hiển thị</label>
            <select id="displayModeSelect" name="mode" class="form-select form-select-sm" onchange="this.form.submit()">
                <?php foreach ($displayModes as $value => $label): ?>
                    <option value="<?php echo htmlspecialchars($value); ?>" <?php echo $displayMode === $value ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <div class="label">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <div class="fw-bold">GOODWILL VIETNAM</div>
                <div class="text-muted small">Shipping label</div>
            </div>
            <div class="text-end">
                <div class="fw-bold mono">#<?php echo str_pad($orderId, 6, '0', STR_PAD_LEFT); ?></div>
                <?php if (!empty($order['order_number'])): ?>
                    <div class="small mono"><?php echo htmlspecialchars($order['order_number']); ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="row g-2">
            <div class="col-12">
                <div class="small text-muted">Nguoi nhan</div>
                <div class="fw-bold"><?php echo htmlspecialchars($order['shipping_name'] ?? ''); ?></div>
                <div><?php echo htmlspecialchars($order['shipping_phone'] ?? ''); ?></div>
                <div><?php echo nl2br(htmlspecialchars($order['shipping_address'] ?? '')); ?></div>
            </div>
            <div class="col-md-4 col-12">
                <div class="small text-muted">Don vi VC</div>
                <div class="fw-bold"><?php echo htmlspecialchars((string)$carrier); ?></div>
            </div>
            <div class="col-md-4 col-12">
                <div class="small text-muted">Ma van don</div>
                <div class="fw-bold mono"><?php echo htmlspecialchars((string)$tracking); ?></div>
            </div>
            <div class="col-md-4 col-12 balanced-only">
                <div class="small text-muted">Ngay tao</div>
                <div class="fw-bold"><?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></div>
            </div>
        </div>

        <div class="row g-2 balanced-only mt-3">
            <div class="col-md-6 col-12">
                <div class="small text-muted">Tong tien</div>
                <div class="fw-bold"><?php echo ((float)($order['total_amount'] ?? 0) > 0) ? number_format((float)$order['total_amount']) . ' VND' : '0 VND'; ?></div>
            </div>
            <div class="col-md-6 col-12">
                <div class="small text-muted">Phi VC</div>
                <div class="fw-bold"><?php echo number_format((float)($order['shipping_fee'] ?? 0)); ?> VND</div>
            </div>
            <div class="col-md-4 col-12">
                <div class="small text-muted">Phuong thuc thanh toan</div>
                <div class="fw-bold"><?php echo $order['payment_method'] === 'cod' ? 'COD' : 'Chuyen khoan'; ?></div>
            </div>
            <div class="col-md-4 col-12">
                <div class="small text-muted">Trang thai don</div>
                <div class="fw-bold"><?php echo htmlspecialchars($statusLabel); ?></div>
            </div>
            <div class="col-md-4 col-12">
                <div class="small text-muted">Email khach hang</div>
                <div class="fw-bold"><?php echo htmlspecialchars($order['customer_email'] ?? ''); ?></div>
            </div>
            <div class="col-12">
                <div class="small text-muted">Ghi chu giao hang</div>
                <div class="fw-bold">
                    <?php echo htmlspecialchars($order['shipping_note'] ?? 'Khong co ghi chu'); ?>
                </div>
            </div>
        </div>

        <hr class="my-2">

        <div class="small text-muted mb-1">Hang hoa</div>
        <?php if (empty($items)): ?>
            <div class="text-muted">Khong co san pham</div>
        <?php else: ?>
            <ul class="mb-0 ps-3">
                <?php foreach ($items as $it): ?>
                    <li>
                        <?php echo htmlspecialchars((string)($it['item_name'] ?? '')); ?>
                        x<?php echo (int)($it['quantity'] ?? 0); ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <div class="full-only mt-4">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="fw-bold">Chi tiet cac mat hang</div>
                <span class="text-muted small"><?php echo count($items); ?> dong</span>
            </div>
            <?php if (empty($items)): ?>
                <div class="text-muted small">Khong co san pham de hien thi chi tiet.</div>
            <?php else: ?>
                <table class="table table-sm table-bordered mb-0">
                    <thead>
                        <tr>
                            <th class="px-2">Ten hang</th>
                            <th class="px-2 text-end">SL</th>
                            <th class="px-2 text-end">Don gia</th>
                            <th class="px-2 text-end">Thanh tien</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $it): ?>
                            <tr>
                                <td class="px-2"><?php echo htmlspecialchars((string)($it['item_name'] ?? '')); ?></td>
                                <td class="px-2 text-end"><?php echo (int)($it['quantity'] ?? 0); ?></td>
                                <td class="px-2 text-end">
                                    <?php
                                    $price = (float)($it['price'] ?? 0);
                                    echo $price > 0 ? number_format($price) . ' VND' : 'Mien phi';
                                    ?>
                                </td>
                                <td class="px-2 text-end">
                                    <?php
                                    $subtotal = (float)($it['subtotal'] ?? $price * (int)$it['quantity']);
                                    echo $subtotal > 0 ? number_format($subtotal) . ' VND' : 'Mien phi';
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
