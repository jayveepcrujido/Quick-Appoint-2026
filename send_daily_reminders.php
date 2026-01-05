<?php
/**
 * Automated Daily Appointment Reminders
 * File: send_daily_reminders.php
 * 
 * Run this script daily via CRON job to send reminders
 * Cron setup: 0 8 * * * /usr/bin/php /path/to/send_daily_reminders.php
 * (Runs daily at 8:00 AM)
 */

require_once 'notification_service.php';
require_once 'db_connection.php'; // Your database connection

// Initialize notification service
$notifier = new NotificationService(true, true);

echo "=== Daily Appointment Reminder System ===\n";
echo "Started at: " . date('Y-m-d H:i:s') . "\n\n";

try {
    // Get appointments scheduled for tomorrow
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    
    $query = "
        SELECT 
            a.id,
            a.transaction_id,
            a.appointment_date,
            a.appointment_time,
            a.status,
            u.name as resident_name,
            u.email,
            u.phone,
            s.service_name,
            d.department_name
        FROM appointments a
        INNER JOIN users u ON a.user_id = u.id
        INNER JOIN services s ON a.service_id = s.id
        INNER JOIN departments d ON s.department_id = d.id
        WHERE DATE(a.appointment_date) = ?
        AND a.status = 'confirmed'
        AND a.reminder_sent = 0
        ORDER BY a.appointment_time ASC
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $tomorrow);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $totalAppointments = $result->num_rows;
    $successCount = 0;
    $failCount = 0;
    
    echo "Found {$totalAppointments} appointments for tomorrow ({$tomorrow})\n";
    echo str_repeat('-', 50) . "\n\n";
    
    if ($totalAppointments > 0) {
        while ($row = $result->fetch_assoc()) {
            echo "Processing: {$row['resident_name']} - {$row['transaction_id']}\n";
            
            // Format date for SMS
            $appointmentDate = date('M j, Y', strtotime($row['appointment_date']));
            
            // Prepare reminder data
            $reminderData = [
                'phone' => $row['phone'],
                'email' => $row['email'],
                'name' => $row['resident_name'],
                'service_name' => $row['service_name'],
                'date' => $appointmentDate,
                'time' => date('g:i A', strtotime($row['appointment_time']))
            ];
            
            // Send reminder
            $reminderResult = $notifier->sendAppointmentReminder($reminderData);
            
            if ($reminderResult['sms']) {
                echo "  ✓ SMS reminder sent to {$row['phone']}\n";
                
                // Mark as sent in database
                $updateQuery = "UPDATE appointments SET reminder_sent = 1, reminder_sent_at = NOW() WHERE id = ?";
                $updateStmt = $conn->prepare($updateQuery);
                $updateStmt->bind_param("i", $row['id']);
                $updateStmt->execute();
                
                $successCount++;
            } else {
                echo "  ✗ Failed to send reminder\n";
                if (!empty($reminderResult['errors'])) {
                    echo "  Errors: " . implode(', ', $reminderResult['errors']) . "\n";
                }
                
                $failCount++;
                
                // Log failure
                error_log("Reminder failed for appointment ID {$row['id']}: " . implode(', ', $reminderResult['errors']));
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
        echo "Success rate: " . round(($successCount / $totalAppointments) * 100, 2) . "%\n";
        
    } else {
        echo "No appointments found for tomorrow. Nothing to send.\n";
    }
    
    echo "\nCompleted at: " . date('Y-m-d H:i:s') . "\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    error_log("Daily reminder script error: " . $e->getMessage());
}

$conn->close();
?>