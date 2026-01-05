<?php
session_start();
include '../conn.php'; // Adjust path to your database connection if necessary

header('Content-Type: application/json');

// 1. Check Authentication
if (!isset($_SESSION['auth_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

try {
    $auth_id = $_SESSION['auth_id'];
    $pdo->beginTransaction();

    // 2. CHECK IF USER IS A RESIDENT
    $stmt = $pdo->prepare("SELECT id FROM residents WHERE auth_id = ?");
    $stmt->execute([$auth_id]);
    $resident_id = $stmt->fetchColumn();

    if ($resident_id) {
        // User is a Resident: Delete notifications specifically for them
        // We filter by recipient_type = 'Resident' to be safe
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

    // 3. CHECK IF USER IS PERSONNEL (Fallback for reusability)
    $stmt = $pdo->prepare("SELECT id FROM lgu_personnel WHERE auth_id = ?");
    $stmt->execute([$auth_id]);
    $personnel_id = $stmt->fetchColumn();

    if ($personnel_id) {
        // User is Personnel: Delete notifications specifically for them
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

    // If auth_id exists but matches no profile
    throw new Exception('User profile not found');

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>