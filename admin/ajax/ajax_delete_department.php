<?php
include '../../conn.php';

header('Content-Type: application/json');

if (!isset($_POST['id'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Department ID is required']);
    exit();
}

$id = $_POST['id'];

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("DELETE sr FROM service_requirements sr 
                           INNER JOIN department_services ds ON sr.service_id = ds.id 
                           WHERE ds.department_id = ?");
    $stmt->execute([$id]);

    $stmt = $pdo->prepare("DELETE FROM department_services WHERE department_id = ?");
    $stmt->execute([$id]);

    $stmt = $pdo->prepare("DELETE FROM departments WHERE id = ?");
    $stmt->execute([$id]);

    $pdo->commit();

    echo json_encode([
        'status' => 'success', 
        'message' => 'Department deleted successfully'
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'status' => 'error', 
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>