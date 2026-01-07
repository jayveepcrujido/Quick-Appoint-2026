<?php
session_start();
include '../conn.php';

header('Content-Type: application/json');

if (!isset($_SESSION['auth_id']) || $_SESSION['role'] !== 'LGU Personnel') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Unauthorized access'
    ]);
    exit();
}

$stmt = $pdo->prepare("SELECT department_id FROM lgu_personnel WHERE auth_id = ?");
$stmt->execute([$_SESSION['auth_id']]);
$personnel = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$personnel || !$personnel['department_id']) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Department not found'
    ]);
    exit();
}

$departmentId = $personnel['department_id'];

if (!isset($_POST['date']) || empty($_POST['date'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Date is required'
    ]);
    exit();
}

$selectedDate = $_POST['date'];

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid date format'
    ]);
    exit();
}

$today = date('Y-m-d');
if ($selectedDate < $today) {
    echo json_encode([
        'status' => 'unavailable',
        'message' => 'Cannot select a past date'
    ]);
    exit();
}

$dayOfWeek = date('N', strtotime($selectedDate));
if ($dayOfWeek >= 6) {
    echo json_encode([
        'status' => 'unavailable',
        'message' => 'Weekends are not available for appointments'
    ]);
    exit();
}

try {
    $stmt = $pdo->prepare("
        SELECT 
            id,
            am_slots,
            pm_slots,
            am_booked,
            pm_booked,
            (am_slots - am_booked) as am_remaining,
            (pm_slots - pm_booked) as pm_remaining
        FROM available_dates 
        WHERE department_id = ? 
        AND DATE(date_time) = ?
    ");
    $stmt->execute([$departmentId, $selectedDate]);
    $dateInfo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$dateInfo) {
        echo json_encode([
            'status' => 'unavailable',
            'message' => 'This date is not available for appointments'
        ]);
        exit();
    }

    $amAvailable = $dateInfo['am_remaining'] > 0;
    $pmAvailable = $dateInfo['pm_remaining'] > 0;

    if (!$amAvailable && !$pmAvailable) {
        echo json_encode([
            'status' => 'unavailable',
            'message' => 'All slots for this date are fully booked'
        ]);
        exit();
    }

    echo json_encode([
        'status' => 'available',
        'date_id' => $dateInfo['id'],
        'am_open' => $amAvailable,
        'pm_open' => $pmAvailable,
        'am_remaining' => $dateInfo['am_remaining'],
        'pm_remaining' => $dateInfo['pm_remaining'],
        'message' => 'Date is available'
    ]);

} catch (Exception $e) {
    error_log('Availability check error: ' . $e->getMessage());
    
    echo json_encode([
        'status' => 'error',
        'message' => 'Error checking availability. Please try again.'
    ]);
}
?>