<?php
session_start();
if (!isset($_SESSION['auth_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../login.php");
    exit();
}

require '../vendor/autoload.php';
include '../conn.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

$authId = $_SESSION['auth_id'];

$startDate = isset($_GET['start_date']) && !empty($_GET['start_date']) ? $_GET['start_date'] : null;
$endDate = isset($_GET['end_date']) && !empty($_GET['end_date']) ? $_GET['end_date'] : null;

$departmentFilter = isset($_GET['department_id']) && !empty($_GET['department_id']) ? $_GET['department_id'] : null;

$whereClause = "WHERE 1=1";
$params = [];

if ($departmentFilter) {
    $whereClause .= " AND a.department_id = ?";
    $params[] = $departmentFilter;
}

if ($startDate && $endDate) {
    $whereClause .= " AND DATE(af.submitted_at) BETWEEN ? AND ?";
    $params[] = $startDate;
    $params[] = $endDate;
} elseif ($startDate) {
    $whereClause .= " AND DATE(af.submitted_at) >= ?";
    $params[] = $startDate;
} elseif ($endDate) {
    $whereClause .= " AND DATE(af.submitted_at) <= ?";
    $params[] = $endDate;
}

$feedbackStmt = $pdo->prepare("
    SELECT 
        af.id,
        af.sqd0_answer,
        af.sqd1_answer,
        af.sqd2_answer,
        af.sqd3_answer,
        af.sqd4_answer,
        af.sqd5_answer,
        af.sqd6_answer,
        af.sqd7_answer,
        af.sqd8_answer,
        af.cc1_answer,
        af.cc2_answer,
        af.cc3_answer,
        af.suggestions,
        af.submitted_at,
        a.transaction_id,
        a.scheduled_for,
        CONCAT(r.first_name, ' ', r.last_name) as resident_name,
        ds.service_name,
        d.name as department_name
    FROM appointment_feedback af
    JOIN appointments a ON af.appointment_id = a.id
    JOIN residents r ON a.resident_id = r.id
    JOIN department_services ds ON a.service_id = ds.id
    JOIN departments d ON a.department_id = d.id
    $whereClause
    ORDER BY af.submitted_at DESC
");
$feedbackStmt->execute($params);
$feedbacks = $feedbackStmt->fetchAll(PDO::FETCH_ASSOC);

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

$spreadsheet->getProperties()
    ->setCreator("LGU Quick Appoint")
    ->setTitle("Feedback Report - All Departments")
    ->setSubject("Client Feedback Data")
    ->setDescription("Exported feedback data from all departments - LGU Quick Appoint system");

$sheet->getColumnDimension('A')->setWidth(20);
$sheet->getColumnDimension('B')->setWidth(25);
$sheet->getColumnDimension('C')->setWidth(25);
$sheet->getColumnDimension('D')->setWidth(30);
$sheet->getColumnDimension('E')->setWidth(18);
$sheet->getColumnDimension('F')->setWidth(18);
$sheet->getColumnDimension('G')->setWidth(20);
$sheet->getColumnDimension('H')->setWidth(20);
$sheet->getColumnDimension('I')->setWidth(20);
$sheet->getColumnDimension('J')->setWidth(20);
$sheet->getColumnDimension('K')->setWidth(20);
$sheet->getColumnDimension('L')->setWidth(20);
$sheet->getColumnDimension('M')->setWidth(20);
$sheet->getColumnDimension('N')->setWidth(20);
$sheet->getColumnDimension('O')->setWidth(20);
$sheet->getColumnDimension('P')->setWidth(20);
$sheet->getColumnDimension('Q')->setWidth(20);
$sheet->getColumnDimension('R')->setWidth(40);

$sheet->setCellValue('A1', 'CLIENT FEEDBACK REPORT - ALL DEPARTMENTS');
$sheet->mergeCells('A1:R1');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$dateRangeText = 'Report Generated: ' . date('F d, Y g:i A');
if ($startDate || $endDate) {
    $dateRangeText .= ' | Period: ';
    $dateRangeText .= ($startDate ? date('M d, Y', strtotime($startDate)) : 'All');
    $dateRangeText .= ' - ';
    $dateRangeText .= ($endDate ? date('M d, Y', strtotime($endDate)) : 'All');
}
if ($departmentFilter) {
    $deptStmt = $pdo->prepare("SELECT name FROM departments WHERE id = ?");
    $deptStmt->execute([$departmentFilter]);
    $dept = $deptStmt->fetch(PDO::FETCH_ASSOC);
    if ($dept) {
        $dateRangeText .= ' | Department: ' . $dept['name'];
    }
}
$sheet->setCellValue('A2', $dateRangeText);
$sheet->mergeCells('A2:R2');
$sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$totalFeedbacks = count($feedbacks);
$sheet->setCellValue('A3', 'Total Feedbacks: ' . $totalFeedbacks);
$sheet->mergeCells('A3:R3');
$sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$headers = [
    'Transaction ID',
    'Department',
    'Resident Name',
    'Service',
    'Appointment Date',
    'Submitted Date',
    'SQD0 - Satisfaction',
    'SQD1 - Time',
    'SQD2 - Requirements',
    'SQD3 - Steps',
    'SQD4 - Information',
    'SQD5 - Fees',
    'SQD6 - Security',
    'SQD7 - Support',
    'SQD8 - Outcome',
    'CC1 - Awareness',
    'CC2 - Visibility',
    'Suggestions'
];

$col = 'A';
foreach ($headers as $header) {
    $sheet->setCellValue($col . '4', $header);
    $col++;
}

$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '1e40af']
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => '000000']
        ]
    ]
];
$sheet->getStyle('A4:R4')->applyFromArray($headerStyle);
$sheet->getRowDimension('4')->setRowHeight(30);

