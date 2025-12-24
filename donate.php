<?php
header('Content-Type: text/html; charset=utf-8');
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

requireLogin();

/**
 * Fallback placeholder image for donations without uploads/links.
 */
function buildPlaceholderSvg(string $label, string $bgColor = '#f0f4ff', string $textColor = '#1d4ed8'): string
{
    $label = trim($label);
    if ($label === '') {
        $label = 'Quyên góp';
    }
    $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="400" height="260">
    <rect width="100%" height="100%" rx="28" fill="{$bgColor}"/>
    <text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle"
          font-size="42" font-family="Arial, Helvetica, sans-serif" fill="{$textColor}">
        {$label}
    </text>
</svg>
SVG;
    return 'data:image/svg+xml;base64,' . base64_encode($svg);
}

function getDonationPlaceholder(string $itemName, ?string $categoryName = null): string
{
    if ($categoryName && trim($categoryName) !== '') {
        return buildPlaceholderSvg($categoryName);
    }

    $source = mb_strtolower(trim($itemName));
    $map = [
        'áo' => ['Áo', '#dbeafe', '#1d4ed8'],
        'ao' => ['Áo', '#dbeafe', '#1d4ed8'],
        'quần' => ['Quần', '#fff7ed', '#c2410c'],
        'quan' => ['Quần', '#fff7ed', '#c2410c'],
        'đồ chơi' => ['Đồ chơi', '#fef9c3', '#b45309'],
        'do choi' => ['Đồ chơi', '#fef9c3', '#b45309'],
        'sách' => ['Sách', '#ede9fe', '#6d28d9'],
        'sach' => ['Sách', '#ede9fe', '#6d28d9'],
        'giày' => ['Giày', '#ecfccb', '#3f6212'],
        'giay' => ['Giày', '#ecfccb', '#3f6212'],
        'điện tử' => ['Điện tử', '#e0f2fe', '#0369a1'],
        'dien tu' => ['Điện tử', '#e0f2fe', '#0369a1'],
        'điện thoại' => ['Điện thoại', '#e0f2fe', '#0369a1'],
        'dien thoai' => ['Điện thoại', '#e0f2fe', '#0369a1'],
        'laptop' => ['Laptop', '#e0f2fe', '#0369a1'],
    ];

    foreach ($map as $keyword => $file) {
        if (mb_strpos($source, $keyword) !== false) {
            [$label, $bg, $text] = $file;
            return buildPlaceholderSvg($label, $bg, $text);
        }
    }

    $label = $categoryName ?: 'Quyên góp';
    return buildPlaceholderSvg($label);
}

$pageTitle = "Quyên góp";
$success = '';
$error = '';

/**
 * Convert Excel column letters (e.g., A, B, AA) to zero-based index.
 */
function excelColumnToIndex(string $letters): int
{
    $letters = strtoupper($letters);
    $len = strlen($letters);
    $index = 0;
    for ($i = 0; $i < $len; $i++) {
        $index = $index * 26 + (ord($letters[$i]) - ord('A') + 1);
    }
    return $index - 1;
}

/**
 * Lightweight XLSX reader for the first sheet (returns array of rows).
 * Only uses built-in ZipArchive + SimpleXML.
 */
function readXlsxRows(string $filePath): array
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('Máy chủ chưa bật ZipArchive (bắt buộc để đọc file .xlsx).');
    }

    $rows = [];
    $zip = new ZipArchive();
    if ($zip->open($filePath) !== true) {
        throw new RuntimeException('Không thể mở file Excel (.xlsx).');
    }

    // Shared strings
    $sharedStrings = [];
    $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($sharedXml !== false) {
        $shared = @simplexml_load_string($sharedXml);
        if ($shared && isset($shared->si)) {
            foreach ($shared->si as $si) {
                $text = '';
                foreach ($si->t as $t) {
                    $text .= (string)$t;
                }
                if ($text === '') {
                    $text = (string)$si->t;
                }
                $sharedStrings[] = $text;
            }
        }
    }

    // First worksheet
    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    if ($sheetXml === false) {
        $zip->close();
        throw new RuntimeException('Không tìm thấy dữ liệu sheet1 trong file .xlsx.');
    }
    $sheet = @simplexml_load_string($sheetXml);
    if (!$sheet || !isset($sheet->sheetData->row)) {
        $zip->close();
        throw new RuntimeException('Không thể đọc nội dung sheet1 trong file .xlsx.');
    }

    foreach ($sheet->sheetData->row as $row) {
        $rowData = [];
        foreach ($row->c as $cell) {
            $ref = (string)$cell['r'];
            if (!preg_match('/([A-Z]+)/', $ref, $m)) {
                continue;
            }
            $colIndex = excelColumnToIndex($m[1]);
            $type = (string)$cell['t'];
            $value = '';
            if ($type === 's') {
                $idx = (int)$cell->v;
                $value = $sharedStrings[$idx] ?? '';
            } elseif ($type === 'inlineStr') {
                $value = (string)$cell->is->t;
            } else {
                $value = (string)$cell->v;
            }
            $rowData[$colIndex] = $value;
        }
        if (!empty($rowData)) {
            ksort($rowData);
            $rows[] = array_values($rowData);
        }
    }

    $zip->close();
    return $rows;
}

