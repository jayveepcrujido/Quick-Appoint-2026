<?php
require_once 'sms_functions.php';

$sms = new SMSService();

// Test with YOUR phone number
$testPhone = '+639664318130'; // Replace with your actual number
$testName = 'Arjohn';

$appointmentDetails = [
    'service_name' => 'Test Service',
    'date' => 'Jan 8, 2026',
    'time' => '9:00 AM'
];

echo "Testing SMS reminder...\n";

$result = $sms->sendAppointmentReminder($testPhone, $testName, $appointmentDetails);

if ($result) {
    echo "✅ SMS sent successfully!\n";
} else {
    echo "❌ SMS failed to send\n";
}
?>