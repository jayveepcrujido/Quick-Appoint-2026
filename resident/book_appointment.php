<?php
session_start();
include '../conn.php';

$userId = $_SESSION['resident_id'];
$departmentId = $_POST['department_id'];
$availableDateId = $_POST['available_date_id'];
$reason = $_POST['reason'] ?? null;

$stmt = $pdo->prepare("SELECT date_time FROM available_dates WHERE id = ?");
$stmt->execute([$availableDateId]);
$dateRow = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$dateRow) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid date selected.']);
    exit;
}

$dateTime = $dateRow['date_time'];

$insert = $pdo->prepare("INSERT INTO appointments (resident_id, department_id, scheduled_for, reason) VALUES (?, ?, ?, ?)");
$insert->execute([$userId, $departmentId, $dateTime, $reason]);

echo json_encode(['status' => 'success', 'message' => 'Appointment successfully booked!']);
?>