/**
 * Normalize legacy-encoded Vietnamese text to UTF-8.
 */
function normalizeVietnameseText(?string $text): string
{
    if ($text === null || $text === '') {
        return '';
    }
        $detected = safeDetectEncoding($text);
        if ($detected && strtoupper($detected) !== 'UTF-8') {
            $converted = @iconv($detected, 'UTF-8//IGNORE', $text);
            if ($converted !== false) {
                return $converted;
            }
        }
        return $text;
}

/**
 * Safely detect encoding handling environments where specific names may be unsupported.
 */
function safeDetectEncoding(string $text): ?string
{
    // Try mb_detect_encoding with the runtime order first, but filter unsupported names
    $order = mb_detect_order();
    $supportedList = mb_list_encodings();
    $supportedUpper = array_map('strtoupper', $supportedList);
    $orderFiltered = array_values(array_filter((array)$order, function ($e) use ($supportedUpper) {
        return in_array(strtoupper($e), $supportedUpper, true);
    }));
    if (!empty($orderFiltered)) {
        $enc = @mb_detect_encoding($text, $orderFiltered, true);
        if ($enc) {
            return $enc;
        }
    }

    // Candidate encodings to try if the default order fails
    $candidates = ['UTF-8', 'WINDOWS-1258', 'CP1252', 'ISO-8859-1', 'ASCII'];
    $supported = array_map('strtoupper', mb_list_encodings());
    foreach ($candidates as $c) {
        if (!in_array(strtoupper($c), $supported, true)) {
            continue;
        }
        $e = @mb_detect_encoding($text, $c, true);
        if ($e) return $e;
    }
    return null;
}

$success = '';
$error = '';

$categories = Database::fetchAll("SELECT * FROM categories WHERE status = 'active' ORDER BY sort_order, name");
$categoryNameToId = [];
$categoryIdToName = [];
foreach ($categories as $cat) {
    $categoryNameToId[mb_strtolower(trim($cat['name']))] = $cat['category_id'];
    $categoryIdToName[$cat['category_id']] = $cat['name'];
}


// Download remote images for a single item (URLs separated by comma)
function downloadItemImagesFromUrls(string $urlList, string $uploadDir): array
{
    $result = [];
    if (trim($urlList) === '') {
        return $result;
    }
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    $urls = array_filter(array_map('trim', explode(',', $urlList)));
    foreach ($urls as $url) {
        if (!preg_match('~^https?://~i', $url)) {
            continue;
        }
        $ext = pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION);
        $ext = $ext ? '.' . strtolower($ext) : '.jpg';
        $filename = uniqid('donation_', true) . $ext;
        $content = @file_get_contents($url);
        if ($content === false) {
            continue;
        }
        if (file_put_contents($uploadDir . $filename, $content) !== false) {
            $result[] = $filename;
        }
    }
    return $result;
}

