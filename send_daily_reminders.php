<?php
/**
 * Automated Daily Appointment Reminders
 * File: send_daily_reminders.php
 */

require_once __DIR__ . '/notification_service.php';
require_once __DIR__ . '/db_connection.php'; // Adjust path if needed

// Initialize notification service (SMS only for speed)
$notifier = new NotificationService(false, true); // Email OFF, SMS ON

echo "=== Daily Appointment Reminder System ===\n";
echo "Started at: " . date('Y-m-d H:i:s') . "\n\n";

try {
    // Get appointments scheduled for tomorrow
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    
    $query = "
        SELECT 
            a.id,
            a.transaction_id,
            a.scheduled_for,
            a.status,
            r.first_name,
            r.middle_name,
            r.last_name,
            r.phone_number,
            auth.email,
            ds.service_name,
            d.name as department_name
        FROM appointments a
        INNER JOIN residents r ON a.resident_id = r.id
        INNER JOIN auth ON r.auth_id = auth.id
        INNER JOIN department_services ds ON a.service_id = ds.id
        INNER JOIN departments d ON a.department_id = d.id
        WHERE DATE(a.scheduled_for) = ?
        AND a.status = 'Pending'
        ORDER BY a.scheduled_for ASC
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $tomorrow);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $totalAppointments = $result->num_rows;
    $successCount = 0;
    $failCount = 0;
    $sentAppointments = [];
    
    echo "Found {$totalAppointments} appointments for tomorrow ({$tomorrow})\n";
    echo str_repeat('-', 50) . "\n\n";
    
    if ($totalAppointments > 0) {
        while ($row = $result->fetch_assoc()) {
            // Build full name
            $fullName = trim($row['first_name'] . ' ' . 
                ($row['middle_name'] ? $row['middle_name'] . ' ' : '') . 
                $row['last_name']);
            
            echo "Processing: {$fullName} - {$row['transaction_id']}\n";
            
            // Format date and time
            $appointmentDate = date('M j, Y', strtotime($row['scheduled_for']));
            $appointmentTime = date('g:i A', strtotime($row['scheduled_for']));
            
            // Prepare reminder data
            $reminderData = [
                'phone' => $row['phone_number'],
                'email' => $row['email'],
                'name' => $fullName,
                'service_name' => $row['service_name'],
                'date' => $appointmentDate,
                'time' => $appointmentTime
            ];
            
            // Send reminder (SMS only)
            $reminderResult = $notifier->sendAppointmentReminder($reminderData);
            
            if ($reminderResult['sms']) {
                echo "  ✓ SMS reminder sent to {$row['phone_number']}\n";
                $successCount++;
                $sentAppointments[] = $row['id'];
            } else {
                echo "  ✗ Failed to send reminder\n";
                if (!empty($reminderResult['errors'])) {
                    echo "  Errors: " . implode(', ', $reminderResult['errors']) . "\n";
                }
                
                $failCount++;
                error_log("Reminder failed for appointment ID {$row['id']}: " . 
                    implode(', ', $reminderResult['errors']));
            }
            
            echo "\n";
            
            // Small delay to avoid rate limiting
            usleep(500000); // 0.5 seconds
        }
        
        echo str_repeat('=', 50) . "\n";
        echo "SUMMARY\n";
        echo str_repeat('=', 50) . "\n";
        echo "Total appointments: {$totalAppointments}\n";
        echo "Successfully sent: {$successCount}\n";
        echo "Failed: {$failCount}\n";
        
        if ($totalAppointments > 0) {
            echo "Success rate: " . round(($successCount / $totalAppointments) * 100, 2) . "%\n";
        }
        
        if (!empty($sentAppointments)) {
            echo "\nSuccessfully sent reminders for appointment IDs: " . 
                implode(', ', $sentAppointments) . "\n";
        }
        
    } else {
        echo "No pending appointments found for tomorrow. Nothing to send.\n";
    }
    
    echo "\nCompleted at: " . date('Y-m-d H:i:s') . "\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    error_log("Daily reminder script error: " . $e->getMessage());
}

$conn->close();
?>