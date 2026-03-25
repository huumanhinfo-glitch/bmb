<?php
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Tạo spreadsheet mới
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Tạo header
$sheet->setCellValue('A1', 'team_name');
$sheet->setCellValue('B1', 'player1');
$sheet->setCellValue('C1', 'player2');
$sheet->setCellValue('D1', 'tournament');
$sheet->setCellValue('E1', 'skill_level');

// Dữ liệu mẫu
$data = [
    // BMB Super Cup - 16 đội
    ['SC01', 'Nguyễn Văn Anh', 'Trần Minh Bảo', 'BMB Super Cup - Đôi Nam', '4.0'],
    ['SC02', 'Lê Công Danh', 'Phạm Đức Huy', 'BMB Super Cup - Đôi Nam', '4.0'],
    ['SC03', 'Hoàng Kim Long', 'Vũ Minh Quân', 'BMB Super Cup - Đôi Nam', '3.5'],
    ['SC04', 'Đặng Quốc Thắng', 'Bùi Tuấn Kiệt', 'BMB Super Cup - Đôi Nam', '3.5'],
    ['SC05', 'Ngô Hữu Nghĩa', 'Đỗ Xuân Phong', 'BMB Super Cup - Đôi Nam', '3.5'],
    ['SC06', 'Mai Quang Hải', 'Lý Văn Sơn', 'BMB Super Cup - Đôi Nam', '3.0'],
    ['SC07', 'Trịnh Hoàng Nam', 'Cao Tiến Dũng', 'BMB Super Cup - Đôi Nam', '3.0'],
    ['SC08', 'Vương Mạnh Cường', 'Lưu Đình Trọng', 'BMB Super Cup - Đôi Nam', '3.0'],
    
    // Giải Mixed - 12 đội
    ['MX01', 'Nguyễn Văn Anh', 'Nguyễn Thị Mai', 'Giải Đôi Nam Nữ (Mixed)', 'Open'],
    ['MX02', 'Trần Minh Bảo', 'Trần Thu Hà', 'Giải Đôi Nam Nữ (Mixed)', 'Open'],
    ['MX03', 'Lê Công Danh', 'Lê Minh Anh', 'Giải Đôi Nam Nữ (Mixed)', '3.5'],
    ['MX04', 'Phạm Đức Huy', 'Phạm Thanh Thủy', 'Giải Đôi Nam Nữ (Mixed)', '3.5'],
    ['MX05', 'Hoàng Kim Long', 'Hoàng Thị Lan', 'Giải Đôi Nam Nữ (Mixed)', '3.0'],
    ['MX06', 'Vũ Minh Quân', 'Vũ Ngọc Ánh', 'Giải Đôi Nam Nữ (Mixed)', '3.0'],
    ['MX07', 'Đặng Quốc Thắng', 'Đặng Kim Ngân', 'Giải Đôi Nam Nữ (Mixed)', '2.5'],
    ['MX08', 'Bùi Tuấn Kiệt', 'Bùi Thu Trang', 'Giải Đôi Nam Nữ (Mixed)', '2.5'],
    
    // Giao lưu cuối tuần - 20 đội
    ['GL01', 'Nguyễn Văn A', 'Trần Văn B', 'Giao Lưu Cuối Tuần', '3.0'],
    ['GL02', 'Lê Văn C', 'Phạm Văn D', 'Giao Lưu Cuối Tuần', '3.0'],
    ['GL03', 'Hoàng Văn E', 'Vũ Văn F', 'Giao Lưu Cuối Tuần', '2.5'],
    ['GL04', 'Đặng Văn G', 'Bùi Văn H', 'Giao Lưu Cuối Tuần', '2.5'],
    ['GL05', 'Ngô Văn I', 'Đỗ Văn J', 'Giao Lưu Cuối Tuần', '3.0'],
    ['GL06', 'Mai Văn K', 'Lý Văn L', 'Giao Lưu Cuối Tuần', '2.5'],
    ['GL07', 'Trịnh Văn M', 'Cao Văn N', 'Giao Lưu Cuối Tuần', '3.0'],
    ['GL08', 'Vương Văn O', 'Lưu Văn P', 'Giao Lưu Cuối Tuần', '2.5'],
    ['GL09', 'Nguyễn Thị Q', 'Trần Thị R', 'Giao Lưu Cuối Tuần', '2.5'],
    ['GL10', 'Lê Thị S', 'Phạm Thị T', 'Giao Lưu Cuối Tuần', '3.0'],
];

// Thêm dữ liệu vào sheet
$row = 2;
foreach ($data as $item) {
    $sheet->setCellValue('A' . $row, $item[0]);
    $sheet->setCellValue('B' . $row, $item[1]);
    $sheet->setCellValue('C' . $row, $item[2]);
    $sheet->setCellValue('D' . $row, $item[3]);
    $sheet->setCellValue('E' . $row, $item[4]);
    $row++;
}

// Tự động điều chỉnh độ rộng cột
foreach (range('A', 'E') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Tạo file Excel
$writer = new Xlsx($spreadsheet);
$filename = 'sample_data.xlsx';
$writer->save($filename);

echo "File mẫu đã được tạo: <a href='$filename'>$filename</a>";
?>