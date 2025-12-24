<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

requireLogin();

	$isAdmin = isAdmin();

function buildInternalTrackingCode(int $orderId): string
{
    return 'GW' . str_pad((string)$orderId, 10, '0', STR_PAD_LEFT);
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
        default => $carrier ? (string)$carrier : 'Chua chon',
    };
}

$pageTitle = "Theo dõi đơn hàng";

$order_id = (int)($_GET['id'] ?? 0);

if ($order_id <= 0) {
    header('Location: ' . ($isAdmin ? 'admin/orders.php' : 'my-orders.php'));
    exit();
}

// Lấy thông tin đơn hàng của chính user
$orderParams = [$order_id];
$orderSql = "SELECT o.*, u.name as user_name, u.email as user_email
     FROM orders o
     JOIN users u ON o.user_id = u.user_id
     WHERE o.order_id = ?";

if (!$isAdmin) {
    $orderSql .= " AND o.user_id = ?";
    $orderParams[] = (int)$_SESSION['user_id'];
}

$order = Database::fetch($orderSql, $orderParams);

if (!$order) {
    header('Location: ' . ($isAdmin ? 'admin/orders.php' : 'my-orders.php'));
    exit();
}

// Lấy lịch sử trạng thái (nếu có)
$statusHistory = [];
try {
    $statusHistory = Database::fetchAll(
        "SELECT * FROM order_status_history 
         WHERE order_id = ? 
         ORDER BY created_at ASC",
        [$order_id]
    );
} catch (Exception $e) {
    // Nếu bảng chưa tồn tại thì bỏ qua phần history
    $statusHistory = [];
}

// Logistics info
$carrierLabel = getCarrierLabel($order['shipping_carrier'] ?? '');
$trackingCode = trim((string)($order['shipping_tracking_code'] ?? ''));
if ($trackingCode === '') {
    $trackingCode = buildInternalTrackingCode($order_id);
}
$trackingUrl = buildTrackingUrl($order['shipping_carrier'] ?? '', $trackingCode);
$shippingFee = (float)($order['shipping_fee'] ?? 0);

// Map status -> steps (prefer logistics status when available)
$useLogistics = ($order['status'] ?? '') !== 'cancelled'
    && (
        trim((string)($order['shipping_last_mile_status'] ?? '')) !== ''
        || trim((string)($order['shipping_carrier'] ?? '')) !== ''
        || trim((string)($order['shipping_tracking_code'] ?? '')) !== ''
    );

	if ($useLogistics) {
	    $steps = [
	        'created' => ['label' => 'Da tao van don', 'icon' => 'receipt'],
	        'waiting_pickup' => ['label' => 'Cho lay hang', 'icon' => 'clock'],
	        'picked_up' => ['label' => 'Da lay hang', 'icon' => 'box-seam'],
	        'in_transit' => ['label' => 'Dang trung chuyen', 'icon' => 'truck'],
	        'out_for_delivery' => ['label' => 'Dang giao', 'icon' => 'truck'],
	        'delivered' => ['label' => 'Giao thanh cong', 'icon' => 'house-check'],
	        'failed_delivery' => ['label' => 'Giao that bai', 'icon' => 'x-circle'],
	        'returning' => ['label' => 'Dang hoan', 'icon' => 'arrow-return-left'],
	        'returned' => ['label' => 'Da hoan', 'icon' => 'arrow-return-left'],
	    ];

	    $statusOrder = ['created', 'waiting_pickup', 'picked_up', 'in_transit', 'out_for_delivery', 'delivered'];
	    $statusRank = [
	        'created' => 0,
	        'waiting_pickup' => 1,
	        'picked_up' => 2,
	        'in_transit' => 3,
	        'out_for_delivery' => 4,
	        'failed_delivery' => 5,
	        'returning' => 6,
	        'returned' => 7,
	        'delivered' => 7,
	    ];
	    $currentStatus = trim((string)($order['shipping_last_mile_status'] ?? ''));

    if ($currentStatus === '') {
	        switch ($order['status'] ?? '') {
	            case 'pending':
	                $currentStatus = 'created';
	                break;
	            case 'confirmed':
	                $currentStatus = 'waiting_pickup';
	                break;
	            case 'shipping':
	                $currentStatus = 'in_transit';
	                break;
	            case 'delivered':
	                $currentStatus = 'delivered';
	                break;
	            default:
                $currentStatus = 'created';
        }
    }
	} else {
	    $steps = [
	        'pending'   => ['label' => 'Cho xu ly', 'icon' => 'clock'],
	        'confirmed' => ['label' => 'Da xac nhan', 'icon' => 'check-circle'],
	        'shipping'  => ['label' => 'Dang giao', 'icon' => 'truck'],
	        'delivered' => ['label' => 'Da giao', 'icon' => 'house-check'],
	        'cancelled' => ['label' => 'Da huy', 'icon' => 'x-circle'],
	    ];
	    $statusOrder = ['pending', 'confirmed', 'shipping', 'delivered'];
	    $statusRank = [
	        'pending' => 0,
	        'confirmed' => 1,
	        'shipping' => 2,
	        'delivered' => 3,
	        'cancelled' => 3,
	    ];
	    $currentStatus = (string)($order['status'] ?? 'pending');
	}

