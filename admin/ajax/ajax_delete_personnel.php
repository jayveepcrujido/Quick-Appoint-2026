<?php
session_start();
include '../../conn.php';

header('Content-Type: application/json');

try {
    if (!isset($_SESSION['auth_id']) || $_SESSION['role'] !== 'Admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Unauthorized access. Only admins can delete personnel.']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
        exit;
    }

    $id = intval($_POST['id'] ?? 0);

    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid personnel ID.']);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT auth_id, is_department_head, 
               CONCAT(first_name, ' ', last_name) as name,
               (SELECT COUNT(*) FROM lgu_personnel WHERE created_by_personnel_id = ?) as co_personnel_count
        FROM lgu_personnel 
        WHERE id = ?
    ");
    $stmt->execute([$id, $id]);
    $personnel = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$personnel) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Personnel not found.']);
        exit;
    }

    if ($personnel['co_personnel_count'] > 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'message' => 'Cannot delete this personnel. They have ' . $personnel['co_personnel_count'] . ' co-personnel under them. Please delete or reassign the co-personnel first.'
        ]);
        exit;
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("DELETE FROM auth WHERE id = ?");
    $stmt->execute([$personnel['auth_id']]);

    $pdo->commit();

    $role_text = $personnel['is_department_head'] ? 'Department Head' : 'Personnel';
    echo json_encode([
        'success' => true,
        'message' => $role_text . ' deleted successfully!'
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database Error: ' . $e->getMessage()
    ]);
}
?>