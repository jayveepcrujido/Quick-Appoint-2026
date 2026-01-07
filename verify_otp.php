<?php
session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userOTP = $_POST['otp'] ?? '';
    
    if (empty($userOTP)) {
        echo json_encode([
            'success' => false,
            'message' => 'Please enter the OTP code.'
        ]);
        exit;
    }
    
    if (!isset($_SESSION['registration_otp'])) {
        echo json_encode([
            'success' => false,
            'message' => 'No OTP found. Please request a new one.'
        ]);
        exit;
    }
    
    if (time() > $_SESSION['registration_otp_expiry']) {
        unset($_SESSION['registration_otp']);
        unset($_SESSION['registration_otp_expiry']);
        
        echo json_encode([
            'success' => false,
            'message' => 'OTP has expired. Please request a new one.'
        ]);
        exit;
    }
    
    if ($userOTP === $_SESSION['registration_otp']) {
        $_SESSION['otp_verified'] = true;
        
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