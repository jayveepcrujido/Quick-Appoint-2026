<?php
session_start();
include '../conn.php';

header('Content-Type: application/json');

if (!isset($_SESSION['auth_id'])) {
    echo json_encode(['hasNew' => false, 'count' => 0]);
    exit;
}

$authId = $_SESSION['auth_id'];

try {
    $personnelStmt = $pdo->prepare("SELECT id FROM lgu_personnel WHERE auth_id = ? LIMIT 1");
    $personnelStmt->execute([$authId]);
    $personnelData = $personnelStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$personnelData) {
        echo json_encode(['hasNew' => false, 'count' => 0]);
        exit;
    }
    
    $personnelId = $personnelData['id'];
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as unread_count 
        FROM notifications n
        INNER JOIN appointments a ON n.appointment_id = a.id
        WHERE a.personnel_id = ? AND n.is_read = 0
    ");
    
    $stmt->execute([$personnelId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $unreadCount = (int)$result['unread_count'];
    
    echo json_encode([
        'hasNew' => $unreadCount > 0,
        'count' => $unreadCount,
        'success' => true
    ]);
    
} catch (PDOException $e) {
    error_log("Error checking notifications: " . $e->getMessage());
    echo json_encode([
        'hasNew' => false, 
        'count' => 0,
        'success' => false,
        'error' => 'Database error'
    ]);
}
?>