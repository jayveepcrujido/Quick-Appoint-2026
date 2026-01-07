<?php
session_start();
$host = "localhost";
$username = "root";
$password = "";
$database = "lgu_quick_appoint";
$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    die(json_encode(['error' => 'Connection failed']));
}

$personnel_department_id = $_SESSION['department_id'] ?? null;
$date_filter_type = $_GET['date_filter'] ?? 'all';

if (!$personnel_department_id) {
    die(json_encode(['error' => 'No department ID']));
}

$date_condition = '';
switch ($date_filter_type) {
    case 'week':
        $date_condition = "AND a.requested_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
        break;
    case 'month':
        $date_condition = "AND a.requested_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
        break;
    case 'year':
        $date_condition = "AND a.requested_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
        break;
    case 'all':
    default:
        $date_condition = '';
        break;
}

$sql = "SELECT r.address, 
        COUNT(DISTINCT a.id) as appointment_count,
        COUNT(DISTINCT r.id) as resident_count,
        MAX(a.requested_at) as last_appointment
        FROM appointments a
        JOIN residents r ON a.resident_id = r.id
        WHERE r.address IS NOT NULL 
        AND r.address != ''
        AND a.department_id = ?
        $date_condition
        GROUP BY r.address
        ORDER BY appointment_count DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $personnel_department_id);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = [
        'address' => $row['address'],
        'appointment_count' => (int)$row['appointment_count'],
        'resident_count' => (int)$row['resident_count'],
        'last_appointment' => $row['last_appointment']
    ];
}

$stmt->close();
$conn->close();

header('Content-Type: application/json');
echo json_encode($data);