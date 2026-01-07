<?php
session_start();
if (!isset($_SESSION['auth_id']) || $_SESSION['role'] !== 'Admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

include '../../conn.php';

try {
    $departmentId = $_GET['department_id'] ?? '';
    $status = $_GET['status'] ?? '';
    $startDate = $_GET['start_date'] ?? '';
    $endDate = $_GET['end_date'] ?? '';

    $query = "
        SELECT a.id, a.status, a.scheduled_for, a.reason, a.requested_at,
               r.first_name, r.last_name,
               d.name AS department_name
        FROM appointments a
        JOIN residents r ON a.resident_id = r.id
        JOIN departments d ON a.department_id = d.id
        WHERE 1=1
    ";

    $params = [];

    if (!empty($departmentId)) {
        $query .= " AND a.department_id = :department_id";
        $params[':department_id'] = $departmentId;
    }

    if (!empty($status)) {
        $query .= " AND a.status = :status";
        $params[':status'] = $status;
    }

    if (!empty($startDate)) {
        $query .= " AND DATE(a.scheduled_for) >= :start_date";
        $params[':start_date'] = $startDate;
    }

    if (!empty($endDate)) {
        $query .= " AND DATE(a.scheduled_for) <= :end_date";
        $params[':end_date'] = $endDate;
    }

    $query .= " ORDER BY r.last_name ASC, a.scheduled_for ASC LIMIT 100";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($appointments as &$appointment) {
        if ($appointment['scheduled_for']) {
            $appointment['scheduled_for'] = date('F j, Y • g:i A', strtotime($appointment['scheduled_for']));
        }
        if ($appointment['requested_at']) {
            $appointment['requested_at'] = date('M d, Y g:i A', strtotime($appointment['requested_at']));
        }
    }

    header('Content-Type: application/json');
    echo json_encode($appointments);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>