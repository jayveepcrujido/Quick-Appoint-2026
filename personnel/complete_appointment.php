<?php
session_start();

// CRITICAL: Set JSON header BEFORE any output
header('Content-Type: application/json');

include '../conn.php';
require_once '../notification_service.php'; // Go up one directory to root

if (!isset($_SESSION['auth_id']) || $_SESSION['role'] !== 'LGU Personnel') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['appointment_id'])) {
    $id = $_POST['appointment_id'];
    
    try {
        $pdo->beginTransaction();
        
        $stmtP = $pdo->prepare("SELECT id FROM lgu_personnel WHERE auth_id = ?");
        // 1. GET PERSONNEL ID
        $stmtP = $pdo->prepare("SELECT id, first_name, last_name FROM lgu_personnel WHERE auth_id = ?");
        $stmtP->execute([$_SESSION['auth_id']]);
        $personnel = $stmtP->fetch(PDO::FETCH_ASSOC);
        
        if (!$personnel) {
            throw new Exception('Personnel profile not found');
        }
        $current_personnel_id = $personnel['id'];

        // 2. Get FULL appointment details (including resident contact info, service, department)
        $stmt = $pdo->prepare("
            SELECT 
                a.*, 
                r.id as resident_id, 
                r.first_name, 
                r.last_name,
                r.phone_number,
                au.email,
                ds.service_name,
                d.name as department_name
            FROM appointments a
            JOIN residents r ON a.resident_id = r.id
            JOIN auth au ON r.auth_id = au.id
            LEFT JOIN department_services ds ON a.service_id = ds.id
            LEFT JOIN departments d ON a.department_id = d.id
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
        
        // 4. Create notification for the RESIDENT
        $scheduledDate = date('F d, Y', strtotime($appointment['scheduled_for']));
        $timeSlot = date('H', strtotime($appointment['scheduled_for'])) < 12 ? 'AM' : 'PM';
        
        $residentMsg = "Your appointment on {$scheduledDate} ({$timeSlot} slot) has been completed.";
        
        $notifStmt = $pdo->prepare("
            INSERT INTO notifications (
                appointment_id, message, recipient_type, resident_id, personnel_id, is_read
            ) VALUES (?, ?, 'Resident', ?, NULL, 0)
        ");
        $notifStmt->execute([$id, $residentMsg, $appointment['resident_id']]);
        
        // 5. Create notification for the PERSONNEL
        $personnelMsg = "You marked the appointment for {$appointment['first_name']} {$appointment['last_name']} as completed.";
        
        $auditStmt = $pdo->prepare("
            INSERT INTO notifications (
                appointment_id, message, recipient_type, resident_id, personnel_id, is_read
            ) VALUES (?, ?, 'Personnel', NULL, ?, 0)
        ");
        $auditStmt->execute([$id, $personnelMsg, $current_personnel_id]);
        
        // ============================================
        // 6. SEND EMAIL & SMS NOTIFICATIONS
        // ============================================
        
        // Initialize notification service
        $notificationService = new NotificationService(true, true); // Email & SMS enabled
        
        // Prepare data for completion notification
        $completionData = [
            'email' => $appointment['email'],
            'phone' => $appointment['phone_number'],
            'name' => $appointment['first_name'] . ' ' . $appointment['last_name'],
            'service_name' => $appointment['service_name'] ?? 'Service',
            'transaction_id' => $appointment['transaction_id'],
            'department_name' => $appointment['department_name'] ?? 'LGU Office',
            'completed_date' => date('M j, Y'),
            'completed_time' => date('h:i A')
        ];
        
        // Send completion notification
        $notificationResult = $notificationService->sendAppointmentCompletion($completionData);
        
        // Log notification results (for debugging)
        if (!$notificationResult['email']) {
            error_log("Completion Email Failed for Appointment ID {$id}: " . implode(', ', $notificationResult['errors']));
        }
        if (!$notificationResult['sms']) {
            error_log("Completion SMS Failed for Appointment ID {$id}: " . implode(', ', $notificationResult['errors']));
        }
        
        $pdo->commit();
        
        // Build success response with notification status
        $response = [
            'success' => true,
            'message' => 'Appointment marked as completed.',
            'notifications' => [
                'email_sent' => $notificationResult['email'],
                'sms_sent' => $notificationResult['sms']
            ]
        ];
        
        // Add warnings if notifications failed (optional)
        if (!$notificationResult['email'] || !$notificationResult['sms']) {
            $response['warning'] = 'Appointment completed but some notifications failed to send.';
        }
        
        echo json_encode($response);
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Complete Appointment Error: " . $e->getMessage());
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