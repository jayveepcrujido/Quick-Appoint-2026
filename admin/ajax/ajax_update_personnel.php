<?php
session_start();
include '../../conn.php';

header('Content-Type: application/json');

try {
    if (!isset($_SESSION['auth_id']) || $_SESSION['role'] !== 'Admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Unauthorized access. Only admins can update personnel.']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
        exit;
    }

    $id = intval($_POST['id'] ?? 0);
    $first_name = trim($_POST['first_name'] ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $department_id = intval($_POST['department_id'] ?? 0);
    $email = trim($_POST['email'] ?? '');
    $is_department_head = isset($_POST['is_department_head']) ? 1 : 0;

    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid personnel ID.']);
        exit;
    }

    if (empty($first_name) || empty($last_name) || empty($email)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'All required fields must be filled.']);
        exit;
    }

    if ($department_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Please select a valid department.']);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid email format.']);
        exit;
    }

    $first_name = htmlspecialchars($first_name, ENT_QUOTES, 'UTF-8');
    $middle_name = htmlspecialchars($middle_name, ENT_QUOTES, 'UTF-8');
    $last_name = htmlspecialchars($last_name, ENT_QUOTES, 'UTF-8');
    $email = filter_var($email, FILTER_SANITIZE_EMAIL);

    $stmt = $pdo->prepare("SELECT auth_id FROM lgu_personnel WHERE id = ?");
    $stmt->execute([$id]);
    $auth_id = $stmt->fetchColumn();

    if (!$auth_id) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Personnel not found.']);
        exit;
    }

    $check = $pdo->prepare("SELECT id FROM auth WHERE email = ? AND id != ?");
    $check->execute([$email, $auth_id]);
    if ($check->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Email address already exists for another user.']);
        exit;
    }

    if ($is_department_head) {
        $check_head = $pdo->prepare("
            SELECT id FROM lgu_personnel 
            WHERE department_id = ? AND is_department_head = 1 AND id != ?
        ");
        $check_head->execute([$department_id, $id]);
        if ($check_head->fetch()) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'This department already has a department head. Please remove the existing head first.']);
            exit;
        }
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        UPDATE lgu_personnel 
        SET first_name = ?, middle_name = ?, last_name = ?, department_id = ?, is_department_head = ?
        WHERE id = ?
    ");
    $stmt->execute([$first_name, $middle_name, $last_name, $department_id, $is_department_head, $id]);

    $stmt = $pdo->prepare("UPDATE auth SET email = ? WHERE id = ?");
    $stmt->execute([$email, $auth_id]);

    $pdo->commit();
    
    $role_text = $is_department_head ? 'Department Head' : 'Personnel';
    echo json_encode([
        'success' => true, 
        'message' => $role_text . ' updated successfully!'
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