<?php
require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Font;

$host = "localhost";
$username = "root";
$password = "";
$database = "lgu_quick_appoint";
$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$appointmentLocations = [];
$sql = "SELECT r.address, COUNT(a.id) as appointment_count
        FROM appointments a
        JOIN residents r ON a.resident_id = r.id
        WHERE r.address IS NOT NULL AND r.address != ''
        GROUP BY r.address
        ORDER BY appointment_count DESC";

$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $appointmentLocations[] = [
        'address' => $row['address'],
        'count' => (int)$row['appointment_count']
    ];
}

$barangayCoords = [
    'Bulo', 'Punta', 'San Roque', 'Poblacion', 'Cabulihan', 'Mairok', 
    'Tubigan', 'Balagbag', 'Balanacan', 'Plaridel', 'Pagaguasan', 'Poctol',
    'Bonifacio', 'Caigdal', 'Pulo', 'Kalilayan Ilaya', 'Kalilayan', 
    'Maligaya', 'Tagumpay', 'Sildora'
];

function extractBarangay($address, $barangayList) {
    $lower = strtolower(trim($address));
    
    foreach ($barangayList as $brgy) {
        $brgyLower = strtolower($brgy);
        if (strpos($lower, $brgyLower) !== false) {
            return $brgy;
        }
    }
    
    return 'Unclassified';
}

$barangayData = [];
foreach ($appointmentLocations as $apt) {
    $barangay = extractBarangay($apt['address'], $barangayCoords);
    
    if (!isset($barangayData[$barangay])) {
        $barangayData[$barangay] = 0;
    }
    
    $barangayData[$barangay] += $apt['count'];
}

arsort($barangayData);
$total = array_sum($barangayData);

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Barangay Appointments');

$sheet->getColumnDimension('A')->setWidth(10);
$sheet->getColumnDimension('B')->setWidth(35);
$sheet->getColumnDimension('C')->setWidth(28);

$sheet->mergeCells('A1:C1');
$sheet->setCellValue('A1', 'APPOINTMENT HOTSPOT REPORT');
$sheet->getStyle('A1')->applyFromArray([
    'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => 'FFFFFF']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0D92F4']]
]);
$sheet->getRowDimension(1)->setRowHeight(30);

$sheet->mergeCells('A2:C2');
$sheet->setCellValue('A2', 'Municipality of Unisan, Quezon Province');
$sheet->getStyle('A2')->applyFromArray([
    'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '27548A']]
]);
$sheet->getRowDimension(2)->setRowHeight(25);

$sheet->setCellValue('A4', 'Report Generated:');
$sheet->setCellValue('B4', date('F j, Y, g:i A'));
$sheet->setCellValue('A5', 'Total Barangays with Appointments:');
$sheet->setCellValue('B5', count($barangayData));
$sheet->setCellValue('A6', 'Total Appointments:');
$sheet->setCellValue('B6', $total);

$sheet->getStyle('A4:A6')->applyFromArray([
    'font' => ['bold' => true, 'size' => 10]
]);

$sheet->setCellValue('A8', 'No.');
$sheet->setCellValue('B8', 'Barangay');
$sheet->setCellValue('C8', 'Number of Appointments');

$sheet->getStyle('A8:C8')->applyFromArray([
    'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0D92F4']],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
]);
$sheet->getRowDimension(8)->setRowHeight(20);

$row = 9;
$index = 1;
foreach ($barangayData as $barangay => $count) {
    $sheet->setCellValue('A' . $row, $index);
    $sheet->setCellValue('B' . $row, $barangay);
    $sheet->setCellValue('C' . $row, $count);
    
    $fillColor = ($index % 2 == 0) ? 'F0F9FF' : 'FFFFFF';
    $sheet->getStyle('A' . $row . ':C' . $row)->applyFromArray([
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $fillColor]],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]],
        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]
    ]);
    
    $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('C' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    
    $row++;
    $index++;
}

$sheet->setCellValue('A' . $row, '');
$sheet->setCellValue('B' . $row, 'TOTAL');
$sheet->setCellValue('C' . $row, $total);

$sheet->getStyle('A' . $row . ':C' . $row)->applyFromArray([
    'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2ECC71']],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_MEDIUM]]
]);
$sheet->getRowDimension($row)->setRowHeight(22);

$filename = 'Appointment_Hotspot_Report_' . date('Y-m-d') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');

$conn->close();
exit();
?>
