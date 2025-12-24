<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireAdmin();

function ensureOrdersLogisticsSchema(): void
{
    $cols = Database::fetchAll("SHOW COLUMNS FROM orders");
    $existing = array_fill_keys(array_map(fn($c) => $c['Field'], $cols), true);

    $add = [];
    if (!isset($existing['shipping_carrier'])) {
        $add[] = "ADD COLUMN shipping_carrier VARCHAR(50) NULL";
    }
    if (!isset($existing['shipping_service'])) {
        $add[] = "ADD COLUMN shipping_service VARCHAR(50) NULL";
    }
    if (!isset($existing['shipping_tracking_code'])) {
        $add[] = "ADD COLUMN shipping_tracking_code VARCHAR(100) NULL";
    }
    if (!isset($existing['shipping_fee'])) {
        $add[] = "ADD COLUMN shipping_fee DECIMAL(10,2) NOT NULL DEFAULT 0";
    }
    if (!isset($existing['shipping_weight_gram'])) {
        $add[] = "ADD COLUMN shipping_weight_gram INT NULL";
    }
    if (!isset($existing['shipping_last_mile_status'])) {
        $add[] = "ADD COLUMN shipping_last_mile_status VARCHAR(50) NULL";
    }
    if (!isset($existing['shipping_last_mile_updated_at'])) {
        $add[] = "ADD COLUMN shipping_last_mile_updated_at TIMESTAMP NULL";
    }
    if (!isset($existing['shipped_at'])) {
        $add[] = "ADD COLUMN shipped_at TIMESTAMP NULL";
    }
    if (!isset($existing['delivered_at'])) {
        $add[] = "ADD COLUMN delivered_at TIMESTAMP NULL";
    }
    if (!isset($existing['shipping_admin_note'])) {
        $add[] = "ADD COLUMN shipping_admin_note TEXT NULL";
    }

    if (empty($add)) {
        return;
    }

    try {
        Database::execute("ALTER TABLE orders " . implode(", ", $add));
    } catch (Exception $e) {
        error_log('ensureOrdersLogisticsSchema failed: ' . $e->getMessage());
    }
}

function getOrderStatusEnumValues(): array
{
    $col = Database::fetch("SHOW COLUMNS FROM orders LIKE 'status'");
    $type = $col['Type'] ?? '';
    if (!is_string($type) || stripos($type, "enum(") !== 0) {
        return [];
    }
    preg_match_all("/'([^']+)'/", $type, $m);
    return $m[1] ?? [];
}

function buildTrackingUrl(?string $carrier, ?string $trackingCode): ?string
{
    $carrier = strtolower(trim((string)$carrier));
    $trackingCode = trim((string)$trackingCode);
    if ($carrier === '' || $trackingCode === '') {
        return null;
    }

    switch ($carrier) {
        case 'viettelpost':
        case 'viettel post':
        case 'vtp':
            return "https://viettelpost.com.vn/tra-cuu-hanh-trinh-don/?id=" . rawurlencode($trackingCode);
        case 'ghtk':
            return "https://i.ghtk.vn/" . rawurlencode($trackingCode);
        case 'ghn':
            return "https://donhang.ghn.vn/?order_code=" . rawurlencode($trackingCode);
        case 'j&t':
        case 'jt':
        case 'jnt':
            return "https://www.jtexpress.vn/vi/tracking?billcode=" . rawurlencode($trackingCode);
        case 'vnpost':
            return "https://vnpost.vn/vi-vn/dinh-vi/buu-pham?key=" . rawurlencode($trackingCode);
        default:
            return null;
    }
}

function buildInternalTrackingCode(int $orderId): string
{
    return 'GW' . str_pad((string)$orderId, 10, '0', STR_PAD_LEFT);
}

function getCarrierLabel(?string $carrier): string
{
    $key = strtolower(trim((string)$carrier));
    return match ($key) {
        'viettelpost', 'viettel post', 'vtp' => 'ViettelPost',
        'ghn' => 'GHN',
        'ghtk' => 'GHTK',
        'j&t', 'jt', 'jnt' => 'J&T Express',
        'vnpost' => 'VNPost',
        'grab' => 'GrabExpress',
        default => $carrier ? (string)$carrier : 'Chưa chọn',
    };
}