// Progress
	$currentIndex = array_search($currentStatus, $statusOrder, true);
	$currentRank = $statusRank[$currentStatus] ?? ($currentIndex !== false ? (int)$currentIndex : 0);
	if ($currentIndex === false) {
	    $progressPercent = 0;
	} else {
	    $progressPercent = (($currentIndex + 1) / count($statusOrder)) * 100;
    $progressPercent = min(100, max(0, (int)$progressPercent));
}

include 'includes/header.php';
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>

<!-- Main Content -->
<div class="container py-5 mt-5">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="display-6 fw-bold text-success mb-2">
                        <i class="bi bi-truck me-2"></i>Theo dõi đơn hàng
                    </h1>
                    <p class="text-muted mb-0">
                        Đơn hàng #<?php echo str_pad($order_id, 6, '0', STR_PAD_LEFT); ?>
                    </p>
                </div>
                <div class="d-flex gap-2">
                    <a href="order-detail.php?id=<?php echo $order_id; ?>" class="btn btn-outline-primary">
                        <i class="bi bi-receipt me-2"></i>Xem chi tiết
                    </a>
                    <a href="my-orders.php" class="btn btn-outline-success">
                        <i class="bi bi-list-ul me-2"></i>Đơn hàng của tôi
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Tracking Timeline -->
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-map me-2"></i>Bản đồ hành trình
                    </h5>
                    <small class="text-muted">Tự cập nhật mỗi 30 giây</small>
                </div>
                <div class="card-body">
                    <div id="trackingMap" style="height: 380px; border-radius: 12px; overflow: hidden;"></div>
                    <div id="trackingMapStatus" class="small text-muted mt-2"></div>
                    <div class="small text-muted mt-2">
                        Điểm xuất phát: <strong>328 Ngô Quyền (Kho Goodwill)</strong>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <i class="bi bi-geo-alt me-2"></i>Trạng thái vận chuyển
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-3 text-muted">
                        <i class="bi bi-truck me-1"></i><?php echo htmlspecialchars($carrierLabel); ?>
                        <?php if ($trackingUrl): ?>
                            - <a href="<?php echo htmlspecialchars($trackingUrl); ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars($trackingCode); ?></a>
                        <?php else: ?>
                            - <span class="text-decoration-underline"><?php echo htmlspecialchars($trackingCode); ?></span>
                        <?php endif; ?>
                        <?php if ($shippingFee > 0): ?>
                            <div>Phi VC: <strong><?php echo number_format($shippingFee); ?></strong> VND</div>
                        <?php endif; ?>
                    </div>
                    <!-- Progress bar -->
                    <div class="mb-4">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Tiến độ đơn hàng</span>
                            <strong><?php echo $progressPercent; ?>%</strong>
                        </div>
                        <div class="progress" style="height: 22px;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated bg-success"
                                 role="progressbar"
                                 style="width: <?php echo $progressPercent; ?>%">
                                <?php echo $progressPercent; ?>%
                            </div>
                        </div>
                    </div>

                    <!-- Steps -->
                    <div class="d-flex justify-content-between tracking-steps mb-4">
                        <?php foreach ($statusOrder as $statusKey): ?>
                            <?php
                            $stepInfo   = $steps[$statusKey];
                            $isActive   = ($statusOrder && array_search($statusKey, $statusOrder, true) <= $currentIndex);
                            $isCurrent  = ($statusKey === $currentStatus);
                            $stepClass  = $isActive ? 'step-active' : 'step-inactive';
                            if ($currentStatus === 'cancelled') {
                                $stepClass = $statusKey === 'pending' ? 'step-cancelled' : 'step-inactive';
                            }
                            ?>
                            <div class="tracking-step <?php echo $stepClass; ?>">
                                <div class="step-icon">
                                    <i class="bi bi-<?php echo $stepInfo['icon']; ?>"></i>
                                </div>
                                <div class="step-label">
                                    <?php echo $stepInfo['label']; ?>
                                </div>
                                <?php if ($isCurrent): ?>
                                    <div class="step-current">Hiện tại</div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Status history -->
                    <h6 class="fw-bold mb-3">
                        <i class="bi bi-clock-history me-2"></i>Lịch sử cập nhật
                    </h6>
                    <?php
                    $orderStatusTexts = [
                        'pending'   => 'Cho xu ly',
                        'confirmed' => 'Da xac nhan',
                        'shipping'  => 'Dang giao',
                        'delivered' => 'Da giao',
                        'cancelled' => 'Da huy'
                    ];
                    
                    $filteredHistory = [];
                    $lastRenderedKey = null;
                    foreach ($statusHistory as $history) {
                        $rawKey = (string)($history['new_status'] ?? '');
                        $isLogisticsKey = str_starts_with($rawKey, 'logistics:');
                        $key = $isLogisticsKey ? substr($rawKey, strlen('logistics:')) : $rawKey;
                    
                        if ($useLogistics) {
                            if (!$isLogisticsKey || !isset($steps[$key])) {
                                continue;
                            }
                    
	                            $idx = $statusRank[$key] ?? null;
	                            if ($idx !== null && $idx > $currentRank) {
	                                continue;
	                            }
                    
                            if ($lastRenderedKey === $key) {
                                continue;
                            }
                    
                            $filteredHistory[] = [
                                'title' => $steps[$key]['label'],
                                'created_at' => (string)($history['created_at'] ?? ''),
                                'note' => (string)($history['note'] ?? ''),
                            ];
                            $lastRenderedKey = $key;
                        } else {
                            if ($isLogisticsKey) {
                                continue;
                            }
                            if ($key === '') {
                                continue;
                            }
                    
                            if ($lastRenderedKey === $key) {
                                continue;
                            }
                    
                            $filteredHistory[] = [
                                'title' => $orderStatusTexts[$key] ?? $key,
                                'created_at' => (string)($history['created_at'] ?? ''),
                                'note' => (string)($history['note'] ?? ''),
                            ];
                            $lastRenderedKey = $key;
                        }
                    }
                    ?>
                    
                    <?php if (!empty($filteredHistory)): ?>
                        <div class="timeline">
                            <?php foreach ($filteredHistory as $history): ?>
                                <div class="timeline-item">
                                    <div class="timeline-marker"></div>
                                    <div class="timeline-content">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($history['title']); ?></h6>
                                        <?php if (!empty($history['created_at'])): ?>
                                            <p class="text-muted small mb-1"><?php echo date('d/m/Y H:i:s', strtotime($history['created_at'])); ?></p>
                                        <?php endif; ?>
                                        <?php if (!empty($history['note'])): ?>
                                            <p class="small mb-0"><?php echo htmlspecialchars($history['note']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted mb-0">
                            Chua c¢ l?ch s? tr?ng th i chi ti?t cho don h…ng n…y.
                        </p>
                    <?php endif; ?>
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
                    <div class="mb-3">
                        <h6 class="text-success mb-3">Thông tin giao hàng</h6>
                        <p class="mb-1"><strong>Người nhận:</strong> <?php echo htmlspecialchars($order['shipping_name'] ?? $order['user_name']); ?></p>
                        <p class="mb-1"><strong>Số điện thoại:</strong> <?php echo htmlspecialchars($order['shipping_phone'] ?? ''); ?></p>
                        <p class="mb-1"><strong>Địa chỉ:</strong> <?php echo htmlspecialchars($order['shipping_address'] ?? ''); ?></p>
                    </div>

                    <hr>

                    <div class="mb-3">
                        <h6 class="text-success mb-3">Thông tin thanh toán</h6>
                        <p class="mb-1"><strong>Trạng thái đơn:</strong>
                            <?php
                            $statusText = $steps[$currentStatus]['label'] ?? $currentStatus;
                            echo htmlspecialchars($statusText);
                            ?>
                        </p>
                        <p class="mb-1"><strong>Ngày đặt:</strong> <?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></p>
                        <p class="mb-1"><strong>Cập nhật cuối:</strong> <?php echo date('d/m/Y H:i', strtotime($order['updated_at'])); ?></p>
                    </div>

                    <hr>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Tổng tiền:</span>
                            <span class="fw-bold text-success fs-5">
                                <?php echo $order['total_amount'] > 0 ? number_format($order['total_amount']) . ' VNĐ' : 'Miễn phí'; ?>
                            </span>
                        </div>
                        <small class="text-muted">
                            * Phi van chuyen: <?php echo $shippingFee > 0 ? number_format($shippingFee) . ' VND' : 'Mien phi'; ?>
                        </small>
                    </div>

                    <div class="d-grid gap-2">
                        <a href="order-detail.php?id=<?php echo $order_id; ?>" class="btn btn-outline-primary">
                            <i class="bi bi-receipt me-2"></i>Xem chi tiết đơn
                        </a>
                        <a href="my-orders.php" class="btn btn-outline-success">
                            <i class="bi bi-list-ul me-2"></i>Danh sách đơn hàng
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.tracking-steps {
    gap: 10px;
}
.tracking-step {
    flex: 1;
    text-align: center;
}
.tracking-step .step-icon {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    margin: 0 auto 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #e9ecef;
    color: #6c757d;
}
.tracking-step.step-active .step-icon {
    background: #198754;
    color: #fff;
}
.tracking-step.step-cancelled .step-icon {
    background: #dc3545;
    color: #fff;
}
.tracking-step .step-label {
    font-size: 0.85rem;
}
.tracking-step .step-current {
    font-size: 0.75rem;
    color: #198754;
}

/* Timeline reused from order-detail */
.timeline {
    position: relative;
    padding-left: 30px;
}
.timeline-item {
    position: relative;
    margin-bottom: 20px;
}
.timeline-marker {
    position: absolute;
    left: -35px;
    top: 5px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    border: 3px solid #fff;
    box-shadow: 0 0 0 3px #dee2e6;
    background-color: #198754;
}
.timeline-content {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    border-left: 3px solid #dee2e6;
}

.gw-vehicle-icon {
    background: transparent;
    border: 0;
}
.gw-vehicle {
    width: 34px;
    height: 34px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    border: 2px solid #fff;
    filter: drop-shadow(0 2px 8px rgba(0,0,0,0.35));
    transform-origin: 50% 50%;
}
.gw-vehicle i {
    font-size: 18px;
    line-height: 1;
    color: #fff;
}
.gw-vehicle--shipper {
    background: #0d6efd;
}
.gw-vehicle--truck {
    background: #fd7e14;
}
</style>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script>
(function () {
    const orderId = <?php echo (int)$order_id; ?>;
    const apiUrl = 'api/get-order-tracking-events.php?order_id=' + encodeURIComponent(orderId);
    const currentStatus = <?php echo json_encode((string)$currentStatus); ?>;
    const useLogistics = <?php echo json_encode((bool)$useLogistics); ?>;
    const isAdmin = <?php echo json_encode((bool)$isAdmin); ?>;

    const map = L.map('trackingMap', { zoomControl: true });
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    const layerGroup = L.layerGroup().addTo(map);
    const vehicleLayer = L.layerGroup().addTo(map);
    let routeLayer = null;
    const mapStatusEl = document.getElementById('trackingMapStatus');
    let lastFingerprint = '';
    let vehicleMarker = null;
    let routeFingerprint = '';
    let animHandle = null;
    let animStartMs = 0;
    let animDurationMs = 0;
    let routeSegments = [];
    let cumulativeDistances = [];
    let totalMeters = 0;

    function safeText(value) {
        return (value == null ? '' : String(value));
    }

    function haversineMeters(a, b) {
        const R = 6371000;
        const toRad = (d) => (d * Math.PI) / 180;
        const lat1 = toRad(a[0]);
        const lat2 = toRad(b[0]);
        const dLat = toRad(b[0] - a[0]);
        const dLng = toRad(b[1] - a[1]);
        const s = Math.sin(dLat / 2) ** 2 + Math.cos(lat1) * Math.cos(lat2) * Math.sin(dLng / 2) ** 2;
        return 2 * R * Math.asin(Math.min(1, Math.sqrt(s)));
    }

    function bearingDeg(a, b) {
        const toRad = (d) => (d * Math.PI) / 180;
        const toDeg = (r) => (r * 180) / Math.PI;
        const lat1 = toRad(a[0]);
        const lat2 = toRad(b[0]);
        const dLng = toRad(b[1] - a[1]);
        const y = Math.sin(dLng) * Math.cos(lat2);
        const x = Math.cos(lat1) * Math.sin(lat2) - Math.sin(lat1) * Math.cos(lat2) * Math.cos(dLng);
        return (toDeg(Math.atan2(y, x)) + 360) % 360;
    }

    async function fetchRoadRoute(points) {
        if (!Array.isArray(points) || points.length < 2) return null;

        // Cache by rounded coords to reduce requests
        const cacheKey = 'gw_route:' + points.map(p => `${p[0].toFixed(5)},${p[1].toFixed(5)}`).join('|');
        try {
            const cached = localStorage.getItem(cacheKey);
            if (cached) {
                const parsed = JSON.parse(cached);
                if (parsed && Array.isArray(parsed.coords) && parsed.coords.length >= 2) return parsed.coords;
            }
        } catch (e) {}

        const coordStr = points.map(p => `${p[1]},${p[0]}`).join(';'); // lng,lat
        const url = `https://router.project-osrm.org/route/v1/driving/${coordStr}?overview=full&geometries=geojson&steps=false`;

        const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
        if (!res.ok) return null;
        const data = await res.json();
        const route = data && Array.isArray(data.routes) ? data.routes[0] : null;
        const coords = route && route.geometry && Array.isArray(route.geometry.coordinates) ? route.geometry.coordinates : null;
        if (!coords || coords.length < 2) return null;

        const latlngs = coords.map(c => [c[1], c[0]]);
        try { localStorage.setItem(cacheKey, JSON.stringify({ coords: latlngs, ts: Date.now() })); } catch (e) {}
        return latlngs;
    }

    async function geocodeAddress(address) {
        const trimmed = safeText(address).trim();
        if (!trimmed) return null;

        const cacheKey = 'gw_geocode:' + trimmed.toLowerCase();
        try {
            const cached = localStorage.getItem(cacheKey);
            if (cached) {
                const parsed = JSON.parse(cached);
                if (parsed && typeof parsed.lat === 'number' && typeof parsed.lng === 'number') return parsed;
            }
        } catch (e) {}

        const url = 'https://nominatim.openstreetmap.org/search?format=json&limit=1&countrycodes=vn&q=' + encodeURIComponent(trimmed);
        const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
        if (!res.ok) return null;
        const data = await res.json();
        const first = Array.isArray(data) ? data[0] : null;
        if (!first || !first.lat || !first.lon) return null;

        const result = { lat: parseFloat(first.lat), lng: parseFloat(first.lon) };
        try { localStorage.setItem(cacheKey, JSON.stringify(result)); } catch (e) {}
        return result;
    }

    function fingerprint(events) {
        if (!Array.isArray(events) || events.length === 0) return '';
        const last = events[events.length - 1];
        return [events.length, safeText(last.occurred_at), safeText(last.title), safeText(last.location_address)].join('|');
    }

    async function hydrateCoords(event) {
        if (event.lat != null && event.lng != null) return event;
        const geo = await geocodeAddress(event.location_address || '');
        if (!geo) return event;
        return Object.assign({}, event, geo);
    }

    function isShippingMode() {
        const s = safeText(currentStatus).toLowerCase();
        if (!s) return false;
        if (useLogistics) {
            return ['created', 'waiting_pickup', 'picked_up', 'in_transit', 'out_for_delivery'].includes(s);
        }
        return s === 'shipping';
    }

    function buildVehicleIcon(vehicleType, rotationDeg) {
        const rot = Number.isFinite(rotationDeg) ? rotationDeg : 0;
        const type = safeText(vehicleType).toLowerCase();
        // Use icons that exist in Bootstrap Icons reliably
        const iconClass = type === 'truck' ? 'bi-truck' : 'bi-bicycle';
        const html = `
            <div class="gw-vehicle ${type === 'truck' ? 'gw-vehicle--truck' : 'gw-vehicle--shipper'}" style="transform: rotate(${rot}deg)">
                <i class="bi ${iconClass}"></i>
            </div>
        `;
        return L.divIcon({
            className: 'gw-vehicle-icon',
            html,
            iconSize: [34, 34],
            iconAnchor: [17, 17],
        });
    }

    function cancelAnimation() {
        if (animHandle) {
            cancelAnimationFrame(animHandle);
            animHandle = null;
        }
        animStartMs = 0;
        animDurationMs = 0;
        routeSegments = [];
        cumulativeDistances = [];
        totalMeters = 0;
    }

    function pointAtDistance(meters) {
        if (!routeSegments.length || totalMeters <= 0) return null;
        const target = Math.min(Math.max(0, meters), totalMeters);
        let segIdx = 0;
        while (segIdx < cumulativeDistances.length && cumulativeDistances[segIdx] < target) segIdx++;

        const prevCum = segIdx === 0 ? 0 : cumulativeDistances[segIdx - 1];
        const seg = routeSegments[Math.min(segIdx, routeSegments.length - 1)];
        const segLen = seg.len;
        if (segLen <= 0) return { latlng: seg.b, rotation: 0 };

        const t = Math.min(1, Math.max(0, (target - prevCum) / segLen));
        const lat = seg.a[0] + (seg.b[0] - seg.a[0]) * t;
        const lng = seg.a[1] + (seg.b[1] - seg.a[1]) * t;
        const rot = bearingDeg(seg.a, seg.b);
        return { latlng: [lat, lng], rotation: rot };
    }

    function vehicleTypeForStatus() {
        const s = safeText(currentStatus).toLowerCase();
        if (useLogistics) {
            if (['in_transit', 'picked_up'].includes(s)) return 'truck';
            if (s === 'out_for_delivery') return 'scooter';
            if (['created', 'waiting_pickup'].includes(s)) return 'truck';
            return 'scooter';
        }
        return s === 'shipping' ? 'scooter' : 'scooter';
    }

    function ensureVehicleMarker(latlng, rotation) {
        const icon = buildVehicleIcon(vehicleTypeForStatus(), rotation);
        if (!vehicleMarker) {
            vehicleMarker = L.marker(latlng, { icon, interactive: false, zIndexOffset: 1000 }).addTo(vehicleLayer);
        } else {
            vehicleMarker.setIcon(icon);
            vehicleMarker.setLatLng(latlng);
        }
    }

    function syntheticTransitStops(start, end, count) {
        const stops = [];
        if (!start || !end || count <= 0) return stops;

        // deterministic pseudo-random offset by orderId
        const seed = (orderId * 9301 + 49297) % 233280;
        const rand01 = (n) => ((seed + n * 9301) % 233280) / 233280;

        for (let i = 1; i <= count; i++) {
            const t = i / (count + 1);
            const lat = start[0] + (end[0] - start[0]) * t;
            const lng = start[1] + (end[1] - start[1]) * t;

            // perpendicular offset for "station feel" (small)
            const dx = end[1] - start[1];
            const dy = end[0] - start[0];
            const mag = Math.max(1e-9, Math.sqrt(dx * dx + dy * dy));
            const ox = (-dy / mag) * (0.01 * (rand01(i) - 0.5));
            const oy = (dx / mag) * (0.01 * (rand01(i + 7) - 0.5));

            stops.push({
                title: `Trạm trung chuyển ${i}`,
                note: 'Mô phỏng trạm trung chuyển (ViettelPost).',
                lat: lat + oy,
                lng: lng + ox,
            });
        }
        return stops;
    }

    function startVehicleAnimation(points) {
        if (!isShippingMode()) {
            vehicleLayer.clearLayers();
            vehicleMarker = null;
            cancelAnimation();
            if (mapStatusEl) mapStatusEl.textContent = '';
            return;
        }

        if (!Array.isArray(points) || points.length < 2) {
            if (mapStatusEl) {
                mapStatusEl.textContent = `Chưa đủ tọa độ để mô phỏng xe chạy (cần ít nhất 2 điểm). status=${safeText(currentStatus)}, points=${Array.isArray(points) ? points.length : 0}`;
            }
            return;
        }
        const mode = safeText(currentStatus).toLowerCase();
        const vehicleLabel = (vehicleTypeForStatus() === 'truck') ? 'Xe tải' : 'Xe máy giao hàng';
        if (mapStatusEl) mapStatusEl.textContent = `${vehicleLabel} đang di chuyển (mô phỏng 80km/h). status=${safeText(currentStatus)}, points=${points.length}`;

        // Add synthetic transit stations for in_transit when only start/end
        let routePoints = points.slice();
        if (useLogistics && mode === 'in_transit' && points.length === 2) {
            const synth = syntheticTransitStops(points[0], points[1], 2);
            synth.forEach((s) => {
                const latlng = [s.lat, s.lng];
                routePoints.splice(routePoints.length - 1, 0, latlng);
                const html = `
                    <div style="min-width:220px">
                        <div><strong>${safeText(s.title)}</strong></div>
                        <div class="text-muted small">${safeText(s.note)}</div>
                    </div>
                `;
                L.circleMarker(latlng, { radius: 7, color: '#fd7e14', fillColor: '#fd7e14', fillOpacity: 0.85 })
                    .bindPopup(html)
                    .addTo(layerGroup);
            });
        }

        const fp = safeText(currentStatus) + '|' + routePoints.map(p => p.join(',')).join('|');
        if (fp === routeFingerprint && animHandle) return;
        routeFingerprint = fp;

        cancelAnimation();

        routeSegments = [];
        cumulativeDistances = [];
        totalMeters = 0;
        for (let i = 0; i < routePoints.length - 1; i++) {
            const a = routePoints[i];
            const b = routePoints[i + 1];
            const len = haversineMeters(a, b);
            if (len <= 0) continue;
            routeSegments.push({ a, b, len });
            totalMeters += len;
            cumulativeDistances.push(totalMeters);
        }
        if (totalMeters <= 0 || !routeSegments.length) return;

        const speedMps = (80 * 1000) / 3600; // 80km/h
        animDurationMs = Math.max(1000, (totalMeters / speedMps) * 1000);
        animStartMs = performance.now();

        const start = pointAtDistance(0);
        if (start) ensureVehicleMarker(start.latlng, start.rotation);

        const tick = (now) => {
            const elapsed = now - animStartMs;
            const progress = Math.min(1, Math.max(0, elapsed / animDurationMs));
            const pos = pointAtDistance(progress * totalMeters);
            if (pos) ensureVehicleMarker(pos.latlng, pos.rotation);

            if (progress < 1) {
                animHandle = requestAnimationFrame(tick);
            } else {
                animHandle = null;
                const end = pointAtDistance(totalMeters);
                if (end) ensureVehicleMarker(end.latlng, end.rotation);

                if (isAdmin) {
                    fetch('api/mark-order-delivered.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                        body: JSON.stringify({ order_id: orderId })
                    }).then(() => {
                        setTimeout(() => location.reload(), 800);
                    }).catch(() => {});
                } else if (useLogistics && safeText(currentStatus).toLowerCase() === 'out_for_delivery') {
                    fetch('api/auto-deliver-order.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                        body: JSON.stringify({ order_id: orderId })
                    }).then(async (r) => {
                        if (!r.ok) return;
                        const j = await r.json().catch(() => null);
                        if (j && j.success) {
                            setTimeout(() => location.reload(), 800);
                        }
                    }).catch(() => {});
                }
            }
        };

        animHandle = requestAnimationFrame(tick);
    }

    function render(events) {
        layerGroup.clearLayers();
        if (routeLayer) {
            try { map.removeLayer(routeLayer); } catch (e) {}
            routeLayer = null;
        }

        const points = [];
        events.forEach((ev) => {
            const hasCoord = typeof ev.lat === 'number' && !Number.isNaN(ev.lat) && typeof ev.lng === 'number' && !Number.isNaN(ev.lng);
            if (!hasCoord) return;

            const latlng = [ev.lat, ev.lng];
            points.push(latlng);

            const title = safeText(ev.title) || 'Cập nhật';
            const addr = safeText(ev.location_address);
            const time = safeText(ev.occurred_at);
            const note = safeText(ev.note);

            const html = `
                <div style="min-width:220px">
                    <div><strong>${title}</strong></div>
                    ${addr ? `<div class="text-muted">${addr}</div>` : ''}
                    ${time ? `<div class="text-muted small">${time}</div>` : ''}
                    ${note ? `<div style="margin-top:6px">${note}</div>` : ''}
                </div>
            `;

            L.marker(latlng, { title }).bindPopup(html).addTo(layerGroup);
        });

        if (points.length === 1) {
            map.setView(points[0], 13);
        } else {
            map.setView([16.047079, 108.206230], 11);
        }

        // Prefer road-following route (OSRM). Fallback to straight line.
        (async () => {
            if (points.length < 2) {
                startVehicleAnimation(points);
                return;
            }

            const mode = safeText(currentStatus).toLowerCase();
            let routePoints = points.slice();
            if (useLogistics && mode === 'in_transit' && points.length === 2) {
                const synth = syntheticTransitStops(points[0], points[1], 2);
                synth.forEach((s) => {
                    const latlng = [s.lat, s.lng];
                    routePoints.splice(routePoints.length - 1, 0, latlng);
                    const html = `
                        <div style="min-width:220px">
                            <div><strong>${safeText(s.title)}</strong></div>
                            <div class="text-muted small">${safeText(s.note)}</div>
                        </div>
                    `;
                    L.circleMarker(latlng, { radius: 7, color: '#fd7e14', fillColor: '#fd7e14', fillOpacity: 0.85 })
                        .bindPopup(html)
                        .addTo(layerGroup);
                });
            }

            const key = safeText(currentStatus) + '|' + routePoints.map(p => p.join(',')).join('|');
            if (key === routeFingerprint && routeLayer) {
                startVehicleAnimation(routePoints);
                return;
            }

            let road = null;
            try {
                road = await fetchRoadRoute(routePoints);
            } catch (e) {
                road = null;
                if (mapStatusEl) {
                    mapStatusEl.textContent = (mapStatusEl.textContent ? (mapStatusEl.textContent + ' | ') : '') + 'route=failed';
                }
            }
            const path = (road && road.length >= 2) ? road : routePoints;

            routeLayer = L.polyline(path, { color: '#0d6efd', weight: 4, opacity: 0.9 });
            routeLayer.addTo(map);
            try { map.fitBounds(routeLayer.getBounds(), { padding: [24, 24] }); } catch (e) {}

            startVehicleAnimation(path);
        })();
    }

    async function refresh() {
        try {
            const res = await fetch(apiUrl, { headers: { 'Accept': 'application/json' } });
            const data = await res.json();
            if (!data || !data.success) return;

            const events = Array.isArray(data.events) ? data.events : [];
            const fp = fingerprint(events);
            if (fp && fp === lastFingerprint) return;
            lastFingerprint = fp;

            const hydrated = await Promise.all(events.map(hydrateCoords));
            if (mapStatusEl) {
                const withCoords = hydrated.filter(e => typeof e.lat === 'number' && typeof e.lng === 'number').length;
                mapStatusEl.textContent = (mapStatusEl.textContent ? (mapStatusEl.textContent + ' | ') : '') + `events=${hydrated.length}, coords=${withCoords}`;
            }
            render(hydrated);
        } catch (e) {
            // ignore
        }
    }

    map.setView([16.047079, 108.206230], 11);
    refresh();
    setInterval(refresh, 30000);
})();
</script>

<?php include 'includes/footer.php'; ?>