// 1) Prefill from uploaded Excel/CSV template (only load data, not insert)
// AJAX preview endpoint for uploaded Excel/CSV (returns parsed rows as JSON)
if (isset($_GET['ajax']) && $_GET['ajax'] === 'excel_preview') {
    header('Content-Type: application/json; charset=utf-8');
    if (!isset($_FILES['donation_excel']) || $_FILES['donation_excel']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'error' => 'No file uploaded or upload error.']);
        exit;
    }
    $filePath = $_FILES['donation_excel']['tmp_name'];
    $ext = strtolower(pathinfo($_FILES['donation_excel']['name'], PATHINFO_EXTENSION));
    $rowsOut = [];
    try {
        if ($ext === 'xlsx') {
            $rows = readXlsxRows($filePath);
            if (!empty($rows)) {
                $rowsOut = $rows;
            }
        } elseif ($ext === 'csv' || $ext === 'xls') {
            if (($handle = fopen($filePath, 'r')) !== false) {
                $header = fgetcsv($handle);
                while (($row = fgetcsv($handle)) !== false) {
                    // Normalize each cell
                    $row = array_map(function ($cell) {
                        if ($cell === null || $cell === '') return '';
                        $detected = safeDetectEncoding($cell);
                        if ($detected && strtoupper($detected) !== 'UTF-8') return mb_convert_encoding($cell, 'UTF-8', $detected);
                        return preg_replace('/^\xEF\xBB\xBF/', '', $cell);
                    }, $row);
                    $rowsOut[] = $row;
                }
                fclose($handle);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Unsupported file extension.']);
            exit;
        }
        echo json_encode(['success' => true, 'rows' => $rowsOut]);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['donation_excel']) && $_FILES['donation_excel']['error'] === UPLOAD_ERR_OK) {
    $filePath = $_FILES['donation_excel']['tmp_name'];
    $ext = strtolower(pathinfo($_FILES['donation_excel']['name'], PATHINFO_EXTENSION));
    $prefill = [
        'item_name' => [],
        'description' => [],
        'category_id' => [],
        'quantity' => [],
        'unit' => [],
        'condition_status' => [],
        'estimated_value' => [],
        'image_urls' => []
    ];

    try {
        if ($ext === 'xlsx') {
            $rows = readXlsxRows($filePath);
            if (!empty($rows)) {
                array_shift($rows); // drop header
                foreach ($rows as $row) {
                    if (count($row) < 7) {
                        continue;
                    }
                    $name = trim($row[0] ?? '');
                    if ($name === '') {
                        continue;
                    }
                    $desc = $row[1] ?? '';
                    $catName = $row[2] ?? '';
                    $qty = $row[3] ?? 1;
                    $unit = $row[4] ?? 'cai';
                    $cond = $row[5] ?? 'good';
                    $value = $row[6] ?? '';
                    $imgUrls = $row[7] ?? '';

                    $catKey = mb_strtolower(trim($catName));
                    $catId = $categoryNameToId[$catKey] ?? 0;
                    $prefill['item_name'][] = $name;
                    $prefill['description'][] = trim($desc);
                    $prefill['category_id'][] = $catId;
                    $prefill['quantity'][] = max(1, (int)$qty);
                    $prefill['unit'][] = $unit !== '' ? $unit : 'cai';
                    $prefill['condition_status'][] = $cond !== '' ? $cond : 'good';
                    $prefill['estimated_value'][] = is_numeric($value) ? (float)$value : 0;
                    $prefill['image_urls'][] = $imgUrls;
                }
            }
        } elseif ($ext === 'csv' || $ext === 'xls') {
            if (($handle = fopen($filePath, 'r')) !== false) {
                // Read header first
                $header = fgetcsv($handle);
                if ($header === false) {
                    throw new RuntimeException('Không thể đọc header từ file CSV.');
                }

                while (($row = fgetcsv($handle)) !== false) {
                    if (count($row) < 7) {
                        continue;
                    }

                    // Normalize encoding per cell
                    $row = array_map(function ($cell) {
                        if ($cell === null || $cell === '') {
                            return '';
                        }
                        $detected = safeDetectEncoding($cell);
                        if ($detected && strtoupper($detected) !== 'UTF-8') {
                            return mb_convert_encoding($cell, 'UTF-8', $detected);
                        }
                        // Remove BOM if present
                        return preg_replace('/^\xEF\xBB\xBF/', '', $cell);
                    }, $row);

                    $name = trim($row[0] ?? '');
                    if ($name === '') {
                        continue;
                    }

                    $desc = trim($row[1] ?? '');
                    $catName = trim($row[2] ?? '');
                    $qty = $row[3] ?? 1;
                    $unit = $row[4] ?? 'cái';
                    $cond = $row[5] ?? 'good';
                    $value = $row[6] ?? '';
                    $imgUrls = $row[7] ?? '';

                    $catKey = mb_strtolower($catName);
                    $catId = $categoryNameToId[$catKey] ?? 0;
                    $prefill['item_name'][] = $name;
                    $prefill['description'][] = $desc;
                    $prefill['category_id'][] = $catId;
                    $prefill['quantity'][] = max(1, (int)$qty);
                    $prefill['unit'][] = $unit !== '' ? $unit : 'cái';
                    $prefill['condition_status'][] = $cond !== '' ? $cond : 'good';
                    $prefill['estimated_value'][] = is_numeric($value) ? (float)$value : 0;
                    $prefill['image_urls'][] = $imgUrls;
                }
                fclose($handle);
            } else {
                throw new RuntimeException('Không thể mở file CSV.');
            }
        }
    } catch (Throwable $e) {
        $error = 'Lỗi khi xử lý file: ' . $e->getMessage();
    }

    if (!empty($prefill['item_name'])) {
        $_POST = array_merge($_POST, $prefill);
        $success = "Đã tải dữ liệu từ file. Vui lòng kiểm tra và bấm gửi Quyên góp.";
    } else {
        $error = "Không đọc được dữ liệu hợp lệ từ file. Vui lòng kiểm tra nội dung.";
    }
}
// 2) X? lư submit thêm quyên góp (không ph?i upload excel)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !(isset($_FILES['donation_excel']) && $_FILES['donation_excel']['error'] === UPLOAD_ERR_OK)) {
    $items = [];
    $itemNames = $_POST['item_name'] ?? [];
    $descriptions = $_POST['description'] ?? [];
    $categoryIds = $_POST['category_id'] ?? [];
    $quantities = $_POST['quantity'] ?? [];
    $units = $_POST['unit'] ?? [];
    $conditions = $_POST['condition_status'] ?? [];
    $values = $_POST['estimated_value'] ?? [];
    $imageLinks = $_POST['image_urls'] ?? [];

    $pickup_city = sanitize($_POST['pickup_city'] ?? '');
    $pickup_district = sanitize($_POST['pickup_district'] ?? '');
    $pickup_ward = sanitize($_POST['pickup_ward'] ?? '');
    $pickup_address = sanitize($_POST['pickup_address'] ?? '');
    $pickup_date = $_POST['pickup_date'] ?? '';
    $pickup_time = $_POST['pickup_time'] ?? '';
    $contact_phone = sanitize($_POST['contact_phone'] ?? '');
    if (empty($pickup_city) || empty($pickup_district) || empty($pickup_ward)) {
        $error = "Vui lòng chọn Thành phố, Quận/Huyện và Phường/Xã.";
    } elseif (empty($pickup_address)) {
        $error = "Vui lòng nhập địa chỉ nhận hàng.";
    }

    $pickup_address_full = trim(implode(', ', array_filter([
        $pickup_address,
        $pickup_ward,
        $pickup_district,
        $pickup_city
    ])));

    $count = is_array($itemNames) ? count($itemNames) : 0;
    if (!$error && $count === 0) {
        $error = "Vui lòng thêm ít nhất 1 vật phẩm.";
    }

    for ($i = 0; !$error && $i < $count; $i++) {
        $name = sanitize($itemNames[$i] ?? '');
        $desc = sanitize($descriptions[$i] ?? '');
        $catId = (int)($categoryIds[$i] ?? 0);
        $qty = (int)($quantities[$i] ?? 1);
        $unit = sanitize($units[$i] ?? "cái");
        $cond = sanitize($conditions[$i] ?? 'good');
        $val = (float)($values[$i] ?? 0);
        if ($name === '') {
            $error = "Vui lòng nhập tên vật phẩm cho tất cả hàng.";
            break;
        }
        if ($catId <= 0) {
            $error = "Vui lòng chọn danh mục cho tất cả hàng.";
            break;
        }
        if ($qty <= 0) {
            $error = "Số lượng mỗi hàng phải lớn hơn 0.";
            break;
        }
        $items[] = [
            '__index' => $i,
            'name' => $name,
            'description' => $desc,
            'category_id' => $catId,
            'category_name' => $categoryIdToName[$catId] ?? '',
            'quantity' => $qty,
            'unit' => $unit,
            'condition_status' => $cond,
            'estimated_value' => $val,
            'image_urls' => trim($imageLinks[$i] ?? '')
        ];
    }

    if (!$error) {
        try {
            Database::beginTransaction();

            $sql = "INSERT INTO donations (user_id, item_name, description, category_id, quantity, unit, 
                    condition_status, estimated_value, images, pickup_address, pickup_date, pickup_time, 
                    contact_phone, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

            foreach ($items as $item) {
                $images = [];
                // tải ảnh từ URL (Nhập từ Excel/CSV)
                if (!empty($item['image_urls'])) {
                    $images = array_merge($images, downloadItemImagesFromUrls($item['image_urls'], 'uploads/donations/'));
                }
                if (isset($_FILES['item_images']['name'][$item['__index']])) {
                    $uploadDir = 'uploads/donations/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    $names = $_FILES['item_images']['name'][$item['__index']];
                    $types = $_FILES['item_images']['type'][$item['__index']];
                    $tmps = $_FILES['item_images']['tmp_name'][$item['__index']];
                    $errors = $_FILES['item_images']['error'][$item['__index']];
                    $sizes = $_FILES['item_images']['size'][$item['__index']];
                    $fileCount = is_array($names) ? count($names) : 0;
                    for ($f = 0; $f < $fileCount; $f++) {
                        if ($errors[$f] === UPLOAD_ERR_OK) {
                            $file = [
                                'name' => $names[$f],
                                'type' => $types[$f],
                                'tmp_name' => $tmps[$f],
                                'error' => $errors[$f],
                                'size' => $sizes[$f]
                            ];
                            $uploadResult = uploadFile($file, $uploadDir);
                            if ($uploadResult['success']) {
                                $images[] = $uploadResult['filename'];
                            }
                        }
                    }
                }

                // Set a representative placeholder if no image provided
                if (empty($images)) {
                    $images[] = getDonationPlaceholder($item['name'], $item['category_name'] ?? '');
                }

                Database::exeCute($sql, [
                    $_SESSION['user_id'],
                    $item['name'],
                    $item['description'],
                    $item['category_id'],
                    $item['quantity'],
                    $item['unit'],
                    $item['condition_status'],
                    $item['estimated_value'],
                    json_encode($images),
                    $pickup_address_full,
                    $pickup_date ?: null,
                    $pickup_time ?: null,
                    $contact_phone
                ]);

                $donation_id = Database::lastInsertId();
                logActivity($_SESSION['user_id'], 'donate', "Created donation #$donation_id: {$item['name']}");
            }

            Database::commit();
            $success = "Quyên góp dă được gửi. Bạn có thể theo dõi trong trang của tôi.";
            $_POST = [];
        } catch (Exception $e) {
            Database::rollback();
            error_log("Donation error: " . $e->getMessage());
            $error = "Có lỗi xảy ra khi gửi quyên góp. Vui llòng thử lỗi.";
        }
    }
}

