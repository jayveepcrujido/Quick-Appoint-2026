<?php
/**
 * PhilSMS Test Script
 * Run this file to test your SMS integration
 */

require 'sms_functions.php'; // Your PhilSMS integration file

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>PhilSMS Integration Test</h1>";
echo "<hr>";

// Initialize SMS Service
$smsService = new SMSService();

// ============================================
// TEST 1: Phone Number Validation
// ============================================
echo "<h2>Test 1: Phone Number Validation</h2>";

$testNumbers = [
    '09171234567',      // Valid
    '639171234567',     // Valid
    '+639171234567',    // Valid
    '9171234567',       // Valid
    '12345',            // Invalid
    '09123',            // Invalid
];

foreach ($testNumbers as $number) {
    $isValid = $smsService->isValidPhilippineNumber($number);
    $status = $isValid ? '<span style="color:green">✓ VALID</span>' : '<span style="color:red">✗ INVALID</span>';
    echo "{$number} - {$status}<br>";
}

echo "<hr>";

// ============================================
// TEST 2: Send Test SMS
// ============================================
echo "<h2>Test 2: Send Test SMS</h2>";

// CHANGE THIS TO YOUR ACTUAL PHONE NUMBER FOR TESTING
$testPhone = '09308875334'; // <-- CHANGE THIS!

echo "<form method='POST' action=''>";
echo "<label>Enter your phone number to receive test SMS:</label><br>";
echo "<input type='text' name='test_phone' value='{$testPhone}' placeholder='09171234567' required style='padding:8px; width:200px;'><br><br>";
echo "<button type='submit' name='send_test' style='padding:10px 20px; background:#4CAF50; color:white; border:none; cursor:pointer;'>Send Test SMS</button>";
echo "</form>";

if (isset($_POST['send_test']) && !empty($_POST['test_phone'])) {
    $phoneNumber = $_POST['test_phone'];
    
    echo "<div style='margin-top:20px; padding:15px; background:#f0f0f0; border-left:4px solid #2196F3;'>";
    echo "<strong>Sending test SMS to: {$phoneNumber}</strong><br><br>";
    
    if (!$smsService->isValidPhilippineNumber($phoneNumber)) {
        echo "<span style='color:red;'>❌ Error: Invalid Philippine phone number format</span>";
    } else {
        $result = $smsService->testConnection($phoneNumber);
        
        if ($result) {
            echo "<span style='color:green;'>✓ SUCCESS! SMS sent successfully!</span><br>";
            echo "<small>Check your phone for the test message.</small>";
        } else {
            echo "<span style='color:red;'>✗ FAILED! SMS could not be sent.</span><br>";
            echo "<small>Check the error logs below for details.</small>";
        }
    }
    echo "</div>";
}

echo "<hr>";

// ============================================
// TEST 3: Test Appointment Confirmation SMS
// ============================================
echo "<h2>Test 3: Appointment Confirmation SMS</h2>";

echo "<form method='POST' action=''>";
echo "<label>Phone Number:</label><br>";
echo "<input type='text' name='appt_phone' placeholder='09171234567' required style='padding:8px; width:200px;'><br><br>";

echo "<label>Recipient Name:</label><br>";
echo "<input type='text' name='appt_name' placeholder='Juan Dela Cruz' required style='padding:8px; width:200px;'><br><br>";

echo "<button type='submit' name='send_appointment' style='padding:10px 20px; background:#2196F3; color:white; border:none; cursor:pointer;'>Send Appointment SMS</button>";
echo "</form>";

if (isset($_POST['send_appointment'])) {
    $phoneNumber = $_POST['appt_phone'];
    $name = $_POST['appt_name'];
    
    $appointmentDetails = [
        'service_name' => 'Birth Certificate Request',
        'date' => date('M j, Y', strtotime('+3 days')),
        'time' => '10:00 AM',
        'transaction_id' => 'TXN-' . date('Ymd') . '-' . rand(1000, 9999),
        'department_name' => 'Civil Registry Office'
    ];
    
    echo "<div style='margin-top:20px; padding:15px; background:#f0f0f0; border-left:4px solid #2196F3;'>";
    echo "<strong>Sending appointment confirmation to: {$phoneNumber}</strong><br><br>";
    
    $result = $smsService->sendAppointmentConfirmation($phoneNumber, $name, $appointmentDetails);
    
    if ($result) {
        echo "<span style='color:green;'>✓ SUCCESS! Appointment SMS sent!</span>";
    } else {
        echo "<span style='color:red;'>✗ FAILED! Could not send SMS.</span>";
    }
    echo "</div>";
}

