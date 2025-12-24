<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

require_once __DIR__ . '/../vendor/autoload.php';

date_default_timezone_set('Asia/Ho_Chi_Minh');

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

$endDate = $_GET['end_date'] ?? date('Y-m-d');
if (!empty($_GET['start_date'])) {
    $startDate = $_GET['start_date'];
} else {
    $startDate = date('Y-m-01', strtotime('-5 months', strtotime($endDate)));
}

if (strtotime($startDate) > strtotime($endDate)) {
    $startDate = date('Y-m-01', strtotime($endDate));
}

$stats = getStatistics();

$donationStats = Database::fetchAll("
    SELECT DATE_FORMAT(created_at, '%Y-%m') as month,
           COUNT(*) as count,
           SUM(quantity) as total_quantity
    FROM donations
    WHERE created_at BETWEEN ? AND ?
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month ASC
", [$startDate, $endDate . ' 23:59:59']);

$categoryStats = Database::fetchAll("
    SELECT c.name, COUNT(*) as count, SUM(d.quantity) as total_quantity
    FROM donations d
    LEFT JOIN categories c ON d.category_id = c.category_id
    WHERE d.created_at BETWEEN ? AND ? AND d.status = 'approved'
    GROUP BY c.category_id, c.name
    ORDER BY count DESC
    LIMIT 10
", [$startDate, $endDate . ' 23:59:59']);

$topDonors = Database::fetchAll("
    SELECT u.name, u.email, COUNT(*) as donation_count, SUM(d.quantity) as total_items
    FROM donations d
    LEFT JOIN users u ON d.user_id = u.user_id
    WHERE d.created_at BETWEEN ? AND ? AND d.status = 'approved'
    GROUP BY u.user_id, u.name, u.email
    ORDER BY donation_count DESC
    LIMIT 10
", [$startDate, $endDate . ' 23:59:59']);

$campaignStats = Database::fetchAll("
    SELECT c.name, c.status, c.target_items, c.current_items,
           (SELECT COUNT(*) FROM campaign_donations WHERE campaign_id = c.campaign_id) as donations_count
    FROM campaigns c
    WHERE c.created_at BETWEEN ? AND ?
    ORDER BY c.created_at DESC
", [$startDate, $endDate . ' 23:59:59']);

$inventoryStats = [
    'total' => Database::fetch("SELECT COUNT(*) as count FROM inventory")['count'],
    'available' => Database::fetch("SELECT COUNT(*) as count FROM inventory WHERE status = 'available'")['count'],
    'sold' => Database::fetch("SELECT COUNT(*) as count FROM inventory WHERE status = 'sold'")['count'],
    'free' => Database::fetch("SELECT COUNT(*) as count FROM inventory WHERE price_type = 'free' AND status = 'available'")['count'],
    'cheap' => Database::fetch("SELECT COUNT(*) as count FROM inventory WHERE price_type = 'cheap' AND status = 'available'")['count'],
];

$donationMonthlyMap = [];
foreach ($donationStats as $row) {
    $donationMonthlyMap[$row['month']] = (int) $row['count'];
}

$donationChartData = [];
$startMonth = new DateTime(date('Y-m-01', strtotime($startDate)));
$endMonth = new DateTime(date('Y-m-01', strtotime($endDate)));
if ($startMonth <= $endMonth) {
    $period = new DatePeriod($startMonth, new DateInterval('P1M'), (clone $endMonth)->modify('+1 month'));
    foreach ($period as $month) {
        $key = $month->format('Y-m');
        $donationChartData[] = [
            'label' => 'Tháng ' . (int) $month->format('n'),
            'value' => $donationMonthlyMap[$key] ?? 0,
            'raw' => $key
        ];
    }
}

$categoryLabels = array_map(function ($row) {
    return $row['name'] ?? 'Khác';
}, $categoryStats);
$categoryValues = array_map(function ($row) {
    return (int) $row['count'];
}, $categoryStats);

$spreadsheet = new Spreadsheet();
$spreadsheet->getProperties()
    ->setCreator('Goodwill Vietnam')
    ->setTitle('Báo cáo thống kê')
    ->setSubject('Báo cáo thống kê')
    ->setDescription('Báo cáo thống kê từ ' . $startDate . ' đến ' . $endDate);

$overview = $spreadsheet->getActiveSheet();
$overview->setTitle('Tong quan');
$overview->fromArray([
    ['Chỉ số', 'Giá trị', 'Màu'],
    ['Tổng người dùng', $stats['users'], '#007bff'],
    ['Tổng quyên góp', $stats['donations'], '#198754'],
    ['Vật phẩm trong kho', $stats['items'], '#0dcaf0'],
    ['Tổng chiến dịch', $stats['campaigns'], '#ffc107']
], null, 'A1');
$overview->getStyle('A1:B1')->getFont()->setBold(true);
$overview->getColumnDimension('A')->setWidth(28);
$overview->getColumnDimension('B')->setWidth(18);
$overview->setCellValue('D1', 'Tu ngay');
$overview->setCellValue('E1', $startDate);
$overview->setCellValue('D2', 'Den ngay');
$overview->setCellValue('E2', $endDate);
$overview->getStyle('D1:E2')->getFont()->setBold(true);

$donationSheet = $spreadsheet->createSheet();
$donationSheet->setTitle('Quyen gop thang');
$donationSheet->fromArray([['Tháng', 'Số quyên góp']], null, 'A1');
foreach ($donationChartData as $index => $item) {
    $row = $index + 2;
    $donationSheet->setCellValue('A' . $row, $item['label']);
    $donationSheet->setCellValue('B' . $row, $item['value']);
}
$donationSheet->getStyle('A1:B1')->getFont()->setBold(true);
$donationSheet->getColumnDimension('A')->setWidth(18);
$donationSheet->getColumnDimension('B')->setWidth(18);
$donationEndRow = count($donationChartData) + 1;
if ($donationEndRow < 2) {
    $donationEndRow = 2;
}
$donationSeries = new DataSeries(
    DataSeries::TYPE_LINECHART,
    DataSeries::GROUPING_STANDARD,
    range(0, 0),
    [new DataSeriesValues('String', "'Quyen gop thang'!\$B\$1", null, 1)],
    [new DataSeriesValues('String', "'Quyen gop thang'!\$A\$2:\$A\$" . $donationEndRow, null, max(1, count($donationChartData)))],
    [new DataSeriesValues('Number', "'Quyen gop thang'!\$B\$2:\$B\$" . $donationEndRow, null, max(1, count($donationChartData)))]
);
$donationPlot = new PlotArea(null, [$donationSeries]);
$donationChart = new Chart(
    'donation_trend',
    new Title('Thống kê quyên góp theo tháng'),
    new Legend(Legend::POSITION_BOTTOM, null, false),
    $donationPlot
);
$donationChart->setTopLeftPosition('D2');
$donationChart->setBottomRightPosition('L18');
$donationSheet->addChart($donationChart);

$categorySheet = $spreadsheet->createSheet();
$categorySheet->setTitle('Phan bo danh muc');
$categorySheet->fromArray([['Danh mục', 'Số lượng']], null, 'A1');
foreach ($categoryStats as $index => $row) {
    $rowNum = $index + 2;
    $categorySheet->setCellValue('A' . $rowNum, $row['name'] ?? 'Khác');
    $categorySheet->setCellValue('B' . $rowNum, $row['count']);
}
$categorySheet->getStyle('A1:B1')->getFont()->setBold(true);
$categorySheet->getColumnDimension('A')->setWidth(25);
$categorySheet->getColumnDimension('B')->setWidth(15);
$categoryEndRow = count($categoryStats) + 1;
if ($categoryEndRow < 2) {
    $categoryEndRow = 2;
}
$categorySeries = new DataSeries(
    DataSeries::TYPE_DONUTCHART,
    null,
    range(0, 0),
    [new DataSeriesValues('String', "'Phan bo danh muc'!\$A\$1", null, 1)],
    [new DataSeriesValues('String', "'Phan bo danh muc'!\$A\$2:\$A\$" . $categoryEndRow, null, max(1, count($categoryStats)))],
    [new DataSeriesValues('Number', "'Phan bo danh muc'!\$B\$2:\$B\$" . $categoryEndRow, null, max(1, count($categoryStats)))]
);
$categorySeries->setPlotDirection(DataSeries::DIRECTION_COL);
$categoryPlot = new PlotArea(null, [$categorySeries]);
$categoryChart = new Chart(
    'category_donut',
    new Title('Phân bố danh mục'),
    new Legend(Legend::POSITION_RIGHT, null, false),
    $categoryPlot
);
$categoryChart->setTopLeftPosition('D2');
$categoryChart->setBottomRightPosition('J18');
$categorySheet->addChart($categoryChart);

$donorSheet = $spreadsheet->createSheet();
$donorSheet->setTitle('Top người quyên góp');
$donorSheet->fromArray([['Người dùng', 'Email', 'Số lần quyên góp', 'Tổng vật phẩm']], null, 'A1');
foreach ($topDonors as $index => $donor) {
    $row = $index + 2;
    $donorSheet->setCellValue('A' . $row, $donor['name'] ?? 'Khách');
    $donorSheet->setCellValue('B' . $row, $donor['email'] ?? '');
    $donorSheet->setCellValue('C' . $row, $donor['donation_count']);
    $donorSheet->setCellValue('D' . $row, $donor['total_items']);
}
$donorSheet->getStyle('A1:D1')->getFont()->setBold(true);
$donorSheet->getColumnDimension('A')->setWidth(25);
$donorSheet->getColumnDimension('B')->setWidth(30);
$donorSheet->getColumnDimension('C')->setWidth(18);
$donorSheet->getColumnDimension('D')->setWidth(18);

$inventorySheet = $spreadsheet->createSheet();
$inventorySheet->setTitle('Kho hàng');
$inventorySheet->fromArray([
    ['Chỉ số', 'Giá trị'],
    ['Tổng vật phẩm', $inventoryStats['total']],
    ['Có sẵn', $inventoryStats['available']],
    ['Đã bán', $inventoryStats['sold']],
    ['Miễn phí', $inventoryStats['free']],
    ['Giá rẻ', $inventoryStats['cheap']]
], null, 'A1');
$inventorySheet->getStyle('A1:B1')->getFont()->setBold(true);
$inventorySheet->getColumnDimension('A')->setWidth(25);
$inventorySheet->getColumnDimension('B')->setWidth(15);

$campaignSheet = $spreadsheet->createSheet();
$campaignSheet->setTitle('Chiến dịch');
$campaignSheet->fromArray([
    ['Tên chiến dịch', 'Trạng thái', 'Mục tiêu', 'Đã nhận', 'Tiến độ (%)', 'Số quyên góp']
], null, 'A1');
foreach ($campaignStats as $index => $campaign) {
    $row = $index + 2;
    $progress = $campaign['target_items'] > 0
        ? min(100, round(($campaign['current_items'] / $campaign['target_items']) * 100))
        : 0;
    $campaignSheet->setCellValue('A' . $row, $campaign['name']);
    $campaignSheet->setCellValue('B' . $row, $campaign['status']);
    $campaignSheet->setCellValue('C' . $row, $campaign['target_items']);
    $campaignSheet->setCellValue('D' . $row, $campaign['current_items']);
    $campaignSheet->setCellValue('E' . $row, $progress);
    $campaignSheet->setCellValue('F' . $row, $campaign['donations_count']);
}
$campaignSheet->getStyle('A1:F1')->getFont()->setBold(true);
foreach (range('A', 'F') as $col) {
    $campaignSheet->getColumnDimension($col)->setWidth(18);
}

$visualSheet = $spreadsheet->createSheet();
$visualSheet->setTitle('Sơ đồ báo cáo');
$reportMonthLabel = 'Báo cáo theo tháng ' . date('m/Y', strtotime($endDate));
$visualSheet->setCellValue('A1', $reportMonthLabel);
$visualSheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

// KPI cards (simulate dashboard tiles)
$kpiCards = [
    ['label' => 'Tổng người dùng', 'value' => $stats['users'], 'color' => '007BFF'],
    ['label' => 'Tổng quyên góp', 'value' => $stats['donations'], 'color' => '198754'],
    ['label' => 'Vật phẩm trong kho', 'value' => $stats['items'], 'color' => '0DCAF0'],
    ['label' => 'Tổng chiến dịch', 'value' => $stats['campaigns'], 'color' => 'FFC107']
];

$cardWidth = 3; // number of columns per card
$cardColumnsTotal = 4 * ($cardWidth + 1) + 1;
for ($col = 2; $col <= $cardColumnsTotal; $col++) {
    $visualSheet->getColumnDimension(Coordinate::stringFromColumnIndex($col))->setWidth(12);
}

$cardLabelStartRow = 3;
$cardLabelEndRow = 4;
$cardValueStartRow = 5;
$cardValueEndRow = 8;

foreach ($kpiCards as $index => $card) {
    $startColumnIndex = 2 + ($index * ($cardWidth + 1)); // start at column B
    $endColumnIndex = $startColumnIndex + $cardWidth - 1;
    $labelRange = Coordinate::stringFromColumnIndex($startColumnIndex) . $cardLabelStartRow . ':' .
        Coordinate::stringFromColumnIndex($endColumnIndex) . $cardLabelEndRow;
    $valueRange = Coordinate::stringFromColumnIndex($startColumnIndex) . $cardValueStartRow . ':' .
        Coordinate::stringFromColumnIndex($endColumnIndex) . $cardValueEndRow;
    $cardRange = Coordinate::stringFromColumnIndex($startColumnIndex) . $cardLabelStartRow . ':' .
        Coordinate::stringFromColumnIndex($endColumnIndex) . $cardValueEndRow;

    $visualSheet->mergeCells($labelRange);
    $visualSheet->mergeCells($valueRange);

    $visualSheet->setCellValue(Coordinate::stringFromColumnIndex($startColumnIndex) . $cardLabelStartRow, $card['label']);
    $visualSheet->setCellValue(Coordinate::stringFromColumnIndex($startColumnIndex) . $cardValueStartRow, number_format($card['value']));

    $style = [
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'color' => ['rgb' => $card['color']]
        ],
        'font' => [
            'color' => ['rgb' => 'FFFFFF'],
            'bold' => true
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER,
            'wrapText' => true
        ],
        'borders' => [
            'outline' => [
                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM,
                'color' => ['rgb' => $card['color']]
            ]
        ]
    ];
    $visualSheet->getStyle($cardRange)->applyFromArray($style);
    $visualSheet->getStyle($labelRange)->getFont()->setSize(12);
    $visualSheet->getStyle($valueRange)->getFont()->setSize(24);
}

for ($row = $cardLabelStartRow; $row <= $cardValueEndRow; $row++) {
    $visualSheet->getRowDimension($row)->setRowHeight($row <= $cardLabelEndRow ? 22 : 28);
}

$donationLabelRow = $cardValueEndRow + 3;
$visualSheet->setCellValue('A' . $donationLabelRow, 'Thống kê quyên góp theo tháng');
$visualSheet->getStyle('A' . $donationLabelRow)->getFont()->setBold(true);
$donationChartTop = $donationLabelRow + 1;
$donationChartBottom = $donationChartTop + 14;

$visualDonationSeries = clone $donationSeries;
$visualDonationPlot = new PlotArea(null, [$visualDonationSeries]);
$visualDonationChart = new Chart(
    'visual_donation',
    new Title('Thống kê quyên góp theo tháng'),
    new Legend(Legend::POSITION_BOTTOM, null, false),
    $visualDonationPlot
);
$visualDonationChart->setTopLeftPosition('C' . $donationChartTop);
$visualDonationChart->setBottomRightPosition('L' . $donationChartBottom);
$visualSheet->addChart($visualDonationChart);

$categoryLabelRow = $donationChartBottom + 2;
$visualSheet->setCellValue('A' . $categoryLabelRow, 'Phân bố danh mục');
$visualSheet->getStyle('A' . $categoryLabelRow)->getFont()->setBold(true);
$categoryChartTop = $categoryLabelRow + 1;
$categoryChartBottom = $categoryChartTop + 15;

$visualCategorySeries = clone $categorySeries;
$visualCategoryPlot = new PlotArea(null, [$visualCategorySeries]);
$visualCategoryChart = new Chart(
    'visual_category',
    new Title('Phân bố danh mục'),
    new Legend(Legend::POSITION_RIGHT, null, false),
    $visualCategoryPlot
);
$visualCategoryChart->setTopLeftPosition('C' . $categoryChartTop);
$visualCategoryChart->setBottomRightPosition('L' . $categoryChartBottom);
$visualSheet->addChart($visualCategoryChart);

$writer = new Xlsx($spreadsheet);
$writer->setIncludeCharts(true);
$filename = 'bao-cao-' . date('Ymd-Hi') . '.xlsx';

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer->save('php://output');
exit;
