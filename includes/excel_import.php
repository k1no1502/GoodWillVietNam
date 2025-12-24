<?php
// Xử lý upload và đọc file Excel quyên góp
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

function readDonationExcel($filePath) {
    $spreadsheet = IOFactory::load($filePath);
    $sheet = $spreadsheet->getActiveSheet();
    $rows = [];
    foreach ($sheet->getRowIterator(2) as $row) { // Bỏ qua dòng tiêu đề
        $cellIterator = $row->getCellIterator();
        $cellIterator->setIterateOnlyExistingCells(false);
        $rowData = [];
        foreach ($cellIterator as $cell) {
            $rowData[] = $cell->getValue();
        }
        if (!empty(array_filter($rowData))) {
            $rows[] = $rowData;
        }
    }
    return $rows;
}