function getCarrierStatusMeta(?string $status): array
{
    $status = strtolower(trim((string)$status));
    return match ($status) {
        'created' => ['class' => 'secondary', 'text' => 'Đã tạo vận đơn'],
        'waiting_pickup' => ['class' => 'warning', 'text' => 'Chờ lấy hàng'],
        'picked_up' => ['class' => 'info', 'text' => 'Đã lấy hàng'],
        'in_transit' => ['class' => 'primary', 'text' => 'Đang trung chuyển'],
        'out_for_delivery' => ['class' => 'primary', 'text' => 'Đang giao'],
        'delivered' => ['class' => 'success', 'text' => 'Giao thành công'],
        'failed_delivery' => ['class' => 'danger', 'text' => 'Giao thất bại'],
        'returning' => ['class' => 'warning', 'text' => 'Đang hoàn'],
        'returned' => ['class' => 'dark', 'text' => 'Đã hoàn'],
        default => ['class' => 'light text-dark', 'text' => $status !== '' ? $status : '—'],
    };
}

ensureOrdersLogisticsSchema();
$allowedStatuses = getOrderStatusEnumValues();
$validStatuses = !empty($allowedStatuses) ? $allowedStatuses : ['pending', 'confirmed', 'shipping', 'delivered', 'cancelled'];
$shippingStatusKey = in_array('shipping', $validStatuses, true) ? 'shipping' : (in_array('processing', $validStatuses, true) ? 'processing' : 'shipping');
$deliveredStatusKey = in_array('delivered', $validStatuses, true) ? 'delivered' : (in_array('completed', $validStatuses, true) ? 'completed' : 'delivered');

// Detect history table once so we can log safely in POST handler
$historyTableExists = !empty(Database::fetchAll("SHOW TABLES LIKE 'order_status_history'"));

