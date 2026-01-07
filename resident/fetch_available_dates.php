<?php
session_start();
include '../conn.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

$departmentId = $_SESSION['department_id'];

$month = $_GET['month'];
$year = $_GET['year'];

$stmt = $pdo->prepare("
    SELECT 
        DATE(date_time) as date, 
        am_slots, 
        pm_slots, 
        am_booked, 
        pm_booked 
    FROM available_dates 
    WHERE department_id = ? 
    AND MONTH(date_time) = ? 
    AND YEAR(date_time) = ?
");
$stmt->execute([$departmentId, $month + 1, $year]);

$dates = $stmt->fetchAll(PDO::FETCH_ASSOC);

$availableDates = [];
foreach ($dates as $row) {
    $availableDates[$row['date']] = [
        'am_slots' => (int)$row['am_slots'],
        'pm_slots' => (int)$row['pm_slots'],
        'am_booked' => (int)$row['am_booked'],
        'pm_booked' => (int)$row['pm_booked']
    ];
}

echo json_encode($availableDates);
?>
