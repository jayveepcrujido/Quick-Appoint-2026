<?php
include '../conn.php';

if (!isset($_GET['department_id']) || !isset($_GET['month']) || !isset($_GET['year'])) {
    exit('Missing parameters');
}

$departmentId = $_GET['department_id'];
$month = (int)$_GET['month'];
$year = (int)$_GET['year'];

$stmt = $pdo->prepare("SELECT id, DATE(date_time) AS date, am_slots, pm_slots, am_booked, pm_booked FROM available_dates WHERE department_id = ? AND MONTH(date_time) = ? AND YEAR(date_time) = ?");
$stmt->execute([$departmentId, $month, $year]);

$data = $stmt->fetchAll(PDO::FETCH_ASSOC);
$response = [];

foreach ($data as $row) {
    $date = $row['date'];
    $response[$date] = [
        'id' => $row['id'],
        'am_slots' => (int)$row['am_slots'],
        'pm_slots' => (int)$row['pm_slots'],
        'am_booked' => (int)$row['am_booked'],
        'pm_booked' => (int)$row['pm_booked']
    ];
}

echo json_encode($response);