// Handle status update / cancel
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $order_id = (int)($_POST['order_id'] ?? 0);
    $action   = $_POST['action'];

    if ($order_id > 0) {
        try {
            $order = Database::fetch("SELECT * FROM orders WHERE order_id = ?", [$order_id]);
            if (!$order) {
                throw new Exception('Đơn hàng không tồn tại.');
            }

            $old_status = $order['status'];
            $new_status = $old_status;

	            if ($action === 'update_logistics') {
	                // Carrier constraint: only ViettelPost
	                $shipping_carrier = 'viettelpost';

	                $last_mile_status = strtolower(trim($_POST['shipping_last_mile_status'] ?? ''));

	                // Service constraint: only 3 services; editable only when last-mile status is empty/created.
	                $existingService = trim((string)($order['shipping_service'] ?? ''));
	                $postedService = trim((string)($_POST['shipping_service'] ?? ''));
	                $allowedServices = ['VCN', 'VHT', 'VTK'];

	                $oldCarrierStatus = strtolower(trim((string)($order['shipping_last_mile_status'] ?? '')));
	                $canEditPricingAndService = in_array($oldCarrierStatus, ['', 'created'], true);

	                if (!$canEditPricingAndService) {
	                    $shipping_service = $existingService;
	                } elseif ($postedService !== '' && in_array($postedService, $allowedServices, true)) {
	                    $shipping_service = $postedService;
	                } else {
	                    $shipping_service = '';
	                }
	                $tracking_code = buildInternalTrackingCode($order_id);
	                $admin_note = trim($_POST['shipping_admin_note'] ?? '');

	                // Fee/weight editable only when last-mile status is empty/created
	                if (!$canEditPricingAndService) {
	                    $shipping_fee = (float)($order['shipping_fee'] ?? 0);
	                    $weight_gram = (int)($order['shipping_weight_gram'] ?? 0);
	                } else {
	                    $shipping_fee = array_key_exists('shipping_fee', $_POST)
	                        ? (float)($_POST['shipping_fee'])
	                        : (float)($order['shipping_fee'] ?? 0);
	                    $weight_gram = array_key_exists('shipping_weight_gram', $_POST)
	                        ? (int)($_POST['shipping_weight_gram'])
	                        : (int)($order['shipping_weight_gram'] ?? 0);
	                }

                $setStatusToShipping = !empty($_POST['set_status_shipping']);
                $setStatusToDelivered = !empty($_POST['set_status_delivered']);

                Database::beginTransaction();

                Database::execute(
                    "UPDATE orders
                     SET shipping_carrier = ?,
                         shipping_service = ?,
                         shipping_tracking_code = ?,
                         shipping_fee = ?,
                         shipping_weight_gram = ?,
                         shipping_last_mile_status = ?,
                         shipping_last_mile_updated_at = NOW(),
                         shipping_admin_note = ?,
                         shipped_at = CASE WHEN ? <> '' AND shipped_at IS NULL THEN NOW() ELSE shipped_at END,
                         delivered_at = CASE WHEN ? = 'delivered' AND delivered_at IS NULL THEN NOW() ELSE delivered_at END,
                         updated_at = NOW()
                     WHERE order_id = ?",
                    [
	                        $shipping_carrier,
	                        $shipping_service !== '' ? $shipping_service : null,
                        $tracking_code,
                        max(0, $shipping_fee),
                        $weight_gram > 0 ? $weight_gram : null,
                        $last_mile_status !== '' ? $last_mile_status : null,
                        $admin_note !== '' ? $admin_note : null,
                        $tracking_code,
                        strtolower($last_mile_status),
                        $order_id
                    ]
                );

                $statusToApply = null;
                if ($setStatusToDelivered || strtolower($last_mile_status) === 'delivered') {
                    $statusToApply = $deliveredStatusKey;
                } else {
                    $confirmedStatusKey = in_array('confirmed', $validStatuses, true)
                        ? 'confirmed'
                        : (in_array('processing', $validStatuses, true) ? 'processing' : 'pending');

                    if ($setStatusToShipping) {
                        $statusToApply = $shippingStatusKey;
                    } elseif (in_array($last_mile_status, ['out_for_delivery', 'in_transit', 'picked_up', 'failed_delivery', 'returning', 'returned'], true)) {
                        $statusToApply = $shippingStatusKey;
                    } elseif (in_array($last_mile_status, ['waiting_pickup', 'created'], true)) {
                        $statusToApply = $confirmedStatusKey;
                    }
                }

                if ($statusToApply !== null && $statusToApply !== $old_status && in_array($statusToApply, $validStatuses, true)) {
                    Database::execute(
                        "UPDATE orders SET status = ?, updated_at = NOW() WHERE order_id = ?",
                        [$statusToApply, $order_id]
                    );

                    logActivity($_SESSION['user_id'], 'update_order_logistics', "Updated logistics for order #$order_id and set status to $statusToApply");
                } else {
                    logActivity($_SESSION['user_id'], 'update_order_logistics', "Updated logistics for order #$order_id");
                }

	                // Write logistics history (avoid mixing with order status trigger)
	                if ($historyTableExists && $last_mile_status !== '') {
	                    if ($oldCarrierStatus !== $last_mile_status) {
	                        Database::execute(
	                            "INSERT INTO order_status_history (order_id, old_status, new_status, note, created_at)
	                             VALUES (?, ?, ?, ?, NOW())",
                            [
                                $order_id,
                                'logistics:' . ($oldCarrierStatus !== '' ? $oldCarrierStatus : 'created'),
                                'logistics:' . $last_mile_status,
                                'Logistics status update'
                            ]
                        );
                    }
                }

                Database::commit();
                setFlashMessage('success', 'Da cap nhat thong tin van chuyen.');

            } elseif ($action === 'cancel_order') {
                if ($old_status === 'cancelled') {
                    throw new Exception('Đơn hàng đã bị hủy trước đó.');
                }

                $new_status = 'cancelled';
                Database::execute(
                    "UPDATE orders SET status = ?, updated_at = NOW() WHERE order_id = ?",
                    [$new_status, $order_id]
                );

                if ($historyTableExists) {
                    Database::execute(
                        "INSERT INTO order_status_history (order_id, old_status, new_status, note, created_at) 
                         VALUES (?, ?, ?, ?, NOW())",
                        [$order_id, $old_status, $new_status, 'Hủy đơn hàng từ admin']
                    );
                }

                setFlashMessage('success', 'Đã hủy đơn hàng.');
                logActivity($_SESSION['user_id'], 'cancel_order_admin', "Cancelled order #$order_id from admin");
            }
        } catch (Exception $e) {
            if (Database::getConnection()->inTransaction()) {
                Database::rollback();
            }
            setFlashMessage('error', 'Có lỗi xảy ra: ' . $e->getMessage());
        }

        header('Location: orders.php');
        exit();
    }
}

// Filters
$status   = $_GET['status']   ?? '';
$user_id  = (int)($_GET['user_id'] ?? 0);
$search   = trim($_GET['search'] ?? '');
$carrier  = trim($_GET['carrier'] ?? '');
$tracking = trim($_GET['tracking'] ?? '');
$date_from = $_GET['date_from'] ?? '';
$date_to   = $_GET['date_to'] ?? '';
$page     = (int)($_GET['page'] ?? 1);
$per_page = 20;
$offset   = ($page - 1) * $per_page;

if (!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $date_from)) {
    $date_from = '';
}
if (!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $date_to)) {
    $date_to = '';
}

$where  = "1=1";
$params = [];

if ($status !== '') {
    $where   .= " AND o.status = ?";
    $params[] = $status;
}

if ($user_id > 0) {
    $where   .= " AND o.user_id = ?";
    $params[] = $user_id;
}

if ($search !== '') {
    $where      .= " AND (u.name LIKE ? OR u.email LIKE ? OR o.shipping_name LIKE ? OR o.shipping_phone LIKE ?)";
    $searchLike  = "%$search%";
    $params[]    = $searchLike;
    $params[]    = $searchLike;
    $params[]    = $searchLike;
    $params[]    = $searchLike;
}

