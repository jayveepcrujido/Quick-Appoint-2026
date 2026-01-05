<?php
session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userOTP = $_POST['otp'] ?? '';
    
    // Validate OTP input
    if (empty($userOTP)) {
        echo json_encode([
            'success' => false,
            'message' => 'Please enter the OTP code.'
        ]);
        exit;
    }
    
    // Check if OTP exists in session
    if (!isset($_SESSION['registration_otp'])) {
        echo json_encode([
            'success' => false,
            'message' => 'No OTP found. Please request a new one.'
        ]);
        exit;
    }
    
    // Check if OTP has expired
    if (time() > $_SESSION['registration_otp_expiry']) {
        // Clear expired OTP
        unset($_SESSION['registration_otp']);
        unset($_SESSION['registration_otp_expiry']);
        
        echo json_encode([
            'success' => false,
            'message' => 'OTP has expired. Please request a new one.'
        ]);
        exit;
    }
    
    // Verify OTP
    if ($userOTP === $_SESSION['registration_otp']) {
        // Mark OTP as verified
        $_SESSION['otp_verified'] = true;
        
        // Clear OTP from session (one-time use)
        unset($_SESSION['registration_otp']);
        unset($_SESSION['registration_otp_expiry']);
        
        echo json_encode([
            'success' => true,
            'message' => 'OTP verified successfully!'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid OTP. Please try again.'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method.'
    ]);
}
?>