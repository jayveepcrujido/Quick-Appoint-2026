<?php
session_start();
include '../conn.php'; 

header('Content-Type: application/json');

if (!isset($_SESSION['auth_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

try {
    $auth_id = $_SESSION['auth_id'];
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT id FROM residents WHERE auth_id = ?");
    $stmt->execute([$auth_id]);
    $resident_id = $stmt->fetchColumn();

    if ($resident_id) {
        $delStmt = $pdo->prepare("
            DELETE FROM notifications 
            WHERE resident_id = ? 
            AND recipient_type = 'Resident'
        ");
        $delStmt->execute([$resident_id]);
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Resident notifications cleared']);
        exit();
    }

    $stmt = $pdo->prepare("SELECT id FROM lgu_personnel WHERE auth_id = ?");
    $stmt->execute([$auth_id]);
    $personnel_id = $stmt->fetchColumn();

    if ($personnel_id) {
        $delStmt = $pdo->prepare("
            DELETE FROM notifications 
            WHERE personnel_id = ? 
            AND recipient_type = 'Personnel'
        ");
        $delStmt->execute([$personnel_id]);
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Personnel notifications cleared']);
        exit();
    }

    throw new Exception('User profile not found');

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>