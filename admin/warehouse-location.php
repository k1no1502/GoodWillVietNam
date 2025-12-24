<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireAdmin();

$logisticsPath = __DIR__ . '/../config/logistics.php';
$config = file_exists($logisticsPath) ? require $logisticsPath : [];
$warehouse = $config['warehouse'] ?? [];

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lat = $_POST['lat'] ?? null;
    $lng = $_POST['lng'] ?? null;
    $address = trim((string)($_POST['address'] ?? ''));
    $lat = ($lat === '' || $lat === null) ? null : (float)$lat;
    $lng = ($lng === '' || $lng === null) ? null : (float)$lng;

    if ($lat === null || $lng === null) {
        $error = 'Thiếu tọa độ lat/lng.';
    } elseif ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
        $error = 'Tọa độ không hợp lệ.';
    } else {
        $config['warehouse'] = array_merge(
            [
                'name' => 'Kho hàng Goodwill',
                'address' => '328 Ngô Quyền, Sơn Trà, Đà Nẵng, Việt Nam',
                'lat' => null,
                'lng' => null,
            ],
            $warehouse,
            [
                'address' => $address !== '' ? $address : ($warehouse['address'] ?? '328 Ngô Quyền, Sơn Trà, Đà Nẵng, Việt Nam'),
                'lat' => $lat,
                'lng' => $lng
            ]
        );

        $php = "<?php\nreturn " . var_export($config, true) . ";\n";
        if (@file_put_contents($logisticsPath, $php) === false) {
            $error = 'Không thể lưu file cấu hình: config/logistics.php';
        } else {
            $success = 'Đã lưu vị trí kho.';
            $warehouse = $config['warehouse'];
        }
    }
}

$warehouseLat = isset($warehouse['lat']) && is_numeric($warehouse['lat']) ? (float)$warehouse['lat'] : 16.047079;
$warehouseLng = isset($warehouse['lng']) && is_numeric($warehouse['lng']) ? (float)$warehouse['lng'] : 108.206230;
$warehouseAddress = (string)($warehouse['address'] ?? '328 Ngô Quyền, Sơn Trà, Đà Nẵng, Việt Nam');
$warehouseName = (string)($warehouse['name'] ?? 'Kho hàng Goodwill');

$pageTitle = 'Cài đặt vị trí kho';
include '../includes/header.php';
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>

<div class="container py-5 mt-5">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-1"><i class="bi bi-geo-alt me-2"></i>Cài đặt vị trí kho</h1>
            <div class="text-muted small">Free: click lên bản đồ hoặc kéo marker để đặt đúng vị trí kho (cố định).</div>
        </div>
        <a href="orders.php" class="btn btn-outline-success"><i class="bi bi-arrow-left me-2"></i>Quay lại</a>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-lg-8">
                    <div class="input-group mb-2">
                        <input id="warehouseSearch" class="form-control" placeholder="Tìm địa chỉ (VD: 328 Ngô Quyền, Sơn Trà, Đà Nẵng)" value="<?php echo htmlspecialchars($warehouseAddress); ?>">
                        <button class="btn btn-outline-primary" type="button" id="btnSearch"><i class="bi bi-search"></i></button>
                    </div>
                    <div id="warehouseMap" style="height: 420px; border-radius: 12px; overflow: hidden; border: 1px solid #e9ecef;"></div>
                </div>
                <div class="col-lg-4">
                    <div class="mb-2"><strong><?php echo htmlspecialchars($warehouseName); ?></strong></div>
                    <div class="small text-muted mb-3" id="warehouseAddressPreview"><?php echo htmlspecialchars($warehouseAddress); ?></div>

                    <form method="POST" class="vstack gap-2">
                        <input type="hidden" id="address" name="address" value="<?php echo htmlspecialchars($warehouseAddress); ?>">
                        <label class="form-label mb-0">Latitude</label>
                        <input class="form-control" id="lat" name="lat" value="<?php echo htmlspecialchars((string)$warehouseLat); ?>" required>

                        <label class="form-label mb-0">Longitude</label>
                        <input class="form-control" id="lng" name="lng" value="<?php echo htmlspecialchars((string)$warehouseLng); ?>" required>

                        <button type="submit" class="btn btn-success mt-2">
                            <i class="bi bi-save me-2"></i>Lưu vị trí kho
                        </button>
                        <div class="small text-muted">Tip: zoom tối đa rồi click đúng vị trí số nhà.</div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const latEl = document.getElementById('lat');
    const lngEl = document.getElementById('lng');
    const addrEl = document.getElementById('address');
    const addrPreviewEl = document.getElementById('warehouseAddressPreview');
    const searchEl = document.getElementById('warehouseSearch');
    const btnSearch = document.getElementById('btnSearch');
    const start = [<?php echo json_encode($warehouseLat); ?>, <?php echo json_encode($warehouseLng); ?>];

    function setInputs(lat, lng, placeId, address) {
        latEl.value = String(lat.toFixed(6));
        lngEl.value = String(lng.toFixed(6));
        if (addrEl && address) addrEl.value = String(address);
        if (addrPreviewEl && address) addrPreviewEl.textContent = String(address);
    }

    window.__initWarehouseLeaflet = async function () {
        const map = L.map('warehouseMap');
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 20,
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        map.setView(start, 16);
        const marker = L.marker(start, { draggable: true }).addTo(map);

        const reverse = async (lat, lng) => {
            try {
                const url = 'https://nominatim.openstreetmap.org/reverse?format=json&accept-language=vi&lat=' + encodeURIComponent(lat) + '&lon=' + encodeURIComponent(lng);
                const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                const json = await res.json();
                const addr = json && (json.display_name || '');
                setInputs(lat, lng, null, addr || (searchEl?.value || ''));
            } catch (e) {}
        };

        marker.on('dragend', () => {
            const p = marker.getLatLng();
            setInputs(p.lat, p.lng, null, searchEl?.value || '');
        });

        map.on('click', (e) => {
            marker.setLatLng(e.latlng);
            setInputs(e.latlng.lat, e.latlng.lng, null, searchEl?.value || '');
        });

        const search = async () => {
            const q = (searchEl?.value || '').trim();
            if (!q) return;
            try {
                const url = 'https://nominatim.openstreetmap.org/search?format=json&limit=1&countrycodes=vn&q=' + encodeURIComponent(q);
                const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                const first = Array.isArray(data) ? data[0] : null;
                if (!first || !first.lat || !first.lon) return;
                const lat = parseFloat(first.lat);
                const lng = parseFloat(first.lon);
                map.setView([lat, lng], 18);
                marker.setLatLng([lat, lng]);
                setInputs(lat, lng, null, first.display_name || q);
            } catch (e) {}
        };

        btnSearch?.addEventListener('click', search);
        searchEl?.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') { e.preventDefault(); search(); }
        });

        // initial
        setInputs(start[0], start[1], null, searchEl?.value || '');
        await reverse(start[0], start[1]);
    };
})();
</script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script>window.__initWarehouseLeaflet && window.__initWarehouseLeaflet();</script>

<?php include '../includes/footer.php'; ?>
