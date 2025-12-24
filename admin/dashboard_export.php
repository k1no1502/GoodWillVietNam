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
use PhpOffice\PhpSpreadsheet\Style\Alignment;

$stats = getStatistics();
$donationTrend = getDonationTrendData();
$categoryDistribution = getCategoryDistributionData();

$spreadsheet = new Spreadsheet();
$spreadsheet->getProperties()
    ->setCreator('Goodwill Vietnam')
    ->setTitle('Dashboard export')
    ->setSubject('Dashboard data export')
    ->setDescription('Tong quan so lieu dashboard tai thoi diem ' . date('d/m/Y H:i'));

// Overview sheet
$overview = $spreadsheet->getActiveSheet();
$overview->setTitle('Tong quan');
$overview->fromArray([
    ['Chi so', 'Gia tri'],
    ['Tong nguoi dung', $stats['users']],
    ['Tong quyen gop', $stats['donations']],
    ['Vat pham ton kho', $stats['items']],
    ['Chien dich', $stats['campaigns']],
], null, 'A1');
$overview->getStyle('A1:B1')->getFont()->setBold(true);
$overview->getColumnDimension('A')->setWidth(25);
$overview->getColumnDimension('B')->setWidth(18);
$overview->getStyle('A1:B5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
$overview->getStyle('B2:B5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
$overview->setCellValue('D1', 'Thoi gian xuat')
    ->setCellValue('E1', date('d/m/Y H:i'));
$overview->getStyle('D1:E1')->getFont()->setBold(true);

// KPI column chart (replicates dashboard cards visually)
$kpiCategoriesRange = "'Tong quan'!\$A\$2:\$A\$5";
$kpiValuesRange = "'Tong quan'!\$B\$2:\$B\$5";
$kpiSeries = new DataSeries(
    DataSeries::TYPE_BARCHART,
    DataSeries::GROUPING_CLUSTERED,
    range(0, 0),
    [new DataSeriesValues('String', "'Tong quan'!\$B\$1", null, 1)],
    [new DataSeriesValues('String', $kpiCategoriesRange, null, 4)],
    [new DataSeriesValues('Number', $kpiValuesRange, null, 4)]
);
$kpiSeries->setPlotDirection(DataSeries::DIRECTION_COL);
$kpiPlot = new PlotArea(null, [$kpiSeries]);
$kpiChart = new Chart(
    'kpi_summary_chart',
    new Title('Tong quan nguoi dung - quyen gop - vat pham - chien dich'),
    new Legend(Legend::POSITION_RIGHT, null, false),
    $kpiPlot
);
$kpiChart->setTopLeftPosition('D3');
$kpiChart->setBottomRightPosition('L18');
$overview->addChart($kpiChart);

// Donation trend sheet
$donationSheet = $spreadsheet->createSheet();
$donationSheet->setTitle('Quyen gop thang');
$donationSheet->fromArray([
    ['Thang', 'So quyen gop']
], null, 'A1');
foreach ($donationTrend as $index => $item) {
    $row = $index + 2;
    $donationSheet->setCellValue('A' . $row, $item['label']);
    $donationSheet->setCellValue('B' . $row, $item['total']);
}
$donationSheet->getStyle('A1:B1')->getFont()->setBold(true);
$donationSheet->getColumnDimension('A')->setWidth(20);
$donationSheet->getColumnDimension('B')->setWidth(20);
$donationRangeEnd = count($donationTrend) + 1;
$donationSeriesLabels = [
    new DataSeriesValues('String', "'Quyen gop thang'!\$B\$1", null, 1)
];
$donationAxis = [
    new DataSeriesValues('String', "'Quyen gop thang'!\$A\$2:\$A\$" . max(2, $donationRangeEnd), null, max(1, count($donationTrend)))
];
$donationValues = [
    new DataSeriesValues('Number', "'Quyen gop thang'!\$B\$2:\$B\$" . max(2, $donationRangeEnd), null, max(1, count($donationTrend)))
];
$donationSeries = new DataSeries(
    DataSeries::TYPE_LINECHART,
    DataSeries::GROUPING_STANDARD,
    range(0, count($donationValues) - 1),
    $donationSeriesLabels,
    $donationAxis,
    $donationValues
);
$donationPlotArea = new PlotArea(null, [$donationSeries]);
$donationLegend = new Legend(Legend::POSITION_TOPRIGHT, null, false);
$donationChart = new Chart(
    'donation_trend_chart',
    new Title('Thong ke quyen gop theo thang'),
    $donationLegend,
    $donationPlotArea
);
$donationChart->setTopLeftPosition('D2');
$donationChart->setBottomRightPosition('L18');
$donationSheet->addChart($donationChart);

// Category sheet
$categorySheet = $spreadsheet->createSheet();
$categorySheet->setTitle('Danh muc');
$categorySheet->fromArray([
    ['Danh muc', 'Tong so']
], null, 'A1');
foreach ($categoryDistribution as $index => $item) {
    $row = $index + 2;
    $categorySheet->setCellValue('A' . $row, $item['label']);
    $categorySheet->setCellValue('B' . $row, $item['total']);
}
$categorySheet->getStyle('A1:B1')->getFont()->setBold(true);
$categorySheet->getColumnDimension('A')->setWidth(25);
$categorySheet->getColumnDimension('B')->setWidth(15);
$categoryRangeEnd = count($categoryDistribution) + 1;
$categoryLabelsRange = "'Danh muc'!\$A\$2:\$A\$" . max(2, $categoryRangeEnd);
$categoryValuesRange = "'Danh muc'!\$B\$2:\$B\$" . max(2, $categoryRangeEnd);
$categorySeries = new DataSeries(
    DataSeries::TYPE_DONUTCHART,
    null,
    range(0, 0),
    [new DataSeriesValues('String', "'Danh muc'!\$A\$1", null, 1)],
    [new DataSeriesValues('String', $categoryLabelsRange, null, max(1, count($categoryDistribution)))],
    [new DataSeriesValues('Number', $categoryValuesRange, null, max(1, count($categoryDistribution)))]
);
$categorySeries->setPlotDirection(DataSeries::DIRECTION_COL);
$categoryPlot = new PlotArea(null, [$categorySeries]);
$categoryLegend = new Legend(Legend::POSITION_RIGHT, null, false);
$categoryChart = new Chart(
    'category_distribution',
    new Title('Phan bo danh muc'),
    $categoryLegend,
    $categoryPlot
);
$categoryChart->setTopLeftPosition('D2');
$categoryChart->setBottomRightPosition('J18');
$categorySheet->addChart($categoryChart);

// Visual dashboard sheet aggregating requested charts
$visualSheet = $spreadsheet->createSheet();
$visualSheet->setTitle('So do');
$visualSheet->setCellValue('A1', 'Dashboard Excel');
$visualSheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$visualSheet->setCellValue('A3', 'So do tong quan (cards)');
$visualSheet->setCellValue('A20', 'Thong ke quyen gop theo thang');
$visualSheet->setCellValue('A36', 'Phan bo danh muc');

// Reuse KPI chart on the visual sheet
$dashboardKpiSeries = new DataSeries(
    DataSeries::TYPE_BARCHART,
    DataSeries::GROUPING_CLUSTERED,
    range(0, 0),
    [new DataSeriesValues('String', "'Tong quan'!\$B\$1", null, 1)],
    [new DataSeriesValues('String', $kpiCategoriesRange, null, 4)],
    [new DataSeriesValues('Number', $kpiValuesRange, null, 4)]
);
$dashboardKpiSeries->setPlotDirection(DataSeries::DIRECTION_COL);
$dashboardKpiPlot = new PlotArea(null, [$dashboardKpiSeries]);
$dashboardKpiChart = new Chart(
    'dashboard_kpi_chart',
    new Title('Tong nguoi dung / quyen gop / vat pham / chien dich'),
    new Legend(Legend::POSITION_RIGHT, null, false),
    $dashboardKpiPlot
);
$dashboardKpiChart->setTopLeftPosition('C4');
$dashboardKpiChart->setBottomRightPosition('L18');
$visualSheet->addChart($dashboardKpiChart);

// Donation trend chart mirrored on dashboard sheet
$dashboardTrendSeries = new DataSeries(
    DataSeries::TYPE_LINECHART,
    DataSeries::GROUPING_STANDARD,
    range(0, 0),
    [new DataSeriesValues('String', "'Quyen gop thang'!\$B\$1", null, 1)],
    [new DataSeriesValues('String', "'Quyen gop thang'!\$A\$2:\$A\$" . max(2, $donationRangeEnd), null, max(1, count($donationTrend)))],
    [new DataSeriesValues('Number', "'Quyen gop thang'!\$B\$2:\$B\$" . max(2, $donationRangeEnd), null, max(1, count($donationTrend)))]
);
$dashboardTrendPlot = new PlotArea(null, [$dashboardTrendSeries]);
$dashboardTrendChart = new Chart(
    'dashboard_trend_chart',
    new Title('Thong ke quyen gop theo thang'),
    new Legend(Legend::POSITION_BOTTOM, null, false),
    $dashboardTrendPlot
);
$dashboardTrendChart->setTopLeftPosition('C21');
$dashboardTrendChart->setBottomRightPosition('L35');
$visualSheet->addChart($dashboardTrendChart);

// Category distribution donut mirrored on dashboard sheet
$visualCategorySeries = new DataSeries(
    DataSeries::TYPE_DONUTCHART,
    null,
    range(0, 0),
    [new DataSeriesValues('String', "'Danh muc'!\$A\$1", null, 1)],
    [new DataSeriesValues('String', $categoryLabelsRange, null, max(1, count($categoryDistribution)))],
    [new DataSeriesValues('Number', $categoryValuesRange, null, max(1, count($categoryDistribution)))]
);
$visualCategorySeries->setPlotDirection(DataSeries::DIRECTION_COL);
$visualCategoryPlot = new PlotArea(null, [$visualCategorySeries]);
$visualCategoryChart = new Chart(
    'dashboard_category_chart',
    new Title('Phan bo danh muc'),
    new Legend(Legend::POSITION_RIGHT, null, false),
    $visualCategoryPlot
);
$visualCategoryChart->setTopLeftPosition('C38');
$visualCategoryChart->setBottomRightPosition('L60');
$visualSheet->addChart($visualCategoryChart);

$filename = 'dashboard-' . date('Ymd-Hi') . '.xlsx';
$writer = new Xlsx($spreadsheet);
$writer->setIncludeCharts(true);

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

while (ob_get_level() > 0) {
    ob_end_clean();
}
$writer->save('php://output');
exit;