if ($carrier !== '') {
    $where   .= " AND o.shipping_carrier = ?";
    $params[] = $carrier;
}

if ($tracking !== '') {
    $where   .= " AND o.shipping_tracking_code LIKE ?";
    $params[] = "%" . $tracking . "%";
}

if ($date_from !== '') {
    $where   .= " AND DATE(o.created_at) >= ?";
    $params[] = $date_from;
}

if ($date_to !== '') {
    $where   .= " AND DATE(o.created_at) <= ?";
    $params[] = $date_to;
}

// Get total count
$totalSql   = "SELECT COUNT(*) as count FROM orders o JOIN users u ON o.user_id = u.user_id WHERE $where";
$totalRow   = Database::fetch($totalSql, $params);
$totalCount = (int)($totalRow['count'] ?? 0);
$totalPages = max(1, ceil($totalCount / $per_page));

$sql = "SELECT o.*, 
               u.name  as user_name,
               u.email as user_email,
               COUNT(oi.order_item_id)  as total_items,
               COALESCE(SUM(oi.quantity), 0) as total_quantity
        FROM orders o
        JOIN users u ON o.user_id = u.user_id
        LEFT JOIN order_items oi ON o.order_id = oi.order_id
        WHERE $where
        GROUP BY o.order_id
        ORDER BY o.created_at DESC
        LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$orders   = Database::fetchAll($sql, $params);

// Get users for filter
$users = Database::fetchAll("SELECT user_id, name, email FROM users ORDER BY name");

$carrierOptions = [
    '' => 'Tấ   t c?',
    'viettelpost' => 'ViettelPost',
];

$serviceOptions = [
    '' => '-- Chọn --',
    'VCN' => 'Chuyển phát Nhanh (VCN)',
    'VHT' => 'Hoả tốc (VHT)',
    'VTK' => 'Tiết kiệm (VTK)',
];

$orderStatusLabels = [
    'pending' => 'Chờ xử lý',
    'confirmed' => 'Đã xác nhận',
    'processing' => 'Đang xử lý',
    'shipping' => 'Đang giao',
    'delivered' => 'Đã giao',
    'completed' => 'Hoàn tất',
    'cancelled' => 'Đã hủy',
    'refunded' => 'Đã hoàn tiền',
];

// Statistics for top cards
$statusCountRows = Database::fetchAll("SELECT status, COUNT(*) AS count FROM orders GROUP BY status");
$statusCounts = [];
foreach ($statusCountRows as $row) {
    $statusCounts[$row['status']] = (int)($row['count'] ?? 0);
}
$totalRevenueRow = Database::fetch("SELECT COALESCE(SUM(total_amount), 0) AS total_revenue FROM orders");
$shippingCardLabel = $orderStatusLabels[$shippingStatusKey] ?? 'Đang giao';
$deliveredCardLabel = $orderStatusLabels[$deliveredStatusKey] ?? 'Đã giao';

$stats = [
    'total_orders' => array_sum($statusCounts),
    'pending_orders' => $statusCounts['pending'] ?? 0,
    'confirmed_orders' => $statusCounts['confirmed'] ?? 0,
    'shipping_orders' => $statusCounts[$shippingStatusKey] ?? ($statusCounts['shipping'] ?? 0),
    'delivered_orders' => $statusCounts[$deliveredStatusKey] ?? (($statusCounts['delivered'] ?? 0) + ($statusCounts['completed'] ?? 0)),
    'cancelled_orders' => $statusCounts['cancelled'] ?? 0,
    'total_revenue' => (float)($totalRevenueRow['total_revenue'] ?? 0),
];

