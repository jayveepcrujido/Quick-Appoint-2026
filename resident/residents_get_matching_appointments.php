<?php
session_start();
include '../conn.php';

if (!isset($_SESSION['auth_id']) || $_SESSION['role'] !== 'Resident') {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$userId = $_SESSION['user_id'];

if (!isset($_GET['scheduled_for'])) {
    echo json_encode(['error' => 'Missing scheduled date']);
    exit();
}

$scheduledFor = $_GET['scheduled_for'];

$query = "SELECT a.id, u.first_name, u.last_name, a.status
          FROM appointments a
          JOIN users u ON a.user_id = u.id
          WHERE a.scheduled_for = :scheduled_for 
            AND a.user_id != :user_id
            AND a.status = 'Pending'";
$stmt = $pdo->prepare($query);
$stmt->execute([
    'scheduled_for' => $scheduledFor,
    'user_id' => $userId
]);

$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($results);
?>
