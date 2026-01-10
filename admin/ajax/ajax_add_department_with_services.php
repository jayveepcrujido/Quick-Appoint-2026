<?php
session_start();
if (!isset($_SESSION['auth_id']) || $_SESSION['role'] !== 'Admin') {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'Unauthorized access'
    ]);
    exit();
}

include '../../conn.php';

header('Content-Type: application/json');

$name = trim($_POST['name'] ?? '');
$acronym = trim($_POST['acronym'] ?? '');
$description = trim($_POST['description'] ?? '');
$serviceDescriptions = $_POST['service_descriptions'] ?? [];
$services = $_POST['services'] ?? [];
$requirements = $_POST['requirements'] ?? [];


// Validation
if (empty($name)) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Department name is required.'
    ]);
    exit();
}

if (empty($services) || !is_array($services)) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'At least one service is required.'
    ]);
    exit();
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("INSERT INTO departments (name, acronym, description) VALUES (?, ?, ?)");
    $stmt->execute([$name, $acronym ?: null, $description]);
    $deptId = $pdo->lastInsertId();

    $svcStmt = $pdo->prepare("INSERT INTO department_services (department_id, service_name, description) VALUES (?, ?, ?)");
    $reqStmt = $pdo->prepare("INSERT INTO service_requirements (service_id, requirement) VALUES (?, ?)");

    foreach ($services as $index => $service) {
        $serviceName = trim($service);
        
        if ($serviceName !== '') {
            $serviceDesc = isset($serviceDescriptions[$index]) ? trim($serviceDescriptions[$index]) : null;
            
            $svcStmt->execute([$deptId, $serviceName, $serviceDesc]);
            $serviceId = $pdo->lastInsertId();

            if (isset($requirements[$index])) {
                $rawReqs = $requirements[$index];
                
                $reqList = explode('|||', $rawReqs);

                foreach ($reqList as $singleReq) {
                    $cleanReq = trim($singleReq);
                    if ($cleanReq !== '') {
                        $reqStmt->execute([$serviceId, $cleanReq]);
                    }
                }
            }
        }
    }

    $pdo->commit();
    echo json_encode([
        'status' => 'success',
        'message' => 'Department added successfully!',
        'department_id' => $deptId
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'An error occurred: ' . $e->getMessage()
    ]);
}
?>