<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Font;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Dữ liệu mẫu
$data = [
    ['Tên vật phẩm','Mô tả','Danh mục','Số lượng','Đơn vị','Tình trạng','Giá trị ước tính','Địa chỉ nhận hàng','Ngày nhận','Giờ nhận','Số điện thoại'],
    ['Áo khoác mùa đông','Áo khoác dày cho trẻ em','Quần áo',5,'cái','good',500000,'123 Đường ABC','2025-12-01','09:00','0912345678'],
    ['Sách giáo khoa lớp 5','Bộ sách giáo khoa đầy đủ','Sách vở',2,'bộ','like_new',200000,'123 Đường ABC','2025-12-01','09:00','0912345678'],
];

// Ghi dữ liệu vào sheet
foreach ($data as $rowIdx => $row) {
    foreach ($row as $colIdx => $value) {
        $cell = $sheet->getCellByColumnAndRow($colIdx + 1, $rowIdx + 1);
        $cell->setValue($value);
        $cell->getStyle()->getFont()->setName('Times New Roman');
        if ($rowIdx === 0) {
            $cell->getStyle()->getFont()->setBold(true);
        }
    }
}

// Auto width
foreach (range('A', $sheet->getHighestColumn()) as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

$writer = new Xlsx($spreadsheet);
$writer->save(__DIR__ . '/../../assets/excel/donation_template.xlsx');
echo "Đã tạo file Excel mẫu với font Times New Roman!\n";
