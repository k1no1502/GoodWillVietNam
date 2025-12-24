<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once '../config/database.php';
require_once '../includes/functions.php';

requireLogin();

$isAdmin = isAdmin();
$orderId = (int)($_GET['order_id'] ?? 0);

function geocodeAddressCached(string $address): ?array
{
    $address = trim($address);
    if ($address === '') {
        return null;
    }

    $googleConfigPath = __DIR__ . '/../config/google.php';
    $googleConfig = file_exists($googleConfigPath) ? require $googleConfigPath : [];
    $googleMapsKey = trim((string)($googleConfig['maps_api_key'] ?? ''));

    $cacheDir = __DIR__ . '/../cache';
    $cacheFile = $cacheDir . '/geocode.json';
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0755, true);
    }

    if (function_exists('mb_strtolower')) {
        $norm = mb_strtolower($address, 'UTF-8');
    } else {
        $norm = strtolower($address);
    }
    $key = md5($norm);
    $cache = [];
    if (is_file($cacheFile)) {
        $raw = @file_get_contents($cacheFile);
        $decoded = json_decode((string)$raw, true);
        if (is_array($decoded)) {
            $cache = $decoded;
        }
    }

    if (isset($cache[$key]) && is_array($cache[$key])) {
        $hit = $cache[$key];
        if (isset($hit['lat'], $hit['lng']) && is_numeric($hit['lat']) && is_numeric($hit['lng'])) {
            return ['lat' => (float)$hit['lat'], 'lng' => (float)$hit['lng']];
        }
    }

    // Prefer Google Geocoding (more accurate for VN) when API key is configured
    if ($googleMapsKey !== '') {
        $gUrl = 'https://maps.googleapis.com/maps/api/geocode/json?region=vn&language=vi&address=' . rawurlencode($address) . '&key=' . rawurlencode($googleMapsKey);
        $gResp = null;

        if (ini_get('allow_url_fopen')) {
            $ctx = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'timeout' => 4,
                    'header' => "Accept: application/json\r\n",
                ],
            ]);
            $tmp = @file_get_contents($gUrl, false, $ctx);
            if (is_string($tmp) && $tmp !== '') {
                $gResp = $tmp;
            }
        }
        if ($gResp === null && function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $gUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_TIMEOUT => 4,
                CURLOPT_HTTPHEADER => ['Accept: application/json'],
            ]);
            $tmp = curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($code >= 200 && $code < 300 && is_string($tmp) && $tmp !== '') {
                $gResp = $tmp;
            }
        }

        if ($gResp) {
            $gJson = json_decode($gResp, true);
            $first = is_array($gJson) && !empty($gJson['results'][0]) ? $gJson['results'][0] : null;
            $loc = $first['geometry']['location'] ?? null;
            if (is_array($loc) && isset($loc['lat'], $loc['lng']) && is_numeric($loc['lat']) && is_numeric($loc['lng'])) {
                $result = ['lat' => (float)$loc['lat'], 'lng' => (float)$loc['lng']];
                $cache[$key] = $result + ['address' => $address, 'ts' => time(), 'source' => 'google'];
                @file_put_contents($cacheFile, json_encode($cache, JSON_UNESCAPED_UNICODE));
                return $result;
            }
        }
    }

    $url = 'https://nominatim.openstreetmap.org/search?format=json&limit=1&countrycodes=vn&q=' . rawurlencode($address);
    $resp = null;

    // Prefer file_get_contents when allowed
    if (ini_get('allow_url_fopen')) {
        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 4,
                'header' => "User-Agent: GW_VN/1.0 (server geocode)\r\nAccept: application/json\r\n",
            ],
        ]);
        $tmp = @file_get_contents($url, false, $ctx);
        if (is_string($tmp) && $tmp !== '') {
            $resp = $tmp;
        }
    }

    // Fallback to cURL if allow_url_fopen is disabled
    if ($resp === null && function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 4,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'User-Agent: GW_VN/1.0 (server geocode)'
            ],
        ]);
        $tmp = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code >= 200 && $code < 300 && is_string($tmp) && $tmp !== '') {
            $resp = $tmp;
        }
    }

    if (!$resp) {
        return null;
    }
    $json = json_decode($resp, true);
    if (!is_array($json) || empty($json[0]['lat']) || empty($json[0]['lon'])) {
        return null;
    }

    $result = ['lat' => (float)$json[0]['lat'], 'lng' => (float)$json[0]['lon']];
    $cache[$key] = $result + ['address' => $address, 'ts' => time()];
    @file_put_contents($cacheFile, json_encode($cache, JSON_UNESCAPED_UNICODE));

    return $result;
}

function fallbackCoordsForAddress(string $address): array
{
    $a = strtolower($address);
    $fallbacks = [
        'đà nẵng' => ['lat' => 16.047079, 'lng' => 108.206230],
        'da nang' => ['lat' => 16.047079, 'lng' => 108.206230],
        'hà nội' => ['lat' => 21.028511, 'lng' => 105.804817],
        'ha noi' => ['lat' => 21.028511, 'lng' => 105.804817],
        'hồ chí minh' => ['lat' => 10.776889, 'lng' => 106.700806],
        'ho chi minh' => ['lat' => 10.776889, 'lng' => 106.700806],
        'tp hcm' => ['lat' => 10.776889, 'lng' => 106.700806],
        'hcm' => ['lat' => 10.776889, 'lng' => 106.700806],
    ];

    foreach ($fallbacks as $needle => $coords) {
        if (str_contains($a, $needle)) {
            return $coords;
        }
    }

    // Vietnam center-ish fallback
    return ['lat' => 16.0, 'lng' => 107.5];
}

