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

$deptId = $_POST['department_id'] ?? null;
$name = trim($_POST['name'] ?? '');
$acronym = trim($_POST['acronym'] ?? '');
$description = trim($_POST['description'] ?? '');

$serviceIds = $_POST['service_ids'] ?? [];
$serviceNames = $_POST['service_names'] ?? [];
$serviceDescriptions = $_POST['service_descriptions'] ?? [];
$requirementsMap = $_POST['requirements'] ?? [];

if (!$deptId || !$name || empty($serviceNames)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid or missing data.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Update department basic info
    $stmt = $pdo->prepare("UPDATE departments SET name = ?, acronym = ?, description = ? WHERE id = ?");
    $stmt->execute([$name, $acronym, $description, $deptId]);

    // Process each service
    foreach ($serviceNames as $index => $serviceName) {
        $serviceId = $serviceIds[$index];
        $serviceDesc = isset($serviceDescriptions[$index]) ? trim($serviceDescriptions[$index]) : null;
        $serviceDesc = ($serviceDesc === '') ? null : $serviceDesc;

        // Check if this is a new service (ID starts with 'new_')
        if (strpos($serviceId, 'new') === 0) {
            // Insert new service with description
            $stmt = $pdo->prepare("INSERT INTO department_services (department_id, service_name, description) VALUES (?, ?, ?)");
            $stmt->execute([$deptId, trim($serviceName), $serviceDesc]);
            $newServiceId = $pdo->lastInsertId();

            // Insert requirements for new service
            if (!empty($requirementsMap[$serviceId])) {
                foreach ($requirementsMap[$serviceId] as $req) {
                    if (trim($req) !== '') {
                        $stmt = $pdo->prepare("INSERT INTO service_requirements (service_id, requirement) VALUES (?, ?)");
                        $stmt->execute([$newServiceId, trim($req)]);
                    }
                }
            }
        } else {
            // Update existing service with description
            $stmt = $pdo->prepare("UPDATE department_services SET service_name = ?, description = ? WHERE id = ?");
            $stmt->execute([trim($serviceName), $serviceDesc, $serviceId]);

            // Delete old requirements
            $stmt = $pdo->prepare("DELETE FROM service_requirements WHERE service_id = ?");
            $stmt->execute([$serviceId]);

            // Insert updated requirements
            if (!empty($requirementsMap[$serviceId])) {
                foreach ($requirementsMap[$serviceId] as $req) {
                    if (trim($req) !== '') {
                        $stmt = $pdo->prepare("INSERT INTO service_requirements (service_id, requirement) VALUES (?, ?)");
                        $stmt->execute([$serviceId, trim($req)]);
                    }
                }
            }
        }
    }

    $pdo->commit();
    echo json_encode(['status' => 'success', 'message' => 'Department updated successfully!']);
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
?>