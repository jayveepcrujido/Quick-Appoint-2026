<?php
/**
 * PhilSMS Integration for LGU Quick Appoint - FIXED VERSION
 * File: sms_functions.php
 * The API requires JSON format, not form-encoded
 */

class SMSService {
    private $apiToken;
    private $apiUrl;
    private $senderId;
    
    public function __construct() {
        $this->apiToken = '819|BHD3jSSjK2ExkHxIPFD1nXUjXTT6G9XvAnOGOgJB71d1051f';
        $this->apiUrl = 'https://dashboard.philsms.com/api/v3/sms/send';
        $this->senderId = 'PhilSMS'; // Default sender ID
    }
    
    private function sendSMS($recipient, $message) {
        $recipient = $this->formatPhoneNumber($recipient);
        
        $postData = [
            'recipient' => $recipient,
            'sender_id' => $this->senderId,
            'type' => 'plain',
            'message' => $message
        ];
        
        $jsonData = json_encode($postData);
        
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->apiUrl,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonData, 
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiToken,
                'Content-Type: application/json',
                'Accept: application/json',
                'Content-Length: ' . strlen($jsonData)
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        
        curl_close($ch);
        
        error_log("PhilSMS Response Code: " . $httpCode);
        error_log("PhilSMS Response: " . $response);
        
        if ($curlError) {
            error_log("PhilSMS cURL Error: " . $curlError);
            return false;
        }
        
        $responseData = json_decode($response, true);
        
        if ($httpCode == 200 && isset($responseData['status'])) {
            if (in_array(strtolower($responseData['status']), ['success', 'ok', 'sent', 'queued'])) {
                return true;
            }
        }
        
        if (isset($responseData['message'])) {
            error_log("PhilSMS Error Message: " . $responseData['message']);
        }
        if (isset($responseData['error'])) {
            error_log("PhilSMS Error: " . $responseData['error']);
        }
        if (isset($responseData['errors'])) {
            error_log("PhilSMS Errors: " . print_r($responseData['errors'], true));
        }
        
        return false;
    }
    
    /**
     * Format Philippine phone number for PhilSMS
     * Converts: 09171234567 -> 639171234567
     */
    private function formatPhoneNumber($number) {
        $number = preg_replace('/[^0-9]/', '', $number);
        
        if (substr($number, 0, 1) == '+') {
            $number = substr($number, 1);
        }
        
        if (substr($number, 0, 1) == '0') {
            $number = '63' . substr($number, 1);
        }
        elseif (substr($number, 0, 2) != '63') {
            $number = '63' . $number;
        }
        
        return $number;
    }
    
    /**
     * Send appointment confirmation SMS
     */
    public function sendAppointmentConfirmation($phoneNumber, $recipientName, $appointmentDetails) {
    $message = "LGU QuickAppoint - Hi {$recipientName}! Your appointment for {$appointmentDetails['service_name']} "
             . "is CONFIRMED on {$appointmentDetails['date']} at {$appointmentDetails['time']}. "
             . "Ref: {$appointmentDetails['transaction_id']}.";
    
    if (strlen($message) > 160) {
        $message = $this->truncateMessage($message, 160);
    }
    
    return $this->sendSMS($phoneNumber, $message);
}
    
    /**
     * Send password reset OTP via SMS
     */
    public function sendPasswordResetOTP($phoneNumber, $recipientName, $otp) {
        $message = "Hi {$recipientName}, your LGU QuickAppoint password reset code is: {$otp}. "
                 . "Valid for 5 minutes. Do NOT share this code.";
        
        return $this->sendSMS($phoneNumber, $message);
    }
    
    /**
     * Send reschedule notification SMS
     */
    public function sendRescheduleNotification($phoneNumber, $recipientName, $details) {
        $message = "Hi {$recipientName}! Your appointment has been RESCHEDULED to "
                 . "{$details['new_date']} at {$details['new_time']}. "
                 . "Ref: {$details['transaction_id']}. -LGU QuickAppoint";
        
        return $this->sendSMS($phoneNumber, $message);
    }
    
    /**
     * Send appointment reminder (1 day before)
     */
    public function sendAppointmentReminder($phoneNumber, $recipientName, $appointmentDetails) {
        $message = "REMINDER: Hi {$recipientName}! Your appointment is TOMORROW "
                 . "{$appointmentDetails['date']} at {$appointmentDetails['time']} "
                 . "for {$appointmentDetails['service_name']}. Don't forget! -LGU QuickAppoint";
        
        return $this->sendSMS($phoneNumber, $message);
    }
    
    /**
     * Send cancellation notification
     */
    public function sendCancellationNotification($phoneNumber, $recipientName, $transactionId, $reason = '') {
        $message = "Hi {$recipientName}, your appointment (Ref: {$transactionId}) "
                 . "has been CANCELLED";
        
        if (!empty($reason)) {
            $message .= ". Reason: {$reason}";
        }
        
        $message .= ". Contact us for rebooking. -LGU QuickAppoint";
        
        if (strlen($message) > 160) {
            $message = $this->truncateMessage($message, 160);
        }
        
        return $this->sendSMS($phoneNumber, $message);
    }
    
    /**
     * Send verification OTP
     */
    public function sendVerificationOTP($phoneNumber, $otp) {
        $message = "Your LGU QuickAppoint verification code is: {$otp}. "
                 . "Valid for 5 minutes. Do NOT share this code with anyone.";
        
        return $this->sendSMS($phoneNumber, $message);
    }
    
    /**
     * Send appointment status update
     */
    public function sendStatusUpdate($phoneNumber, $recipientName, $transactionId, $newStatus) {
        $statusMessages = [
            'pending' => 'is now PENDING review',
            'confirmed' => 'has been CONFIRMED',
            'completed' => 'has been COMPLETED',
            'cancelled' => 'has been CANCELLED',
            'rescheduled' => 'has been RESCHEDULED'
        ];
        
        $statusText = $statusMessages[$newStatus] ?? 'status has been updated';
        
        $message = "Hi {$recipientName}, your appointment (Ref: {$transactionId}) {$statusText}. "
                 . "Check your email for details. -LGU QuickAppoint";
        
        return $this->sendSMS($phoneNumber, $message);
    }
    
    /**
     * Truncate message to fit SMS character limit
     */
    private function truncateMessage($message, $maxLength = 160) {
        if (strlen($message) <= $maxLength) {
            return $message;
        }
        
        return substr($message, 0, $maxLength - 3) . '...';
    }
    
    /**
     * Validate Philippine phone number format
     */
    public function isValidPhilippineNumber($number) {
        $number = preg_replace('/[^0-9]/', '', $number);
        
        $patterns = [
            '/^09\d{9}$/',      
            '/^639\d{9}$/',     
            '/^9\d{9}$/'       
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $number)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Test SMS connection
     */
    public function testConnection($phoneNumber) {
        $message = "Test message from LGU QuickAppoint. Your SMS integration is working!";
        return $this->sendSMS($phoneNumber, $message);
    }
}
?>