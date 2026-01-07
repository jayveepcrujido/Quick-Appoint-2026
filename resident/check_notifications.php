<?php
session_start();

include '../conn.php'; 

if (!isset($_SESSION['auth_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$authId = $_SESSION['auth_id'];

session_write_close(); 

$unreadCount = 0;
$residentId = null;

try {
    $residentStmt = $pdo->prepare("SELECT id FROM residents WHERE auth_id = ? LIMIT 1");
    $residentStmt->execute([$authId]);
    $residentData = $residentStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$residentData) {
        http_response_code(404);
        echo json_encode(['error' => 'Resident profile not found']);
        exit();
    }
    
    $residentId = $residentData['id'];

    $sql = "SELECT COUNT(*) as count 
            FROM appointments 
            WHERE resident_id = ? 
            AND status = 'Completed'
            AND is_seen_by_resident = 0";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$residentId]);
    
    $unreadCount = (int)$stmt->fetchColumn(); 

    header('Content-Type: application/json');
    echo json_encode(['unreadCount' => $unreadCount]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}

?>


