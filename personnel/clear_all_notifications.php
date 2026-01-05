<?php
session_start();
include '../conn.php'; // Adjust this path if your connection file is elsewhere

header('Content-Type: application/json');

// 1. Check Authentication
if (!isset($_SESSION['auth_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

try {
    $auth_id = $_SESSION['auth_id'];
    $pdo->beginTransaction();

    // 2. Identify User Role & ID
    // We check if the user is a Resident or Personnel to clear the correct notifications
    
    // Check if Resident
    $stmt = $pdo->prepare("SELECT id FROM residents WHERE auth_id = ?");
    $stmt->execute([$auth_id]);
    $resident_id = $stmt->fetchColumn();

    if ($resident_id) {
        // User is a Resident: Delete all notifications for this resident
        $delStmt = $pdo->prepare("
            DELETE FROM notifications 
            WHERE resident_id = ? 
            AND recipient_type = 'Resident'
        ");
        $delStmt->execute([$resident_id]);
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Notifications cleared']);
        exit();
    }

    // Check if Personnel (Fallback if you use this same file for staff)
    $stmt = $pdo->prepare("SELECT id FROM lgu_personnel WHERE auth_id = ?");
    $stmt->execute([$auth_id]);
    $personnel_id = $stmt->fetchColumn();

    if ($personnel_id) {
        // User is Personnel: Delete all notifications for this personnel
        $delStmt = $pdo->prepare("
            DELETE FROM notifications 
            WHERE personnel_id = ? 
            AND recipient_type = 'Personnel'
        ");
        $delStmt->execute([$personnel_id]);
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Notifications cleared']);
        exit();
    }

    // If neither found
    throw new Exception('User profile not found');

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>