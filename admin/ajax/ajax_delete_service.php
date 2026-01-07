<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['auth_id']) || $_SESSION['role'] !== 'Admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

include '../../conn.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['service_id'])) {
    $serviceId = $_POST['service_id'];
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("DELETE FROM service_requirements WHERE service_id = ?");
        $stmt->execute([$serviceId]);

        $stmt = $pdo->prepare("DELETE FROM department_services WHERE id = ?");
        $stmt->execute([$serviceId]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Service deleted successfully'
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to delete service: ' . $e->getMessage()
        ]);
    }
} else {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request'
    ]);
}
?>