if ($orderId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing order_id.']);
    exit();
}

try {
    $orderParams = [$orderId];
    $hasShippingGeo = false;
    try {
        $hasShippingGeo = !empty(Database::fetchAll("SHOW COLUMNS FROM orders LIKE 'shipping_lat'"));
    } catch (Exception $e) {
        $hasShippingGeo = false;
    }

    $orderSql = $hasShippingGeo
        ? "SELECT order_id, user_id, created_at, shipping_address, shipping_lat, shipping_lng, shipping_place_id FROM orders WHERE order_id = ?"
        : "SELECT order_id, user_id, created_at, shipping_address FROM orders WHERE order_id = ?";
    if (!$isAdmin) {
        $orderSql .= " AND user_id = ?";
        $orderParams[] = (int)$_SESSION['user_id'];
    }

    $order = Database::fetch($orderSql, $orderParams);
    if (!$order) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Order not found.']);
        exit();
    }

    $logisticsConfigPath = __DIR__ . '/../config/logistics.php';
    $logisticsConfig = file_exists($logisticsConfigPath) ? require $logisticsConfigPath : [];
    $warehouse = $logisticsConfig['warehouse'] ?? [
        'name' => 'Kho hàng',
        'address' => '328 Ngô Quyền, Mân Thái, Sơn Trà, Đà Nẵng, Việt Nam',
        'lat' => null,
        'lng' => null,
    ];

    $events = [];
    $warehouseLat = $warehouse['lat'] ?? null;
    $warehouseLng = $warehouse['lng'] ?? null;
    $warehousePlaceId = trim((string)($warehouse['place_id'] ?? ''));
    if (($warehouseLat === null || $warehouseLng === null) && !empty($warehouse['address'])) {
        $geo = geocodeAddressCached((string)$warehouse['address']);
        if ($geo) {
            $warehouseLat = $geo['lat'];
            $warehouseLng = $geo['lng'];
        }
    }

    $events[] = [
        'type' => 'warehouse',
        'status_code' => 'warehouse',
        'title' => $warehouse['name'] ?? 'Kho hàng',
        'note' => 'Đơn hàng được xử lý tại kho.',
        'location_address' => $warehouse['address'] ?? '05 Nguyễn Sơn Trà',
        'lat' => $warehouseLat,
        'lng' => $warehouseLng,
        'place_id' => $warehousePlaceId !== '' ? $warehousePlaceId : null,
        'occurred_at' => (string)($order['created_at'] ?? ''),
    ];

    try {
        $dbEvents = Database::fetchAll(
            "SELECT event_id, status_code, title, note, location_address, lat, lng, occurred_at
             FROM order_tracking_events
             WHERE order_id = ?
             ORDER BY occurred_at ASC, event_id ASC",
            [$orderId]
        );

        foreach ($dbEvents as $row) {
            $events[] = [
                'type' => 'event',
                'event_id' => (int)$row['event_id'],
                'status_code' => (string)($row['status_code'] ?? ''),
                'title' => (string)($row['title'] ?? ''),
                'note' => (string)($row['note'] ?? ''),
                'location_address' => (string)($row['location_address'] ?? ''),
                'lat' => $row['lat'] !== null ? (float)$row['lat'] : null,
                'lng' => $row['lng'] !== null ? (float)$row['lng'] : null,
                'occurred_at' => (string)($row['occurred_at'] ?? ''),
            ];
        }
    } catch (Exception $e) {
        $dbEvents = [];
    }

    $destinationAddress = trim((string)($order['shipping_address'] ?? ''));
    if ($destinationAddress !== '') {
        $destLat = null;
        $destLng = null;
        $storedLat = $hasShippingGeo ? ($order['shipping_lat'] ?? null) : null;
        $storedLng = $hasShippingGeo ? ($order['shipping_lng'] ?? null) : null;
        if ($storedLat !== null && $storedLng !== null && is_numeric($storedLat) && is_numeric($storedLng)) {
            $destLat = (float)$storedLat;
            $destLng = (float)$storedLng;
        } else {
            $geo = geocodeAddressCached($destinationAddress);
            if ($geo) {
                $destLat = $geo['lat'];
                $destLng = $geo['lng'];
            } else {
                $fallback = fallbackCoordsForAddress($destinationAddress);
                $destLat = $fallback['lat'];
                $destLng = $fallback['lng'];
            }
        }
        $events[] = [
            'type' => 'destination',
            'status_code' => 'destination',
            'title' => 'Điểm giao hàng',
            'note' => 'Đang vận chuyển đến địa chỉ nhận hàng.',
            'location_address' => $destinationAddress,
            'lat' => $destLat,
            'lng' => $destLng,
            'occurred_at' => date('Y-m-d H:i:s'),
        ];
    }

    echo json_encode([
        'success' => true,
        'order_id' => (int)$order['order_id'],
        'destination_address' => $destinationAddress,
        'warehouse' => $warehouse,
        'events' => $events,
    ]);
} catch (Exception $e) {
    error_log('get-order-tracking-events error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to fetch tracking events.']);
}