// D? li?u hi?n th? l?i form
$formItems = [];
if (!empty($_POST['item_name'])) {
    $count = count($_POST['item_name']);
    for ($i = 0; $i < $count; $i++) {
        $formItems[] = [
            'name' => $_POST['item_name'][$i] ?? '',
            'description' => $_POST['description'][$i] ?? '',
            'category_id' => (int)($_POST['category_id'][$i] ?? 0),
            'quantity' => $_POST['quantity'][$i] ?? 1,
            'unit' => $_POST['unit'][$i] ?? 'cái',
            'condition_status' => $_POST['condition_status'][$i] ?? 'good',
            'estimated_value' => $_POST['estimated_value'][$i] ?? '',
            'image_urls' => $_POST['image_urls'][$i] ?? ''
        ];
    }
}
if (empty($formItems)) {
    $formItems[] = [
        'name' => '',
        'description' => '',
        'category_id' => 0,
        'quantity' => 1,
        'unit' => 'cái',
        'condition_status' => 'good',
        'estimated_value' => '',
        'image_urls' => ''
    ];
}

include 'includes/header.php';
?>

<!-- Main Content -->
<div class="container py-5 mt-5">
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <div class="card shadow-lg border-0">
                    <div class="card-header bg-success text-white">
                        <h2 class="card-title mb-0">
                            <i class="bi bi-heart-fill me-2"></i>Quyên góp vật phẩm
                        </h2>
                        <p class="mb-0 mt-2">Chia sẻ yêu thương với cộng đồng</p>
                    </div>
                    
                    <div class="card-body p-4">
                        <div class="mb-3 d-flex flex-wrap gap-2 align-items-center">
                            <a href="assets/excel/donation_template.xlsx" class="btn btn-outline-primary" download>
                                <i class="bi bi-download me-1"></i>Tải mẫu Excel (File .xlsx)
                            </a>
                            <a href="assets/excel/donation_template.csv" class="btn btn-outline-secondary">
                                <i class="bi bi-file-earmark-spreadsheet me-1"></i>Mẫu CSV (UTF-8)
                            </a>
                            <form id="excel-upload-form" action="" method="post" enctype="multipart/form-data" class="d-inline">
                                <label class="btn btn-outline-success mb-0">
                                    <i class="bi bi-upload me-1"></i>Nhập từ Excel
                                    <input id="donation_excel_input" type="file" name="donation_excel" accept=".csv,.xls,.xlsx" style="display:none">
                                </label>
                            </form>
                            <small class="text-muted">Upload hỗ trợ CSV UTF-8, XLS và XLSX (khuuyến nghị dùng file .csv và xlsx theo mẫu).</small>
                        </div>
                        <?php if ($success): ?>
                            <div class="alert alert-success" role="alert">
                                <i class="bi bi-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($error): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                            <div id="items-container">
                                <?php foreach ($formItems as $idx => $fi): ?>
                                <div class="item-block border rounded-3 p-3 mb-3 bg-light position-relative" data-index="<?php echo $idx; ?>">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h5 class="mb-0">Vật phẩm <span class="item-number"><?php echo $idx + 1; ?></span></h5>
                                        <button type="button" class="btn btn-sm btn-outline-danger remove-item" style="<?php echo count($formItems) > 1 ? "" : "display:none;"; ?>">Xóa</button>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-8 mb-3">
                                            <label class="form-label">Tên vật phẩm *</label>
                                            <input type="text" class="form-control" name="item_name[]" value="<?php echo htmlspecialchars($fi['name']); ?>" placeholder="Ví dụ: áo sơ mi nam, Sách giáo khoa lớp 5...
