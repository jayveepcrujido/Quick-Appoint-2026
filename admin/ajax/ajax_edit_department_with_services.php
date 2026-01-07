<?php
include '../../conn.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $deptId = $_POST['id'] ?? '';
    $name = $_POST['name'] ?? '';
    $desc = $_POST['description'] ?? '';
    $services = $_POST['services'] ?? [];
    $requirements = $_POST['requirements'] ?? [];

    if (!$deptId || !$name || empty($services)) {
        http_response_code(400);
        echo "Invalid department data.";
        exit();
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("UPDATE departments SET name = ?, description = ? WHERE id = ?");
        $stmt->execute([$name, $desc, $deptId]);

        $oldServices = $pdo->prepare("SELECT id FROM department_services WHERE department_id = ?");
        $oldServices->execute([$deptId]);
        $serviceIds = $oldServices->fetchAll(PDO::FETCH_COLUMN);

        if ($serviceIds) {
            $in = str_repeat('?,', count($serviceIds) - 1) . '?';
            $pdo->prepare("DELETE FROM service_requirements WHERE service_id IN ($in)")->execute($serviceIds);
        }

        $pdo->prepare("DELETE FROM department_services WHERE department_id = ?")->execute([$deptId]);

        $svcStmt = $pdo->prepare("INSERT INTO department_services (department_id, service_name) VALUES (?, ?)");
        $reqStmt = $pdo->prepare("INSERT INTO service_requirements (service_id, requirement) VALUES (?, ?)");

        $reqIndex = 0;
        foreach ($services as $svc) {
            if (trim($svc) !== '') {
                $svcStmt->execute([$deptId, trim($svc)]);
                $newServiceId = $pdo->lastInsertId();

                while ($reqIndex < count($requirements) && trim($requirements[$reqIndex]) !== '') {
                    $reqStmt->execute([$newServiceId, trim($requirements[$reqIndex])]);
                    $reqIndex++;

                    if (isset($services[$reqIndex])) break;
                }
            }
        }

        $pdo->commit();
        echo "success";

    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo "Database error: " . $e->getMessage();
    }
} else {
    http_response_code(400);
    echo "Invalid request";
}
?>
