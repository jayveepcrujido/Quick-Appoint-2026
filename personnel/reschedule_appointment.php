<?php
session_start();
include '../conn.php';

header('Content-Type: application/json');

if (!isset($_SESSION['auth_id']) || $_SESSION['role'] !== 'LGU Personnel') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $appointment_id = $_POST['appointment_id'] ?? null;
    $reschedule_date = $_POST['reschedule_date'] ?? null;
    $time_slot = $_POST['time_slot'] ?? null;

    if (!$appointment_id || !$reschedule_date || !$time_slot) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit();
    }

    $time = ($time_slot === 'morning') ? '09:00:00' : '14:00:00';
    $scheduled_for = $reschedule_date . ' ' . $time;

    try {
        $checkStmt = $pdo->prepare("
            SELECT id FROM appointments 
            WHERE scheduled_for = ? 
            AND status NOT IN ('Cancelled', 'Completed', 'Rejected') 
            AND id != ?
        ");
        $checkStmt->execute([$scheduled_for, $appointment_id]);

        if ($checkStmt->rowCount() > 0) {
            echo json_encode(['success' => false, 'message' => 'This time slot is already fully booked. Please choose another.']);
            exit();
        }

        $new_status = 'Rescheduled';

        $stmt = $pdo->prepare("
            UPDATE appointments 
            SET scheduled_for = ?, 
                status = ?, 
                updated_at = NOW() 
            WHERE id = ?
        ");
        
        $stmt->execute([$scheduled_for, $new_status, $appointment_id]);

        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Appointment rescheduled successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'No changes made or appointment not found.']);
        }

    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>