" required>
                                            <div class="invalid-feedback">Vui lòng nhập tên vật phẩm.</div>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Danh mục *</label>
                                            <select class="form-select" name="category_id[]" required>
                                                <option value="">Chọn danh mục</option>
                                                <?php foreach ($categories as $category): ?>
                                                    <option value="<?php echo $category['category_id']; ?>" <?php echo ($fi['category_id'] == $category['category_id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($category['name']); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <div class="invalid-feedback">Vui lòng chọn danh mục.</div>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Mô tả chi tiết</label>
                                        <textarea class="form-control" name="description[]" rows="3" placeholder="Mô tả tình trạng, kích thước, màu sắc..."><?php echo htmlspecialchars($fi['description']); ?></textarea>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">Số lượng *</label>
                                            <input type="number" class="form-control" name="quantity[]" value="<?php echo htmlspecialchars($fi['quantity']); ?>" min="1" required>
                                            <div class="invalid-feedback">Số lượng phải lớn hơn 0.</div>
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">Đơn vị</label>
                                            <select class="form-select" name="unit[]">
                                                <?php foreach (["cái","bộ","kg","cuốn","thùng"] as $u): ?>
                                                    <option value="<?php echo $u; ?>" <?php echo ($fi['unit'] === $u) ? 'selected' : ''; ?>><?php echo ucfirst($u); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">Tình trạng</label>
                                            <select class="form-select" name="condition_status[]">
                                                <?php
                                                    $condOptions = [
                                                        'new' => 'Mới',
                                                        'like_new' => 'Như mới',
                                                        'good' => 'Tốt',
                                                        'fair' => 'Khá',
                                                        'poor' => 'Cũ'
                                                    ];
                                                    foreach ($condOptions as $k => $text):
                                                ?>
                                                    <option value="<?php echo $k; ?>" <?php echo ($fi['condition_status'] === $k) ? 'selected' : ''; ?>><?php echo $text; ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">Giá trị ước tính (vnd)</label>
                                            <input type="number" class="form-control" name="estimated_value[]" value="<?php echo htmlspecialchars($fi['estimated_value']); ?>" min="0" step="1000">
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Hình ảnh</label>
                                        <input type="file" class="form-control images-input" name="item_images[<?php echo $idx; ?>][]" data-base-name="item_images" id="images-<?php echo $idx; ?>" multiple accept="image/*">
                                        <div class="form-text">Liên kết ảnh cho vật phẩm này.</div>
                                        <div class="image-preview mt-2" id="preview-<?php echo $idx; ?>"></div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Liên kết ảnh (Chỉ nhập đường link)</label>
                                        <textarea class="form-control" name="image_urls[]" rows="2" placeholder="http://example.com/anh1.jpg, http://example.com/anh2.png"><?php echo htmlspecialchars($fi['image_urls']); ?></textarea>
                                        <div class="form-text">Nếu nhập ảnh từ Excel.</div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="mb-3">
                                <button type="button" id="add-item" class="btn btn-outline-primary">
                                    <i class="bi bi-plus-circle me-1"></i>Thêm Vật phẩm
                                </button>
                            </div>

                            <div class="mb-3">
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Thành phố *</label>
                                        <select class="form-select" id="pickup_city" name="pickup_city" required data-selected="<?php echo htmlspecialchars($_POST['pickup_city'] ?? ''); ?>">
                                            <option value="">-- Chọn Thành phố --</option>
                                        </select>
                                        <div class="invalid-feedback">Vui lòng chọn Thành phố</div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Quận/Huyện *</label>
                                        <select class="form-select" id="pickup_district" name="pickup_district" required data-selected="<?php echo htmlspecialchars($_POST['pickup_district'] ?? ''); ?>" disabled>
                                            <option value="">-- Chọn Quận/Huyện --</option>
                                        </select>
                                        <div class="invalid-feedback">Vui lòng chọn Quận/Huyện</div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Phường/Xã *</label>
                                        <select class="form-select" id="pickup_ward" name="pickup_ward" required data-selected="<?php echo htmlspecialchars($_POST['pickup_ward'] ?? ''); ?>" disabled>
                                            <option value="">-- Chọn Phường/Xã --</option>
                                        </select>
                                        <div class="invalid-feedback">Vui lòng chọn Phường/Xã</div>
                                    </div>
                                </div>
                                <label class="form-label">Địa chỉ nhận hàng *</label>
                                <textarea class="form-control" name="pickup_address" rows="2" placeholder="Vui lòng nhập địa chỉ nhận hàng" required><?php echo htmlspecialchars($_POST['pickup_address'] ?? ''); ?></textarea>
                                <div class="invalid-feedback">Vui lòng nhập địa chỉ nhận hàng</div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Ngày Tháng Năm</label>
                                    <input type="date" class="form-control" name="pickup_date" value="<?php echo htmlspecialchars($_POST['pickup_date'] ?? ''); ?>" min="<?php echo date('Y-m-d'); ?>">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Giờ nhận hàng</label>
                                    <input type="time" class="form-control" name="pickup_time" value="<?php echo htmlspecialchars($_POST['pickup_time'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Số điện thoại</label>
                                <input type="tel" class="form-control" name="contact_phone" value="<?php echo htmlspecialchars($_POST['contact_phone'] ?? ''); ?>" placeholder="Vui lòng nhập đúng số điện thoại">
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="bi bi-heart-fill me-2"></i>Gửi quyên góp
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
</div>

<?php include 'includes/footer.php'; ?>
    
    <script>
        // Form validation
        (function() {
            'use strict';
            window.addEventListener('load', function() {
                var forms = document.getElementsByClassName('needs-validation');
                Array.prototype.forEach.call(forms, function(form) {
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

        // Dynamic items
        const container = document.getElementById('items-container');
        const addBtn = document.getElementById('add-item');

        function attachRemoveListener(btn, block) {
            if (!btn) return;
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const targetBlock = block || btn.closest('.item-block');
                if (targetBlock) {
                    targetBlock.remove();
                    updateRemoveButtons();
                }
            });
        }

        addBtn.addEventListener('click', (e) => {
            e.preventDefault();
            const index = container.children.length;
            const block = container.firstElementChild.cloneNode(true);
            block.dataset.index = index;
            block.querySelector('.item-number').textContent = index + 1;
            block.querySelectorAll('input[type="text"], textarea, input[type="number"]').forEach(el => { el.value = ''; });
            block.querySelectorAll('select').forEach(sel => sel.selectedIndex = 0);
            const fileInput = block.querySelector('.images-input');
            const preview = block.querySelector('.image-preview');
            fileInput.name = `item_images[${index}][]`;
            fileInput.id = `images-${index}`;
            preview.id = `preview-${index}`;
            fileInput.value = '';
            preview.innerHTML = '';
            const removeBtn = block.querySelector('.remove-item');
            if (removeBtn) {
                removeBtn.style.display = 'inline-block';
                attachRemoveListener(removeBtn, block);
            }
            container.appendChild(block);
            bindPreview(fileInput, preview);
        });

        function updateRemoveButtons() {
            const blocks = container.querySelectorAll('.item-block');
            blocks.forEach((block, idx) => {
                block.dataset.index = idx;
                block.querySelector('.item-number').textContent = idx + 1;
                const btn = block.querySelector('.remove-item');
                if (btn) btn.style.display = blocks.length > 1 ? 'inline-block' : 'none';
                const fileInput = block.querySelector('.images-input');
                const preview = block.querySelector('.image-preview');
                if (fileInput) {
                    fileInput.name = `item_images[${idx}][]`;
                    fileInput.id = `images-${idx}`;
                }
                if (preview) preview.id = `preview-${idx}`;
            });
        }

        function bindPreview(input, preview) {
            input.addEventListener('change', function(e) {
                const files = e.target.files;
                preview.innerHTML = '';
                if (files.length > 0) {
                    preview.innerHTML = '<h6>Hình ảnh đã chọn:</h6>';
                    Array.from(files).forEach(file => {
                        if (file.type.startsWith('image/')) {
                            const reader = new FileReader();
                            reader.onload = function(evt) {
                                const img = document.createElement('img');
                                img.src = evt.target.result;
                                img.className = 'img-thumbnail me-2 mb-2';
                                img.style.maxWidth = '120px';
                                img.style.maxHeight = '120px';
                                preview.appendChild(img);
                            };
                            reader.readAsDataURL(file);
                        }
                    });
                }
            });
        }

        // Initialize - bind preview for initial blocks and remove buttons
        document.querySelectorAll('.images-input').forEach(input => {
            const idx = input.closest('.item-block').dataset.index;
            const preview = document.getElementById(`preview-${idx}`);
            bindPreview(input, preview);
        });

        document.querySelectorAll('.remove-item').forEach(btn => {
            const block = btn.closest('.item-block');
            attachRemoveListener(btn, block);
        });

        updateRemoveButtons();

        // Excel/CSV upload preview handler
        (function() {
            const excelInput = document.getElementById('donation_excel_input');
            if (!excelInput) return;
            excelInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (!file) return;
                const fd = new FormData();
                fd.append('donation_excel', file);
                fetch(window.location.pathname + '?ajax=excel_preview', {
                    method: 'POST',
                    body: fd
                }).then(r => r.json()).then(data => {
                    if (!data.success) {
                        alert('Upload lỗi: ' + (data.error || 'Không rõ lỗi'));
                        return;
                    }
                    const rows = data.rows || [];
                    if (rows.length <= 1) {
                        alert('Không tìm thấy dữ liệu hợp lệ trong file.');
                        return;
                    }
                    if (!confirm('Xem trước thành công (' + (rows.length - 1) + ' hàng). Bạn muốn điền dữ liệu vào form?')) return;

                    // Remove header and prepare template
                    rows.shift();
                    // Capture existing template before clearing to preserve full layout
                    const template = container.querySelector('.item-block');
                    // Clear existing items
                    container.innerHTML = '';
                    rows.forEach((r, idx) => {
                        let block;
                        if (template) {
                            block = template.cloneNode(true);
                        } else {
                            // Fallback minimal block if template not found
                            block = document.createElement('div');
                            block.className = 'item-block border rounded-3 p-3 mb-3 bg-light position-relative';
                            block.innerHTML = `
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h5 class="mb-0">Vật phẩm <span class="item-number">${idx+1}</span></h5>
                                    <button type="button" class="btn btn-sm btn-outline-danger remove-item">Xóa</button>
                                </div>
                                <div class="mb-3"><input type="text" class="form-control" name="item_name[]" placeholder="Tên vật phẩm"></div>
                                <div class="mb-3"><textarea class="form-control" name="description[]" rows="2" placeholder="Mô tả"></textarea></div>
                                <div class="mb-3"><input type="number" class="form-control" name="quantity[]" value="1"></div>
                                <div class="mb-3"><textarea class="form-control" name="image_urls[]" rows="2" placeholder="http://example.com/anh.jpg"></textarea></div>
                            `;
                        }

                        block.dataset.index = idx;

                        // Reset standard inputs/selects
                        block.querySelectorAll('input[type="text"], textarea, input[type="number"]').forEach(el => { el.value = ''; });
                        block.querySelectorAll('select').forEach(sel => sel.selectedIndex = 0);

                        // Map columns: 0=name,1=desc,2=category,3=qty,4=unit,5=cond,6=value,7=image_urls
                        const nameEl = block.querySelector('input[name="item_name[]"]');
                        if (nameEl) nameEl.value = r[0] || '';
                        const descEl = block.querySelector('textarea[name="description[]"]'); if (descEl) descEl.value = r[1] || '';
                        const catName = (r[2] || '').toString().trim().toLowerCase();
                        const catSelect = block.querySelector('select[name="category_id[]"]');
                        if (catSelect) {
                            let found = false;
                            Array.from(catSelect.options).forEach(opt => {
                                if (opt.text.toString().trim().toLowerCase() === catName) { opt.selected = true; found = true; }
                            });
                            if (!found) catSelect.selectedIndex = 0;
                        }
                        const qtyEl = block.querySelector('input[name="quantity[]"]'); if (qtyEl) qtyEl.value = r[3] || 1;
                        const unitSel = block.querySelector('select[name="unit[]"]'); if (unitSel) {
                            Array.from(unitSel.options).forEach(opt => { if (opt.value === (r[4] || '').toString()) opt.selected = true; });
                        }
                        const condSel = block.querySelector('select[name="condition_status[]"]'); if (condSel) {
                            let v = (r[5] || '').toString().trim().toLowerCase(); let matched = false;
                            Array.from(condSel.options).forEach(opt => { if (opt.value === v || opt.text.toLowerCase() === v) { opt.selected = true; matched = true; } });
                            if (!matched) condSel.selectedIndex = 2;
                        }
                        const valEl = block.querySelector('input[name="estimated_value[]"]'); if (valEl) valEl.value = r[6] || '';
                        const imgEl = block.querySelector('textarea[name="image_urls[]"]'); if (imgEl) imgEl.value = r[7] || '';

                        // Ensure remove button is visible and has listener
                        const removeBtn = block.querySelector('.remove-item');
                        if (removeBtn) {
                            removeBtn.style.display = 'inline-block';
                            attachRemoveListener(removeBtn, block);
                        }

                        // Normalize file input name/id and bind preview
                        const fileInput = block.querySelector('.images-input');
                        const preview = block.querySelector('.image-preview');
                        if (fileInput) {
                            fileInput.name = `item_images[${idx}][]`;
                            fileInput.id = `images-${idx}`;
                            fileInput.value = '';
                        }
                        if (preview) {
                            preview.id = `preview-${idx}`;
                            preview.innerHTML = '';
                        }

                        container.appendChild(block);
                        if (fileInput && preview) bindPreview(fileInput, preview);
                    });
                    updateRemoveButtons();
                }).catch(err => { console.error(err); alert('Lỗi khi upload: ' + err.message); });
            });
        })();

        // Vietnamese address selects (City/District/Ward) via local JSON API
        (function () {
            const cityEl = document.getElementById('pickup_city');
            const districtEl = document.getElementById('pickup_district');
            const wardEl = document.getElementById('pickup_ward');
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
    </script>
</body>
</html>




