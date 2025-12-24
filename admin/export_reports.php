<?php
session_start();

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

requireAdmin();

$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$endDateTime = $end_date . ' 23:59:59';

// Collect data (same as dashboard to keep export consistent)
$stats = getStatistics();

$donationStats = Database::fetchAll("
    SELECT DATE_FORMAT(created_at, '%Y-%m') AS month,
           COUNT(*) AS count,
           SUM(quantity) AS total_quantity,
           SUM(CASE WHEN status = 'approved' THEN quantity ELSE 0 END) AS approved_quantity
    FROM donations
    WHERE created_at BETWEEN ? AND ?
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month ASC
", [$start_date, $endDateTime]);

$categoryStats = Database::fetchAll("
    SELECT c.name, COUNT(*) AS count, SUM(d.quantity) AS total_quantity
    FROM donations d
    LEFT JOIN categories c ON d.category_id = c.category_id
    WHERE d.created_at BETWEEN ? AND ? AND d.status = 'approved'
    GROUP BY c.category_id, c.name
    ORDER BY count DESC
", [$start_date, $endDateTime]);

$topDonors = Database::fetchAll("
    SELECT u.name, u.email, COUNT(*) AS donation_count, SUM(d.quantity) AS total_items
    FROM donations d
    LEFT JOIN users u ON d.user_id = u.user_id
    WHERE d.created_at BETWEEN ? AND ? AND d.status = 'approved'
    GROUP BY u.user_id, u.name, u.email
    ORDER BY donation_count DESC
", [$start_date, $endDateTime]);

$campaignStats = Database::fetchAll("
    SELECT c.name, c.status, c.target_items, c.current_items,
           (SELECT COUNT(*) FROM campaign_donations WHERE campaign_id = c.campaign_id) AS donations_count
    FROM campaigns c
    WHERE c.created_at BETWEEN ? AND ?
    ORDER BY c.created_at DESC
", [$start_date, $endDateTime]);

$inventoryStats = [
    'total' => Database::fetch("SELECT COUNT(*) AS count FROM inventory")['count'] ?? 0,
    'available' => Database::fetch("SELECT COUNT(*) AS count FROM inventory WHERE status = 'available'")['count'] ?? 0,
    'sold' => Database::fetch("SELECT COUNT(*) AS count FROM inventory WHERE status = 'sold'")['count'] ?? 0,
    'free' => Database::fetch("SELECT COUNT(*) AS count FROM inventory WHERE price_type = 'free' AND status = 'available'")['count'] ?? 0,
    'cheap' => Database::fetch("SELECT COUNT(*) AS count FROM inventory WHERE price_type = 'cheap' AND status = 'available'")['count'] ?? 0,
];

$spreadsheet = new Spreadsheet();

// Overview sheet
$overviewSheet = $spreadsheet->getActiveSheet();
$overviewSheet->setTitle('Tong quan');
$overviewSheet->setCellValue('A1', 'Bao cao thong ke');
$overviewSheet->setCellValue('A2', 'Tu ngay');
$overviewSheet->setCellValue('B2', $start_date);
$overviewSheet->setCellValue('A3', 'Den ngay');
$overviewSheet->setCellValue('B3', $end_date);
$overviewSheet->setCellValue('A5', 'Chi so');
$overviewSheet->setCellValue('B5', 'Gia tri');
$overviewSheet->fromArray([
    ['Nguoi dung', $stats['users']],
    ['Luot quyen gop', $stats['donations']],
    ['Tong vat pham', $stats['items']],
    ['Chien dich', $stats['campaigns']],
    ['Kho - Co san', $inventoryStats['available']],
    ['Kho - Mien phi', $inventoryStats['free']],
    ['Kho - Gia re', $inventoryStats['cheap']],
], null, 'A6', true);
$overviewSheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$overviewSheet->getStyle('A5:B5')->getFont()->setBold(true);
$overviewSheet->getStyle('A1:B3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

// Donations by month sheet
$donationSheet = $spreadsheet->createSheet();
$donationSheet->setTitle('Quyen gop theo thang');
$donationSheet->fromArray(['Thang', 'So luot', 'Tong vat pham', 'Vat pham duoc duyet'], null, 'A1');
foreach ($donationStats as $index => $row) {
    $donationSheet->fromArray([
        $row['month'],
        (int) $row['count'],
        (int) $row['total_quantity'],
        (int) ($row['approved_quantity'] ?? 0),
    ], null, 'A' . ($index + 2));
}
$donationSheet->getStyle('A1:D1')->getFont()->setBold(true);

// Category distribution sheet
$categorySheet = $spreadsheet->createSheet();
$categorySheet->setTitle('Danh muc');
$categorySheet->fromArray(['Danh muc', 'So luot', 'Tong vat pham'], null, 'A1');
foreach ($categoryStats as $index => $row) {
    $categorySheet->fromArray([
        $row['name'] ?? 'Khong xac dinh',
        (int) $row['count'],
        (int) $row['total_quantity'],
    ], null, 'A' . ($index + 2));
}
$categorySheet->getStyle('A1:C1')->getFont()->setBold(true);

// Top donors sheet
$donorSheet = $spreadsheet->createSheet();
$donorSheet->setTitle('Top nguoi quyen gop');
$donorSheet->fromArray(['Ho ten', 'Email', 'So lan quyen gop', 'Tong vat pham'], null, 'A1');
foreach ($topDonors as $index => $donor) {
    $donorSheet->fromArray([
        $donor['name'] ?? 'Khach',
        $donor['email'] ?? '',
        (int) $donor['donation_count'],
        (int) $donor['total_items'],
    ], null, 'A' . ($index + 2));
}
$donorSheet->getStyle('A1:D1')->getFont()->setBold(true);

// Campaign statistics sheet
$campaignSheet = $spreadsheet->createSheet();
$campaignSheet->setTitle('Chien dich');
$campaignSheet->fromArray(['Ten', 'Trang thai', 'Muc tieu', 'Da nhan', 'Tien do (%)', 'So quyen gop'], null, 'A1');
foreach ($campaignStats as $index => $campaign) {
    $progress = ($campaign['target_items'] ?? 0) > 0
        ? min(100, round(($campaign['current_items'] / $campaign['target_items']) * 100))
        : 0;
    $campaignSheet->fromArray([
        $campaign['name'] ?? '',
        $campaign['status'] ?? '',
        (int) $campaign['target_items'],
        (int) $campaign['current_items'],
        $progress,
        (int) $campaign['donations_count'],
    ], null, 'A' . ($index + 2));
}
$campaignSheet->getStyle('A1:F1')->getFont()->setBold(true);

// Format columns for all sheets
foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
    foreach (range('A', $sheet->getHighestColumn()) as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
}

$fileName = sprintf(
    'bao-cao-%s-den-%s.xlsx',
    preg_replace('/[^0-9\-]/', '', $start_date),
    preg_replace('/[^0-9\-]/', '', $end_date)
);

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
