<?php
session_start();
if (!isset($_SESSION['auth_id']) || $_SESSION['role'] !== 'Admin') {
    http_response_code(403);
    exit(json_encode(['error' => 'Unauthorized']));
}

$host = "localhost";
$username = "root";
$password = "";
$database = "lgu_quick_appoint";
$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    http_response_code(500);
    exit(json_encode(['error' => 'Database connection failed']));
}

$filter = $_GET['filter'] ?? 'monthly';

function getDateFilter($filter) {
    switch($filter) {
        case 'weekly':
            return "WHERE requested_at >= DATE_SUB(CURDATE(), INTERVAL 1 WEEK)";
        case 'monthly':
            return "WHERE MONTH(requested_at) = MONTH(CURDATE()) AND YEAR(requested_at) = YEAR(CURDATE())";
        case 'yearly':
            return "WHERE YEAR(requested_at) = YEAR(CURDATE())";
        case 'all':
        default:
            return "";
    }
}

$dateFilter = getDateFilter($filter);

$deptLabels = [];
$deptCounts = [];
$res = $conn->query("SELECT d.name, COUNT(a.id) AS total FROM appointments a 
                     JOIN departments d ON a.department_id = d.id 
                     $dateFilter
                     GROUP BY d.id ORDER BY d.name");
while ($row = $res->fetch_assoc()) {
    $deptLabels[] = $row['name'];
    $deptCounts[] = (int)$row['total'];
}

$serviceLabels = [];
$serviceCounts = [];
$res = $conn->query("SELECT s.service_name, COUNT(a.id) AS total 
                     FROM appointments a 
                     JOIN department_services s ON a.service_id = s.id 
                     $dateFilter
                     GROUP BY s.id ORDER BY total DESC LIMIT 8");
while ($row = $res->fetch_assoc()) {
    $serviceLabels[] = $row['service_name'];
    $serviceCounts[] = (int)$row['total'];
}

$monthlyData = array_fill(1, 12, 0);
if ($filter === 'weekly') {
    $dayLabels = [];
    $dayCounts = [];
    $res = $conn->query("SELECT DATE(requested_at) AS d, COUNT(*) AS c 
                         FROM appointments 
                         WHERE requested_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                         GROUP BY DATE(requested_at) ORDER BY d");
    while ($row = $res->fetch_assoc()) {
        $dayLabels[] = date('M j', strtotime($row['d']));
        $dayCounts[] = (int)$row['c'];
    }
    $monthlyData = $dayCounts;
    $monthLabels = $dayLabels;
} else {
    $res = $conn->query("SELECT MONTH(requested_at) AS m, COUNT(*) AS c 
                         FROM appointments 
                         WHERE YEAR(requested_at)=YEAR(CURDATE()) $dateFilter
                         GROUP BY MONTH(requested_at)");
    while ($row = $res->fetch_assoc()) {
        $monthlyData[(int)$row['m']] = (int)$row['c'];
    }
    $monthLabels = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    $monthlyData = array_values($monthlyData);
}

header('Content-Type: application/json');
echo json_encode([
    'departments' => [
        'labels' => $deptLabels,
        'data' => $deptCounts
    ],
    'services' => [
        'labels' => $serviceLabels,
        'data' => $serviceCounts
    ],
    'monthly' => [
        'labels' => $monthLabels,
        'data' => $monthlyData
    ]
]);

$conn->close();
?>