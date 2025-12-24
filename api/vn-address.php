<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=86400');

$type = $_GET['type'] ?? '';
$type = is_string($type) ? $type : '';

$root = dirname(__DIR__);
$dataDir = $root . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'data';

function readJsonFile(string $path): array
{
    if (!is_file($path)) {
        http_response_code(500);
        echo json_encode(['error' => 'Missing data file'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $raw = file_get_contents($path);
    if ($raw === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to read data file'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        http_response_code(500);
        echo json_encode(['error' => 'Invalid JSON data'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    return $decoded;
}

// Data source: https://github.com/madnh/hanhchinhvn (stored locally under assets/data)
if ($type === 'provinces') {
    $provinces = readJsonFile($dataDir . DIRECTORY_SEPARATOR . 'tinh_tp.json');
    $out = [];
    foreach ($provinces as $code => $p) {
        $name = $p['name'] ?? '';
        if ($name === '') continue;
        $out[] = ['code' => (string)$code, 'name' => (string)$name];
    }
    usort($out, fn($a, $b) => strcasecmp($a['name'], $b['name']));
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($type === 'districts') {
    $provinceCode = $_GET['province_code'] ?? '';
    $provinceCode = is_string($provinceCode) ? trim($provinceCode) : '';
    if ($provinceCode === '') {
        http_response_code(400);
        echo json_encode(['error' => 'province_code is required'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $districts = readJsonFile($dataDir . DIRECTORY_SEPARATOR . 'quan_huyen.json');
    $out = [];
    foreach ($districts as $code => $d) {
        if (($d['parent_code'] ?? '') !== $provinceCode) continue;
        $name = $d['name'] ?? '';
        if ($name === '') continue;
        $out[] = ['code' => (string)$code, 'name' => (string)$name];
    }
    usort($out, fn($a, $b) => strcasecmp($a['name'], $b['name']));
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($type === 'wards') {
    $districtCode = $_GET['district_code'] ?? '';
    $districtCode = is_string($districtCode) ? trim($districtCode) : '';
    if ($districtCode === '') {
        http_response_code(400);
        echo json_encode(['error' => 'district_code is required'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $wards = readJsonFile($dataDir . DIRECTORY_SEPARATOR . 'xa_phuong.json');
    $out = [];
    foreach ($wards as $code => $w) {
        if (($w['parent_code'] ?? '') !== $districtCode) continue;
        $name = $w['name'] ?? '';
        if ($name === '') continue;
        $out[] = ['code' => (string)$code, 'name' => (string)$name];
    }
    usort($out, fn($a, $b) => strcasecmp($a['name'], $b['name']));
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Invalid type. Use provinces|districts|wards'], JSON_UNESCAPED_UNICODE);