$pageTitle = "Quản lý đơn hàng";
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">    <style>
        .orders-actions {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        .orders-actions form {
            margin: 0;
        }
        .orders-action-btn {
            width: 48px;
            height: 38px;
            border-radius: 14px;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.05rem;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
            color: #fff;
        }
        .orders-action-btn:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.25);
        }
        .orders-action-btn:hover {
            transform: translateY(-1px);
        }
        .orders-action-btn.view { background-color: #0d6efd; }
        .orders-action-btn.ship { background-color: #198754; }
        .orders-action-btn.print { background-color: #0dcaf0; }
        .orders-action-btn.cancel { background-color: #dc3545; }
        .orders-action-btn i { pointer-events: none; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 admin-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="bi bi-cart-check me-2"></i>Quản lý đơn hàng
                    </h1>
                </div>

                <?php echo displayFlashMessages(); ?>

                <!-- Stats cards -->
                <div class="row mb-4">
                    <div class="col-md-2 col-6 mb-3">
                        <div class="card bg-primary text-white h-100">
                            <div class="card-body">
                                <h6 class="mb-1">Tổng đơn</h6>
                                <h3 class="mb-0"><?php echo number_format($stats['total_orders'] ?? 0); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 col-6 mb-3">
                        <div class="card bg-warning text-dark h-100">
                            <div class="card-body">
                                <h6 class="mb-1">Chờ xử lý</h6>
                                <h3 class="mb-0"><?php echo number_format($stats['pending_orders'] ?? 0); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 col-6 mb-3">
                        <div class="card bg-info text-white h-100">
                            <div class="card-body">
                                <h6 class="mb-1">Đã xác nhận</h6>
                                <h3 class="mb-0"><?php echo number_format($stats['confirmed_orders'] ?? 0); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 col-6 mb-3">
                        <div class="card bg-primary text-white h-100">
                            <div class="card-body">
                                <h6 class="mb-1">Đang giao</h6>
                                <h3 class="mb-0"><?php echo number_format($stats['shipping_orders'] ?? 0); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 col-6 mb-3">
                        <div class="card bg-success text-white h-100">
                            <div class="card-body">
                                <h6 class="mb-1">Đã giao</h6>
                                <h3 class="mb-0"><?php echo number_format($stats['delivered_orders'] ?? 0); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 col-6 mb-3">
                        <div class="card bg-danger text-white h-100">
                            <div class="card-body">
                                <h6 class="mb-1">Đã hủy</h6>
                                <h3 class="mb-0"><?php echo number_format($stats['cancelled_orders'] ?? 0); ?></h3>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Tìm kiếm</label>
                                <input type="text"
                                       name="search"
                                       class="form-control"
                                       value="<?php echo htmlspecialchars($search); ?>"
                                       placeholder="Tên, email, người nhận, SĐT...">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Trạng thái</label>
                                <select name="status" class="form-select">
                                    <option value="">Tất cả</option>
                                    <option value="pending"   <?php echo $status === 'pending'   ? 'selected' : ''; ?>>Chờ xử lý</option>
                                    <option value="confirmed" <?php echo $status === 'confirmed' ? 'selected' : ''; ?>>Đã xác nhận</option>
                                    <option value="shipping"  <?php echo $status === 'shipping'  ? 'selected' : ''; ?>>Đang giao</option>
                                    <option value="delivered" <?php echo $status === 'delivered' ? 'selected' : ''; ?>>Đã giao</option>
                                    <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Đã hủy</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Người dùng</label>
                                <select name="user_id" class="form-select">
                                    <option value="">Tất cả</option>
                                    <?php foreach ($users as $u): ?>
                                        <option value="<?php echo $u['user_id']; ?>"
                                            <?php echo $user_id === (int)$u['user_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($u['name'] . ' (' . $u['email'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-search me-1"></i>Lọc
                                </button>
                            </div>
                            <div class="w-100"></div>
                            <div class="col-md-3">
                                <label class="form-label">Đơn vị vận chuyển</label>
                                <select name="carrier" class="form-select">
                                    <?php foreach ($carrierOptions as $key => $label): ?>
                                        <option value="<?php echo htmlspecialchars($key); ?>" <?php echo $carrier === $key ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Mã vận dơn</label>
                                <input type="text" name="tracking" class="form-control" value="<?php echo htmlspecialchars($tracking); ?>" placeholder="VD: VTP123...">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Từ ngày</label>
                                <input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($date_from); ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Đến ngày</label>
                                <input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($date_to); ?>">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <a class="btn btn-outline-secondary w-100" href="orders.php">
                                    <i class="bi bi-arrow-clockwise me-1"></i>Reset
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Orders table -->
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Người dùng</th>
                                        <th>Người nhận</th>
                                        <th>SĐT</th>
                                        <th>Sản phẩm</th>
                                        <th>Tổng tiền</th>
                                        <th>Trạng thái</th>
                                        <th>Ngày tạo</th>
                                        <th>Hành động</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($orders)): ?>
                                        <tr>
                                            <td colspan="9" class="text-center text-muted">
                                                Không có đơn hàng nào.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($orders as $order): ?>
                                            <?php
                                            $statusClass = 'secondary';
                                            $statusText  = $order['status'];
                                            switch ($order['status']) {
                                                case 'pending':
                                                    $statusClass = 'warning';
                                                    $statusText  = 'Chờ xử lý';
                                                    break;
                                                case 'confirmed':
                                                    $statusClass = 'info';
                                                    $statusText  = 'Đã xác nhận';
                                                    break;
                                                case 'shipping':
                                                    $statusClass = 'primary';
                                                    $statusText  = 'Đang giao';
                                                    break;
                                                case 'delivered':
                                                    $statusClass = 'success';
                                                    $statusText  = 'Đã giao';
                                                    break;
                                                case 'cancelled':
                                                    $statusClass = 'danger';
                                                    $statusText  = 'Đã hủy';
                                                    break;
                                            }

	                                            $carrierLabel = getCarrierLabel(($order['shipping_carrier'] ?? '') !== '' ? $order['shipping_carrier'] : 'viettelpost');
                                            $trackingCode = (string)($order['shipping_tracking_code'] ?? '');
                                            if ($trackingCode === '') {
                                                $trackingCode = buildInternalTrackingCode((int)$order['order_id']);
                                            }
	                                            $trackingUrl = buildTrackingUrl(($order['shipping_carrier'] ?? '') !== '' ? $order['shipping_carrier'] : 'viettelpost', $trackingCode);
                                            $carrierStatusMeta = getCarrierStatusMeta($order['shipping_last_mile_status'] ?? '');
                                            $shippingFee = (float)($order['shipping_fee'] ?? 0);
                                            ?>
                                            <tr>
                                                                <td>#<?php echo str_pad($order['order_id'], 6, '0', STR_PAD_LEFT); ?></td>
                                                                <td>
                                                    <strong><?php echo htmlspecialchars($order['user_name']); ?></strong><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($order['user_email']); ?></small>
                                                </td>
                                                                <td><?php echo htmlspecialchars($order['shipping_name']); ?></td>
                                                                <td><?php echo htmlspecialchars($order['shipping_phone']); ?></td>
                                                                <td>
                                                    <?php echo (int)$order['total_items']; ?> loại
                                                    (<?php echo (int)$order['total_quantity']; ?> cái)
                                                </td>
                                                                <td>
                                                    <strong class="text-success">
                                                        <?php echo $order['total_amount'] > 0 ? number_format($order['total_amount']) . ' VNĐ' : 'Miễn phí'; ?>
                                                    </strong>
                                                </td>
                                                                <td>
                                                    <div class="mt-1 small">
                                                        <div class="text-muted">
                                                            <i class="bi bi-truck me-1"></i><?php echo htmlspecialchars($carrierLabel); ?>
                                                            <?php if (!empty($trackingCode)): ?>
                                                                <?php if ($trackingUrl): ?>
                                                                    - <a href="<?php echo htmlspecialchars($trackingUrl); ?>" target="_blank" rel="noopener">
                                                                        <?php echo htmlspecialchars($trackingCode); ?>
                                                                    </a>
                                                                <?php else: ?>
                                                                    - <?php echo htmlspecialchars($trackingCode); ?>
                                                                <?php endif; ?>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="text-muted">
                                                            <?php if ($shippingFee > 0): ?>
                                                                <span class="me-2">Phi VC: <strong><?php echo number_format($shippingFee); ?></strong> VND</span>
                                                            <?php endif; ?>
                                                            <span class="badge bg-<?php echo htmlspecialchars($carrierStatusMeta['class']); ?>">
                                                                <?php echo htmlspecialchars($carrierStatusMeta['text']); ?>
                                                            </span>

                                                        </div>
                                                    </div>
                                                </td>
                                                                <td><?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></td>
                                                                                                                <td>
                                                    <div class="orders-actions">
                                                        <a href="../order-detail.php?id=<?php echo $order['order_id']; ?>"
                                                           class="orders-action-btn view" title="Xem chi tiết">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                        <button type="button"
                                                                class="orders-action-btn ship"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#logisticsModal<?php echo $order['order_id']; ?>"
                                                                title="Van chuyen">
                                                            <i class="bi bi-truck"></i>
                                                        </button>
                                                        <a href="print-shipping-label.php?order_id=<?php echo $order['order_id']; ?>"
                                                           class="orders-action-btn print"
                                                           target="_blank"
                                                           rel="noopener"
                                                           title="In phieu gui">
                                                            <i class="bi bi-printer"></i>
                                                        </a>
<?php if ($order['status'] !== 'cancelled'): ?>
                                                            <form method="POST" class="d-inline"
                                                                  onsubmit="return confirm('Hủy đơn hàng này?');">
                                                                <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                                                <input type="hidden" name="action" value="cancel_order">
                                                                <button type="submit" class="orders-action-btn cancel" title="Hủy đơn">
                                                                    <i class="bi bi-x-circle"></i>
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>

                                            <!-- Logistics Modal -->
                                            <div class="modal fade" id="logisticsModal<?php echo $order['order_id']; ?>" tabindex="-1" aria-hidden="true">
                                                <div class="modal-dialog modal-lg modal-dialog-centered">
                                                    <div class="modal-content">
                                                        <form method="POST">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Logistics / Van chuyen - Don #<?php echo str_pad($order['order_id'], 6, '0', STR_PAD_LEFT); ?></h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                                                <input type="hidden" name="action" value="update_logistics">

	                                                                <div class="row g-3">
	                                                                    <?php
	                                                                    $currentCarrierStatusKey = strtolower(trim((string)($order['shipping_last_mile_status'] ?? '')));
	                                                                    $feeWeightLocked = !in_array($currentCarrierStatusKey, ['', 'created'], true);
	                                                                    $serviceLocked = $feeWeightLocked;
	                                                                    ?>
	                                                                    <div class="col-md-4">
	                                                                        <label class="form-label">Đơn vị vân chuyển</label>
	                                                                        <input type="hidden" name="shipping_carrier" value="viettelpost">
	                                                                        <input type="text" class="form-control" value="ViettelPost" disabled>
	                                                                    </div>
	                                                                    <div class="col-md-4">
	                                                                        <label class="form-label">Dịch vụ</label>
	                                                                        <?php if ($serviceLocked): ?>
	                                                                            <input type="hidden" name="shipping_service" value="<?php echo htmlspecialchars((string)($order['shipping_service'] ?? '')); ?>">
	                                                                        <?php endif; ?>
	                                                                        <select name="shipping_service" class="form-select js-service" <?php echo $serviceLocked ? 'disabled' : ''; ?>>
	                                                                            <?php foreach ($serviceOptions as $code => $label): ?>
	                                                                                <option value="<?php echo htmlspecialchars($code); ?>"
	                                                                                    <?php echo ((string)($order['shipping_service'] ?? '') === $code) ? 'selected' : ''; ?>>
	                                                                                    <?php echo htmlspecialchars($label); ?>
	                                                                                </option>
	                                                                            <?php endforeach; ?>
	                                                                        </select>
	                                                                    </div>
	                                                                    <div class="col-md-4">
	                                                                        <label class="form-label">Phí vận chuyển (VND)</label>
	                                                                        <?php if ($feeWeightLocked): ?>
	                                                                            <input type="hidden" name="shipping_fee" value="<?php echo htmlspecialchars($order['shipping_fee'] ?? 0); ?>">
	                                                                        <?php endif; ?>
	                                                                        <input type="number"
	                                                                               step="0.01"
	                                                                               min="0"
	                                                                               name="shipping_fee"
		                                                                       class="form-control js-fee"
		                                                                       value="<?php echo htmlspecialchars($order['shipping_fee'] ?? 0); ?>"
		                                                                       <?php echo $feeWeightLocked ? 'disabled' : ''; ?>>
		                                                                    </div>
                                                                    <div class="col-md-6">
                                                                        <label class="form-label">Mã vận đơn</label>
                                                                        <?php $internalTracking = buildInternalTrackingCode((int)$order['order_id']); ?>
                                                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($internalTracking); ?>" disabled>
                                                                        <div class="form-text">Mã vận đơn tự động theo ID đơn hàng (Không thể chỉnh sửa).</div>
	                                                                        <div class="form-text">
	                                                                            <?php
	                                                                            $previewUrl = buildTrackingUrl('viettelpost', $internalTracking);
	                                                                            ?>
	                                                                            <?php if ($previewUrl): ?>
	                                                                                <a href="<?php echo htmlspecialchars($previewUrl); ?>" target="_blank" rel="noopener">Mo trang tracking</a>
                                                                            <?php else: ?>
                                                                                Tracking URL sẽ hiện khi có đơn vị + mã vân đơnf.
                                                                            <?php endif; ?>
                                                                        </div>
                                                                    </div>
	                                                                    <div class="col-md-3">
	                                                                        <label class="form-label">Khối lượng (gram)</label>
	                                                                        <?php if ($feeWeightLocked): ?>
	                                                                            <input type="hidden" name="shipping_weight_gram" value="<?php echo htmlspecialchars($order['shipping_weight_gram'] ?? ''); ?>">
	                                                                        <?php endif; ?>
	                                                                        <input type="number"
	                                                                               min="0"
	                                                                               name="shipping_weight_gram"
		                                                                       class="form-control js-weight"
		                                                                       value="<?php echo htmlspecialchars($order['shipping_weight_gram'] ?? ''); ?>"
		                                                                       placeholder="VD: 500"
		                                                                       <?php echo $feeWeightLocked ? 'disabled' : ''; ?>>
		                                                                    </div>
	                                                                    <div class="col-md-3">
	                                                                        <label class="form-label">Trạng thái vận chuyển</label>
	                                                                        <select name="shipping_last_mile_status" class="form-select js-lastmile-status">
                                                                            <option value="">-- Chon --</option>
                                                                            <?php
                                                                            $carrierStatusOptions = [
                                                                                'created' => 'Đã tạo vận đơn',
                                                                                'waiting_pickup' => 'Chờ lấy hàng',
                                                                                'picked_up' => 'Đã lấy hàng',
                                                                                'in_transit' => 'Đang trung chuyển',
                                                                                'out_for_delivery' => 'Đang giao',
                                                                                'delivered' => 'Giao thành công',
                                                                                'failed_delivery' => 'Giao thất bại',
                                                                                'returning' => 'Đang hoãn',
                                                                                'returned' => 'Đã hoãn',
                                                                            ];
                                                                            $currentCarrierStatus = (string)($order['shipping_last_mile_status'] ?? '');
                                                                            foreach ($carrierStatusOptions as $k => $lbl):
                                                                            ?>
                                                                                <option value="<?php echo $k; ?>" <?php echo $currentCarrierStatus === $k ? 'selected' : ''; ?>>
                                                                                    <?php echo htmlspecialchars($lbl); ?>
                                                                                </option>
                                                                            <?php endforeach; ?>
                                                                        </select>
                                                                    </div>
                                                                    <div class="col-12">
                                                                        <label class="form-label">Ghi chú nọi bộ (logistics)</label>
                                                                        <textarea name="shipping_admin_note" class="form-control" rows="2" placeholder="VD: Hen lay hang, ghi chu giao hang..."><?php echo htmlspecialchars($order['shipping_admin_note'] ?? ''); ?></textarea>
                                                                    </div>
                                                                </div>

                                                                <hr>

                                                                <div class="row g-3">
                                                                    <div class="col-md-6">
                                                                        <div class="form-check">
                                                                            <input class="form-check-input" type="checkbox" name="set_status_shipping" id="setStatusShipping<?php echo $order['order_id']; ?>" value="1">
                                                                            <label class="form-check-label" for="setStatusShipping<?php echo $order['order_id']; ?>">
                                                                                Chuyen trang thai don sang "<?php echo htmlspecialchars($orderStatusLabels[$shippingStatusKey] ?? $shippingStatusKey); ?>"
                                                                            </label>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-md-6">
                                                                        <div class="form-check">
                                                                            <input class="form-check-input" type="checkbox" name="set_status_delivered" id="setStatusDelivered<?php echo $order['order_id']; ?>" value="1">
                                                                            <label class="form-check-label" for="setStatusDelivered<?php echo $order['order_id']; ?>">
                                                                                Chuyen trang thai don sang "<?php echo htmlspecialchars($orderStatusLabels[$deliveredStatusKey] ?? $deliveredStatusKey); ?>"
                                                                            </label>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Dong</button>
                                                                <button type="submit" class="btn btn-success">
                                                                    <i class="bi bi-save me-1"></i>Luu logistics
                                                                </button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                            <nav class="mt-3">
                                <ul class="pagination justify-content-center">
                                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                            <a class="page-link"
                                               href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

	    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
	    <script>
	        (function () {
	            function syncFeeWeight(form) {
	                if (!form) return;
	                const statusSelect = form.querySelector('.js-lastmile-status');
	                const feeInput = form.querySelector('.js-fee');
	                const weightInput = form.querySelector('.js-weight');
	                const serviceSelect = form.querySelector('.js-service');
	                if (!statusSelect || !feeInput || !weightInput) return;

	                const feeWeightLocked = (statusSelect.value === 'waiting_pickup');
	                feeInput.disabled = feeWeightLocked;
	                weightInput.disabled = feeWeightLocked;

	                if (serviceSelect) {
	                    const serviceLocked = (statusSelect.value === 'waiting_pickup' && (serviceSelect.value || '').trim() !== '');
	                    serviceSelect.disabled = serviceLocked;

	                    let hiddenService = form.querySelector('input[type="hidden"][name="shipping_service"]');
	                    if (serviceLocked) {
	                        if (!hiddenService) {
	                            hiddenService = document.createElement('input');
	                            hiddenService.type = 'hidden';
	                            hiddenService.name = 'shipping_service';
	                            serviceSelect.insertAdjacentElement('afterend', hiddenService);
	                        }
	                        hiddenService.value = serviceSelect.value;
	                    } else if (hiddenService) {
	                        hiddenService.remove();
	                    }
	                }
	            }

	            document.addEventListener('change', function (e) {
	                if (e.target && e.target.classList && e.target.classList.contains('js-lastmile-status')) {
	                    syncFeeWeight(e.target.closest('form'));
	                }
	            });

	            document.querySelectorAll('.modal').forEach(function (modalEl) {
	                modalEl.addEventListener('shown.bs.modal', function () {
	                    syncFeeWeight(modalEl.querySelector('form'));
	                });
	            });
	        })();
	    </script>
	</body>
	</html>