echo "<hr>";

// ============================================
// TEST 4: Test OTP SMS
// ============================================
echo "<h2>Test 4: OTP Verification SMS</h2>";

echo "<form method='POST' action=''>";
echo "<label>Phone Number:</label><br>";
echo "<input type='text' name='otp_phone' placeholder='09171234567' required style='padding:8px; width:200px;'><br><br>";

echo "<label>Name:</label><br>";
echo "<input type='text' name='otp_name' placeholder='Juan Dela Cruz' required style='padding:8px; width:200px;'><br><br>";

echo "<button type='submit' name='send_otp' style='padding:10px 20px; background:#FF9800; color:white; border:none; cursor:pointer;'>Send OTP SMS</button>";
echo "</form>";

if (isset($_POST['send_otp'])) {
    $phoneNumber = $_POST['otp_phone'];
    $name = $_POST['otp_name'];
    $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    
    echo "<div style='margin-top:20px; padding:15px; background:#fff3e0; border-left:4px solid #FF9800;'>";
    echo "<strong>Sending OTP to: {$phoneNumber}</strong><br>";
    echo "<strong>OTP Code: {$otp}</strong><br><br>";
    
    $result = $smsService->sendVerificationOTP($phoneNumber, $otp);
    
    if ($result) {
        echo "<span style='color:green;'>✓ SUCCESS! OTP SMS sent!</span>";
    } else {
        echo "<span style='color:red;'>✗ FAILED! Could not send OTP.</span>";
    }
    echo "</div>";
}

echo "<hr>";

// ============================================
// Display Recent Error Logs
// ============================================
echo "<h2>Recent Error Logs</h2>";
echo "<div style='background:#f9f9f9; padding:15px; border:1px solid #ddd; max-height:300px; overflow-y:auto;'>";
echo "<pre style='margin:0; font-size:12px;'>";

// Read last 50 lines from PHP error log
$errorLog = ini_get('error_log');
if ($errorLog && file_exists($errorLog)) {
    $lines = file($errorLog);
    $recentLines = array_slice($lines, -50);
    
    foreach ($recentLines as $line) {
        // Only show PhilSMS related logs
        if (stripos($line, 'philsms') !== false || stripos($line, 'sms') !== false) {
            echo htmlspecialchars($line);
        }
    }
} else {
    echo "Error log not found or not configured.\n";
    echo "Current error_log setting: " . ($errorLog ?: 'Not set') . "\n";
}

echo "</pre>";
echo "</div>";

echo "<hr>";

// ============================================
// API Information
// ============================================
echo "<h2>PhilSMS Configuration</h2>";
echo "<table border='1' cellpadding='10' style='border-collapse:collapse;'>";
echo "<tr><td><strong>API Endpoint:</strong></td><td>https://dashboard.philsms.com/api/v3/sms/send</td></tr>";
echo "<tr><td><strong>API Token:</strong></td><td>819|BHD3j... (configured)</td></tr>";
echo "<tr><td><strong>Sender ID:</strong></td><td>PhilSMS</td></tr>";
echo "<tr><td><strong>Message Type:</strong></td><td>Plain Text</td></tr>";
echo "<tr><td><strong>Character Limit:</strong></td><td>160 characters (single SMS)</td></tr>";
echo "</table>";

echo "<hr>";
echo "<p><small>Last tested: " . date('Y-m-d H:i:s') . "</small></p>";
?>

<style>
    body {
        font-family: Arial, sans-serif;
        max-width: 800px;
        margin: 20px auto;
        padding: 20px;
        background: #f5f5f5;
    }
    h1, h2 {
        color: #333;
    }
    hr {
        margin: 30px 0;
        border: none;
        border-top: 2px solid #ddd;
    }
    input, button {
        font-size: 14px;
    }
    button:hover {
        opacity: 0.9;
    }
</style>