<?php
session_start();
if (!isset($_SESSION['auth_id']) || $_SESSION['role'] !== 'Resident') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

include '../../conn.php';

try {
    $stmt = $pdo->prepare("SELECT id FROM residents WHERE auth_id = ?");
    $stmt->execute([$_SESSION['auth_id']]);
    $resident = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$resident) {
        throw new Exception('Resident profile not found');
    }
    
    $resident_id = $resident['id'];
    $appointment_id = intval($_POST['appointment_id']);
    $requested_schedule = $_POST['requested_schedule'];
    $reason = trim($_POST['reason']);
    
    if (empty($requested_schedule) || empty($reason)) {
        throw new Exception('All fields are required');
    }
    
    if (strlen($reason) < 5) {
        throw new Exception('Please provide a more detailed reason (minimum 20 characters)');
    }
    
    $stmt = $pdo->prepare("
        SELECT id, scheduled_for, status 
        FROM appointments 
        WHERE id = ? AND resident_id = ?
    ");
    $stmt->execute([$appointment_id, $resident_id]);
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$appointment) {
        throw new Exception('Appointment not found');
    }
    
    if ($appointment['status'] !== 'No Show') {
        throw new Exception('Only No-Show appointments can be rescheduled');
    }
    
    $stmt = $pdo->prepare("
        SELECT id FROM reschedule_requests 
        WHERE appointment_id = ? AND status = 'Pending'
    ");
    $stmt->execute([$appointment_id]);
    if ($stmt->fetch()) {
        throw new Exception('You already have a pending reschedule request for this appointment');
    }
  
    $requested_time = strtotime($requested_schedule);
    $min_time = strtotime('+24 hours');
    
    if ($requested_time < $min_time) {
        throw new Exception('Requested schedule must be at least 24 hours from now');
    }
    

    $stmt = $pdo->prepare("
        INSERT INTO reschedule_requests 
        (appointment_id, resident_id, old_scheduled_for, requested_schedule, reason) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $appointment_id,
        $resident_id,
        $appointment['scheduled_for'],
        $requested_schedule,
        $reason
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Reschedule request submitted successfully! The department will review your request.'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>