$row = 5;
foreach ($feedbacks as $feedback) {
    $sheet->setCellValue('A' . $row, $feedback['transaction_id']);
    $sheet->setCellValue('B' . $row, $feedback['department_name']);
    $sheet->setCellValue('C' . $row, $feedback['resident_name']);
    $sheet->setCellValue('D' . $row, $feedback['service_name']);
    $sheet->setCellValue('E' . $row, date('M d, Y', strtotime($feedback['scheduled_for'])));
    $sheet->setCellValue('F' . $row, date('M d, Y g:i A', strtotime($feedback['submitted_at'])));
    $sheet->setCellValue('G' . $row, $feedback['sqd0_answer'] ?? 'N/A');
    $sheet->setCellValue('H' . $row, $feedback['sqd1_answer'] ?? 'N/A');
    $sheet->setCellValue('I' . $row, $feedback['sqd2_answer'] ?? 'N/A');
    $sheet->setCellValue('J' . $row, $feedback['sqd3_answer'] ?? 'N/A');
    $sheet->setCellValue('K' . $row, $feedback['sqd4_answer'] ?? 'N/A');
    $sheet->setCellValue('L' . $row, $feedback['sqd5_answer'] ?? 'N/A');
    $sheet->setCellValue('M' . $row, $feedback['sqd6_answer'] ?? 'N/A');
    $sheet->setCellValue('N' . $row, $feedback['sqd7_answer'] ?? 'N/A');
    $sheet->setCellValue('O' . $row, $feedback['sqd8_answer'] ?? 'N/A');
    $sheet->setCellValue('P' . $row, $feedback['cc1_answer'] ?? 'N/A');
    $sheet->setCellValue('Q' . $row, $feedback['cc2_answer'] ?? 'N/A');
    $sheet->setCellValue('R' . $row, $feedback['suggestions'] ?? '');
    
    $sheet->getStyle('A' . $row . ':R' . $row)->applyFromArray([
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => 'CCCCCC']
            ]
        ]
    ]);
    
    $sheet->getStyle('R' . $row)->getAlignment()->setWrapText(true);
    
    $row++;
}

for ($i = 5; $i < $row; $i++) {
    $sheet->getRowDimension($i)->setRowHeight(-1);
}

$filename = 'Feedback_Report_All_Departments_' . date('Y-m-d_His');
if ($startDate || $endDate) {
    $filename .= '_' . ($startDate ?? 'All') . '_to_' . ($endDate ?? 'All');
}
if ($departmentFilter) {
    $filename .= '_Dept' . $departmentFilter;
}
$filename .= '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
