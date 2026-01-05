<?php
session_start();
if (!isset($_SESSION['auth_id']) || $_SESSION['role'] !== 'LGU Personnel') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

include '../../conn.php';
include '../../send_reset_email.php';

try {
    $request_id = intval($_POST['request_id']);
    $action = $_POST['action'];
    $rejection_reason = trim($_POST['rejection_reason'] ?? '');
    
    // Get personnel ID
    $stmt = $pdo->prepare("SELECT id FROM lgu_personnel WHERE auth_id = ?");
    $stmt->execute([$_SESSION['auth_id']]);
    $personnel = $stmt->fetch(PDO::FETCH_ASSOC);
    $personnel_id = $personnel['id'];
    
    // Get request details - make sure we get the RESIDENT who requested the reschedule
    $stmt = $pdo->prepare("
        SELECT 
            rr.id as request_id,
            rr.appointment_id,
            rr.resident_id,
            rr.requested_schedule,
            rr.status as request_status,
            a.transaction_id,
            CONCAT(r.first_name, ' ', r.last_name) AS resident_name,
            au.email AS resident_email,
            d.name AS department_name,
            ds.service_name
        FROM reschedule_requests rr
        JOIN appointments a ON rr.appointment_id = a.id
        JOIN residents r ON rr.resident_id = r.id
        JOIN auth au ON r.auth_id = au.id
        JOIN departments d ON a.department_id = d.id
        JOIN department_services ds ON a.service_id = ds.id
        WHERE rr.id = ?
    ");
    $stmt->execute([$request_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$request) {
        throw new Exception('Request not found');
    }
    
    if ($request['request_status'] !== 'Pending') {
        throw new Exception('This request has already been reviewed');
    }
    
    $pdo->beginTransaction();
    
    if ($action === 'approve') {
        // Update reschedule request status
        $stmt = $pdo->prepare("
            UPDATE reschedule_requests 
            SET status = 'Approved', 
                reviewed_by_personnel_id = ?, 
                reviewed_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$personnel_id, $request_id]);
        
        // Update appointment
        $stmt = $pdo->prepare("
            UPDATE appointments 
            SET status = 'Pending', 
                scheduled_for = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$request['requested_schedule'], $request['appointment_id']]);
        
        // Create notification FOR THE RESIDENT - FIXED VERSION
        $newDate = date('F d, Y', strtotime($request['requested_schedule']));
        $newHour = date('H', strtotime($request['requested_schedule']));
        $newTimeSlot = $newHour < 12 ? 'AM' : 'PM';
        
        $message = "Your reschedule request has been approved. New appointment: {$newDate} ({$newTimeSlot} slot)";
        
        $stmt = $pdo->prepare("
            INSERT INTO notifications (
                appointment_id, 
                message, 
                recipient_type, 
                resident_id, 
                personnel_id,
                is_read
            ) VALUES (?, ?, 'Resident', ?, NULL, 0)
        ");
        $stmt->execute([
            $request['appointment_id'],
            $message,
            $request['resident_id']
        ]);

        $personnelMsg = "You approved the reschedule request for {$request['resident_name']}. New date: {$newDate}.";
        $stmt = $pdo->prepare("
            INSERT INTO notifications (
                appointment_id, 
                message, 
                recipient_type, 
                resident_id, 
                personnel_id, 
                is_read
            ) VALUES (?, ?, 'Personnel', NULL, ?, 0)
        ");
        $stmt->execute([
            $request['appointment_id'], 
            $personnelMsg, 
            $personnel_id
        ]);
        
        // SEND APPROVAL EMAIL TO THE RESIDENT
        $emailDetails = [
            'service_name' => $request['service_name'],
            'department_name' => $request['department_name'],
            'new_date' => date('F d, Y', strtotime($request['requested_schedule'])),
            'new_time' => date('h:i A', strtotime($request['requested_schedule'])),
            'transaction_id' => $request['transaction_id']
        ];
        
        $emailSent = sendRescheduleApprovedEmail(
            $request['resident_email'],
            $request['resident_name'],
            $emailDetails
        );
        
        $pdo->commit();
        echo json_encode([
            'success' => true, 
            'message' => 'Reschedule request approved successfully' . ($emailSent ? ' and email sent to resident!' : '')
        ]);
        
    } elseif ($action === 'reject') {
        if (empty($rejection_reason)) {
            throw new Exception('Please provide a reason for rejection');
        }
        
        // Update reschedule request status
        $stmt = $pdo->prepare("
            UPDATE reschedule_requests 
            SET status = 'Rejected', 
                reviewed_by_personnel_id = ?, 
                reviewed_at = NOW(),
                rejection_reason = ?
            WHERE id = ?
        ");
        $stmt->execute([$personnel_id, $rejection_reason, $request_id]);
        
        // Create notification FOR THE RESIDENT - FIXED VERSION
        $message = "Your reschedule request has been rejected. Reason: {$rejection_reason}";
        
        $stmt = $pdo->prepare("
            INSERT INTO notifications (
                appointment_id, 
                message, 
                recipient_type, 
                resident_id, 
                personnel_id,
                is_read
            ) VALUES (?, ?, 'Resident', ?, NULL, 0)
        ");
        $stmt->execute([
            $request['appointment_id'],
            $message,
            $request['resident_id']
        ]);
        
        $personnelMsg = "You rejected the reschedule request for {$request['resident_name']}.";
        $stmt = $pdo->prepare("
            INSERT INTO notifications (
                appointment_id, 
                message, 
                recipient_type, 
                resident_id, 
                personnel_id, 
                is_read
            ) VALUES (?, ?, 'Personnel', NULL, ?, 0)
        ");
        $stmt->execute([
            $request['appointment_id'], 
            $personnelMsg, 
            $personnel_id
        ]);
        // SEND REJECTION EMAIL TO THE RESIDENT
        $emailDetails = [
            'service_name' => $request['service_name'],
            'department_name' => $request['department_name'],
            'transaction_id' => $request['transaction_id'],
            'rejection_reason' => $rejection_reason
        ];
        
        $emailSent = sendRescheduleRejectedEmail(
            $request['resident_email'],
            $request['resident_name'],
            $emailDetails
        );
        
        $pdo->commit();
        echo json_encode([
            'success' => true, 
            'message' => 'Reschedule request rejected' . ($emailSent ? ' and email sent to resident' : '')
        ]);
    }
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>