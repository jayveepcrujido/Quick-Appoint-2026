<?php
session_start();
include '../conn.php';

if (!isset($_SESSION['auth_id']) || $_SESSION['role'] !== 'LGU Personnel') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['appointment_id'])) {
    $id = $_POST['appointment_id'];
    
    try {
        $pdo->beginTransaction();
        
        $stmtP = $pdo->prepare("SELECT id FROM lgu_personnel WHERE auth_id = ?");
        $stmtP->execute([$_SESSION['auth_id']]);
        $personnel = $stmtP->fetch(PDO::FETCH_ASSOC);
        
        if (!$personnel) {
            throw new Exception('Personnel profile not found');
        }
        $current_personnel_id = $personnel['id'];

        $stmt = $pdo->prepare("
            SELECT a.*, r.id as resident_id, r.first_name, r.last_name
            FROM appointments a
            JOIN residents r ON a.resident_id = r.id
            WHERE a.id = ?
        ");
        $stmt->execute([$id]);
        $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$appointment) {
            throw new Exception('Appointment not found');
        }
        
        $updateQuery = "UPDATE appointments SET status = 'Completed', updated_at = NOW() WHERE id = :id";
        $updateStmt = $pdo->prepare($updateQuery);
        $updateStmt->execute(['id' => $id]);
        
        $scheduledDate = date('F d, Y', strtotime($appointment['scheduled_for']));
        $timeSlot = date('H', strtotime($appointment['scheduled_for'])) < 12 ? 'AM' : 'PM';
        
        $residentMsg = "Your appointment on {$scheduledDate} ({$timeSlot} slot) has been completed.";
        
        $notifStmt = $pdo->prepare("
            INSERT INTO notifications (
                appointment_id, message, recipient_type, resident_id, personnel_id, is_read
            ) VALUES (?, ?, 'Resident', ?, NULL, 0)
        ");
        $notifStmt->execute([$id, $residentMsg, $appointment['resident_id']]);
        
        $personnelMsg = "You marked the appointment for {$appointment['first_name']} {$appointment['last_name']} as completed.";
        
        $auditStmt = $pdo->prepare("
            INSERT INTO notifications (
                appointment_id, message, recipient_type, resident_id, personnel_id, is_read
            ) VALUES (?, ?, 'Personnel', NULL, ?, 0)
        ");
        $auditStmt->execute([$id, $personnelMsg, $current_personnel_id]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Appointment marked as completed.'
        ]);
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update status: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request'
    ]);
}
?>