<?php
session_start();
include '../conn.php';
include '../send_reset_email.php'; 

header('Content-Type: application/json');

if (!isset($_SESSION['auth_id']) || $_SESSION['role'] !== 'LGU Personnel') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$stmt = $pdo->prepare("
    SELECT p.id as personnel_id, p.department_id, p.first_name, p.last_name, a.email 
    FROM lgu_personnel p 
    JOIN auth a ON p.auth_id = a.id 
    WHERE p.auth_id = ?
");
$stmt->execute([$_SESSION['auth_id']]);
$personnel = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$personnel || !$personnel['department_id']) {
    echo json_encode(['success' => false, 'message' => 'No department assigned']);
    exit();
}

$department_id = $personnel['department_id'];
$personnel_id = $personnel['personnel_id'];

$appointment_id = $_POST['appointment_id'] ?? null;
$new_date_id = $_POST['new_date_id'] ?? null;
$new_time_slot = $_POST['new_time_slot'] ?? null;
$old_date_id = !empty($_POST['old_date_id']) ? $_POST['old_date_id'] : null;
$old_time_slot = !empty($_POST['old_time_slot']) ? $_POST['old_time_slot'] : null;

if (!$appointment_id || !$new_date_id || !$new_time_slot) {
    echo json_encode(['success' => false, 'message' => 'Missing required data']);
    exit();
}

try {
    $pdo->beginTransaction();

    $checkStmt = $pdo->prepare("
        SELECT id, available_date_id, scheduled_for, status
        FROM appointments 
        WHERE id = ? AND department_id = ? 
        AND status IN ('No Show', 'Pending')
    ");
    $checkStmt->execute([$appointment_id, $department_id]);
    $appointment = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$appointment) throw new Exception('Appointment not found or not editable.');

    $dateStmt = $pdo->prepare("SELECT id, DATE(date_time) as date, am_slots, pm_slots, am_booked, pm_booked FROM available_dates WHERE id = ?");
    $dateStmt->execute([$new_date_id]);
    $newDate = $dateStmt->fetch(PDO::FETCH_ASSOC);

    if (!$newDate) throw new Exception('Selected date not found.');

    $isAM = ($new_time_slot === '09:00:00');
    $available = $isAM ? ($newDate['am_slots'] - $newDate['am_booked']) > 0 : ($newDate['pm_slots'] - $newDate['pm_booked']) > 0;
    
    if (!$available) throw new Exception('Selected time slot is fully booked.');

    if ($old_date_id && $old_time_slot) {
        $oldIsAM = ($old_time_slot === '09:00:00');
        $oldColumn = $oldIsAM ? 'am_booked' : 'pm_booked';
        $pdo->prepare("UPDATE available_dates SET $oldColumn = GREATEST(0, $oldColumn - 1) WHERE id = ?")->execute([$old_date_id]);
    }
    
    $newColumn = $isAM ? 'am_booked' : 'pm_booked';
    $pdo->prepare("UPDATE available_dates SET $newColumn = $newColumn + 1 WHERE id = ?")->execute([$new_date_id]);

    $newScheduledFor = $newDate['date'] . ' ' . $new_time_slot;
    $updateStmt = $pdo->prepare("
        UPDATE appointments 
        SET available_date_id = ?, 
            scheduled_for = ?, 
            status = 'Pending', 
            updated_at = NOW(),
            personnel_id = ? 
        WHERE id = ?
    ");
    $updateStmt->execute([$new_date_id, $newScheduledFor, $personnel_id, $appointment_id]);

    $resStmt = $pdo->prepare("
        SELECT r.id as resident_id, r.first_name, r.last_name, au.email, ds.service_name, a.transaction_id
        FROM appointments a
        JOIN residents r ON a.resident_id = r.id
        JOIN auth au ON r.auth_id = au.id
        LEFT JOIN department_services ds ON a.service_id = ds.id
        WHERE a.id = ?
    ");
    $resStmt->execute([$appointment_id]);
    $resInfo = $resStmt->fetch(PDO::FETCH_ASSOC);

    if ($resInfo) {
        $formattedDate = date('F j, Y', strtotime($newDate['date']));
        $formattedTime = date('g:i A', strtotime($new_time_slot));
        $residentMsg = "Your appointment for {$resInfo['service_name']} has been rescheduled to {$formattedDate} at {$formattedTime}.";
        
        $notifStmt = $pdo->prepare("
            INSERT INTO notifications (appointment_id, resident_id, recipient_type, message, created_at, is_read) 
            VALUES (?, ?, 'Resident', ?, NOW(), 0)
        ");
        $notifStmt->execute([$appointment_id, $resInfo['resident_id'], $residentMsg]);

        $personnelMsg = "You rescheduled the appointment for {$resInfo['first_name']} {$resInfo['last_name']} to {$formattedDate} at {$formattedTime}.";
        
        $auditStmt = $pdo->prepare("
            INSERT INTO notifications (appointment_id, personnel_id, recipient_type, message, created_at, is_read) 
            VALUES (?, ?, 'Personnel', ?, NOW(), 0)
        ");
        $auditStmt->execute([$appointment_id, $personnel_id, $personnelMsg]);
    }

    $pdo->commit();

    $formattedDate = date('F j, Y', strtotime($newDate['date']));
    $formattedTime = date('g:i A', strtotime($new_time_slot));

    if ($resInfo) {
        if (function_exists('sendRescheduleNotification')) {
            $resDetails = [
                'service_name' => $resInfo['service_name'],
                'new_date' => $formattedDate,
                'new_time' => $formattedTime,
                'transaction_id' => $resInfo['transaction_id']
            ];
            sendRescheduleNotification($resInfo['email'], $resInfo['first_name'], $resDetails);
        }

        if (function_exists('sendPersonnelRescheduleNotification')) {
            $staffDetails = [
                'resident_name' => $resInfo['first_name'] . ' ' . $resInfo['last_name'],
                'service_name' => $resInfo['service_name'],
                'new_date' => $formattedDate,
                'new_time' => $formattedTime,
                'transaction_id' => $resInfo['transaction_id']
            ];
            sendPersonnelRescheduleNotification($personnel['email'], $personnel['first_name'], $staffDetails);
        }
    }

    echo json_encode([
        'success' => true,
        'message' => "Rescheduled successfully."
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>