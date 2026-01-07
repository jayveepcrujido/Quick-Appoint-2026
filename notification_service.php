<?php
require_once 'send_reset_email.php'; 
require_once 'sms_functions.php'; 

class NotificationService {
    private $smsService;
    private $emailEnabled;
    private $smsEnabled;
    
    public function __construct($enableEmail = true, $enableSMS = true) {
        $this->smsService = new SMSService();
        $this->emailEnabled = $enableEmail;
        $this->smsEnabled = $enableSMS;
    }
    
    /**
     * Send Appointment COMPLETION Notification (Email + SMS) - NEW METHOD!
     * 
     * @param array $data Completion data
     * @return array Results
     */
    public function sendAppointmentCompletion($data) {
        $results = [
            'email' => false,
            'sms' => false,
            'errors' => []
        ];
        
        // Prepare completion details for email
        $emailDetails = [
            'service_name' => $data['service_name'] ?? 'Service',
            'transaction_id' => $data['transaction_id'] ?? 'N/A',
            'department_name' => $data['department_name'] ?? 'LGU Office',
            'completed_date' => $data['completed_date'] ?? date('M j, Y'),
            'completed_time' => $data['completed_time'] ?? date('h:i A')
        ];
        
        // Send Email (using the new function we'll add below)
        if ($this->emailEnabled && !empty($data['email'])) {
            try {
                $results['email'] = sendAppointmentCompletionEmail(
                    $data['email'],
                    $data['name'],
                    $emailDetails
                );
                
                if (!$results['email']) {
                    $results['errors'][] = "Email: Failed to send completion notification";
                }
            } catch (Exception $e) {
                $results['errors'][] = "Email Error: " . $e->getMessage();
                error_log("Completion email failed: " . $e->getMessage());
            }
        }
        
        // Send SMS
        if ($this->smsEnabled && !empty($data['phone'])) {
            try {
                $smsDetails = [
                    'service_name' => $emailDetails['service_name'],
                    'transaction_id' => $emailDetails['transaction_id']
                ];
                
                $results['sms'] = $this->smsService->sendAppointmentCompletion(
                    $data['phone'],
                    $data['name'],
                    $smsDetails
                );
                
                if (!$results['sms']) {
                    $results['errors'][] = "SMS: Failed to send completion notification";
                }
            } catch (Exception $e) {
                $results['errors'][] = "SMS Error: " . $e->getMessage();
                error_log("Completion SMS failed: " . $e->getMessage());
            }
        }
        
        return $results;
    }
    
    /**
     * Send Password Reset (Email + SMS with OTP)
     * 
     * @param string $email Recipient email
     * @param string $phone Recipient phone number
     * @param string $name Recipient name
     * @param string $resetLink Password reset link
     * @param string $otp Optional OTP code for SMS
     * @return array Results of both email and SMS
     */
    public function sendPasswordReset($email, $phone, $name, $resetLink, $otp = null) {
        $results = [
            'email' => false,
            'sms' => false,
            'errors' => []
        ];
        
        // Send Email
        if ($this->emailEnabled && !empty($email)) {
            try {
                $emailResult = sendResetEmail($email, $name, $resetLink);
                $results['email'] = ($emailResult === true);
                
                if (!$results['email']) {
                    $results['errors'][] = "Email: " . $emailResult;
                }
            } catch (Exception $e) {
                $results['errors'][] = "Email Error: " . $e->getMessage();
                error_log("Password reset email failed: " . $e->getMessage());
            }
        }
        
        if ($this->smsEnabled && !empty($phone)) {
            try {
                if (empty($otp)) {
                    $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
                }
                
                $results['sms'] = $this->smsService->sendPasswordResetOTP($phone, $name, $otp);
                $results['otp'] = $otp; 
                
                if (!$results['sms']) {
                    $results['errors'][] = "SMS: Failed to send OTP";
                }
            } catch (Exception $e) {
                $results['errors'][] = "SMS Error: " . $e->getMessage();
                error_log("Password reset SMS failed: " . $e->getMessage());
            }
        }
        
        return $results;
    }
    
    /**
     * Send Appointment Confirmation to Resident (Email + SMS)
     * 
     * @param array $data Appointment data
     * @return array Results
     */
    public function sendAppointmentConfirmation($data) {
        $results = [
            'email' => false,
            'sms' => false,
            'errors' => []
        ];
        
        $emailDetails = [
            'service_name' => $data['service_name'] ?? 'Service',
            'date' => $data['date'] ?? date('M j, Y'),
            'time' => $data['time'] ?? 'TBD',
            'transaction_id' => $data['transaction_id'] ?? 'N/A',
            'department_name' => $data['department_name'] ?? 'LGU Office',
            'requirements' => $data['requirements'] ?? ['Valid ID']
        ];
        
        // Send Email
        if ($this->emailEnabled && !empty($data['email'])) {
            try {
                $results['email'] = sendAppointmentConfirmation(
                    $data['email'],
                    $data['name'],
                    $emailDetails
                );
                
                if (!$results['email']) {
                    $results['errors'][] = "Email: Failed to send confirmation";
                }
            } catch (Exception $e) {
                $results['errors'][] = "Email Error: " . $e->getMessage();
                error_log("Appointment email failed: " . $e->getMessage());
            }
        }
        
        if ($this->smsEnabled && !empty($data['phone'])) {
            try {
                $smsDetails = [
                    'service_name' => $emailDetails['service_name'],
                    'date' => $emailDetails['date'],
                    'time' => $emailDetails['time'],
                    'transaction_id' => $emailDetails['transaction_id'],
                    'department_name' => $emailDetails['department_name']
                ];
                
                $results['sms'] = $this->smsService->sendAppointmentConfirmation(
                    $data['phone'],
                    $data['name'],
                    $smsDetails
                );
                
                if (!$results['sms']) {
                    $results['errors'][] = "SMS: Failed to send confirmation";
                }
            } catch (Exception $e) {
                $results['errors'][] = "SMS Error: " . $e->getMessage();
                error_log("Appointment SMS failed: " . $e->getMessage());
            }
        }
        
        return $results;
    }
    
    /**
     * Send Appointment Notification to Personnel (Email + SMS)
     * 
     * @param array $data Personnel and appointment data
     * @return array Results
     */
    public function sendPersonnelAppointmentNotification($data) {
        $results = [
            'email' => false,
            'sms' => false,
            'errors' => []
        ];
        
        $details = [
            'resident_name' => $data['resident_name'] ?? 'Resident',
            'service_name' => $data['service_name'] ?? 'Service',
            'date' => $data['date'] ?? date('M j, Y'),
            'time' => $data['time'] ?? 'TBD',
            'transaction_id' => $data['transaction_id'] ?? 'N/A',
            'department_name' => $data['department_name'] ?? 'LGU Office',
            'reason' => $data['reason'] ?? 'Not specified',
            'requirements' => $data['requirements'] ?? ['Valid ID']
        ];

        if ($this->emailEnabled && !empty($data['personnel_email'])) {
            try {
                $results['email'] = sendPersonnelAppointmentNotification(
                    $data['personnel_email'],
                    $data['personnel_name'],
                    $details
                );
                
                if (!$results['email']) {
                    $results['errors'][] = "Email: Failed to send to personnel";
                }
            } catch (Exception $e) {
                $results['errors'][] = "Email Error: " . $e->getMessage();
                error_log("Personnel email failed: " . $e->getMessage());
            }
        }

        if ($this->smsEnabled && !empty($data['personnel_phone'])) {
            try {
                $smsMessage = "New appointment assigned: {$details['resident_name']} - "
                            . "{$details['service_name']} on {$details['date']} at {$details['time']}. "
                            . "Ref: {$details['transaction_id']}. Check email for details.";
                
                $results['sms'] = $this->sendCustomSMS(
                    $data['personnel_phone'],
                    $smsMessage
                );
                
                if (!$results['sms']) {
                    $results['errors'][] = "SMS: Failed to send to personnel";
                }
            } catch (Exception $e) {
                $results['errors'][] = "SMS Error: " . $e->getMessage();
                error_log("Personnel SMS failed: " . $e->getMessage());
            }
        }
        
        return $results;
    }
    
    /**
     * Send Reschedule Notification (Email + SMS) to Resident
     * 
     * @param array $data Reschedule data
     * @return array Results
     */
    public function sendRescheduleNotification($data) {
        $results = [
            'email' => false,
            'sms' => false,
            'errors' => []
        ];
        
        $details = [
            'service_name' => $data['service_name'] ?? 'Service',
            'new_date' => $data['new_date'] ?? date('M j, Y'),
            'new_time' => $data['new_time'] ?? 'TBD',
            'transaction_id' => $data['transaction_id'] ?? 'N/A'
        ];
        
        if ($this->emailEnabled && !empty($data['email'])) {
            try {
                $results['email'] = sendRescheduleNotification(
                    $data['email'],
                    $data['name'],
                    $details
                );
                
                if (!$results['email']) {
                    $results['errors'][] = "Email: Failed to send reschedule notification";
                }
            } catch (Exception $e) {
                $results['errors'][] = "Email Error: " . $e->getMessage();
            }
        }
        
        if ($this->smsEnabled && !empty($data['phone'])) {
            try {
                $results['sms'] = $this->smsService->sendRescheduleNotification(
                    $data['phone'],
                    $data['name'],
                    $details
                );
                
                if (!$results['sms']) {
                    $results['errors'][] = "SMS: Failed to send reschedule notification";
                }
            } catch (Exception $e) {
                $results['errors'][] = "SMS Error: " . $e->getMessage();
            }
        }
        
        return $results;
    }
    
    /**
     * Send Reschedule Notification to Personnel (Email)
     * 
     * @param array $data Personnel and reschedule data
     * @return array Results
     */
    public function sendPersonnelRescheduleNotification($data) {
        $results = [
            'email' => false,
            'sms' => false,
            'errors' => []
        ];
        
        $details = [
            'resident_name' => $data['resident_name'] ?? 'Resident',
            'service_name' => $data['service_name'] ?? 'Service',
            'new_date' => $data['new_date'] ?? date('M j, Y'),
            'new_time' => $data['new_time'] ?? 'TBD',
            'transaction_id' => $data['transaction_id'] ?? 'N/A'
        ];
        
        if ($this->emailEnabled && !empty($data['personnel_email'])) {
            try {
                $results['email'] = sendPersonnelRescheduleNotification(
                    $data['personnel_email'],
                    $data['personnel_name'],
                    $details
                );
                
                if (!$results['email']) {
                    $results['errors'][] = "Email: Failed to send to personnel";
                }
            } catch (Exception $e) {
                $results['errors'][] = "Email Error: " . $e->getMessage();
            }
        }
        
        return $results;
    }
    
    /**
     * Send Appointment Reminder (1 day before)
     * 
     * @param array $data Appointment data
     * @return array Results
     */
    public function sendAppointmentReminder($data) {
        $results = [
            'email' => false,
            'sms' => false,
            'errors' => []
        ];
        
        if ($this->smsEnabled && !empty($data['phone'])) {
            try {
                $appointmentDetails = [
                    'service_name' => $data['service_name'],
                    'date' => $data['date'],
                    'time' => $data['time']
                ];
                
                $results['sms'] = $this->smsService->sendAppointmentReminder(
                    $data['phone'],
                    $data['name'],
                    $appointmentDetails
                );
                
                if (!$results['sms']) {
                    $results['errors'][] = "SMS: Failed to send reminder";
                }
            } catch (Exception $e) {
                $results['errors'][] = "SMS Error: " . $e->getMessage();
            }
        }
        return $results;
    }
    
    /**
     * Send Cancellation Notification (Email + SMS)
     * 
     * @param array $data Cancellation data
     * @return array Results
     */
    public function sendCancellationNotification($data) {
        $results = [
            'email' => false,
            'sms' => false,
            'errors' => []
        ];
        
        if ($this->smsEnabled && !empty($data['phone'])) {
            try {
                $results['sms'] = $this->smsService->sendCancellationNotification(
                    $data['phone'],
                    $data['name'],
                    $data['transaction_id'],
                    $data['reason'] ?? ''
                );
                
                if (!$results['sms']) {
                    $results['errors'][] = "SMS: Failed to send cancellation";
                }
            } catch (Exception $e) {
                $results['errors'][] = "SMS Error: " . $e->getMessage();
            }
        }
        return $results;
    }
    
    /**
     * Send Verification OTP (SMS only)
     * 
     * @param string $phone Phone number
     * @param string $otp OTP code
     * @return array Results
     */
    public function sendVerificationOTP($phone, $otp = null) {
        $results = [
            'sms' => false,
            'errors' => []
        ];
        
        if ($this->smsEnabled && !empty($phone)) {
            try {
                // Generate OTP if not provided
                if (empty($otp)) {
                    $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
                }
                
                $results['sms'] = $this->smsService->sendVerificationOTP($phone, $otp);
                $results['otp'] = $otp;
                
                if (!$results['sms']) {
                    $results['errors'][] = "SMS: Failed to send OTP";
                }
            } catch (Exception $e) {
                $results['errors'][] = "SMS Error: " . $e->getMessage();
            }
        }
        
        return $results;
    }
    
    /**
     * Send Status Update Notification
     * 
     * @param array $data Status update data
     * @return array Results
     */
    public function sendStatusUpdate($data) {
        $results = [
            'sms' => false,
            'errors' => []
        ];
        
        if ($this->smsEnabled && !empty($data['phone'])) {
            try {
                $results['sms'] = $this->smsService->sendStatusUpdate(
                    $data['phone'],
                    $data['name'],
                    $data['transaction_id'],
                    $data['status']
                );
                
                if (!$results['sms']) {
                    $results['errors'][] = "SMS: Failed to send status update";
                }
            } catch (Exception $e) {
                $results['errors'][] = "SMS Error: " . $e->getMessage();
            }
        }
        
        return $results;
    }
    
    /**
     * Validate Philippine phone number
     * 
     * @param string $phone Phone number
     * @return bool Valid or not
     */
    public function isValidPhoneNumber($phone) {
        return $this->smsService->isValidPhilippineNumber($phone);
    }
    
    /**
     * Enable/Disable Email notifications
     */
    public function setEmailEnabled($enabled) {
        $this->emailEnabled = $enabled;
    }
    
    /**
     * Enable/Disable SMS notifications
     */
    public function setSMSEnabled($enabled) {
        $this->smsEnabled = $enabled;
    }
    
    /**
     * Get notification status
     */
    public function getStatus() {
        return [
            'email_enabled' => $this->emailEnabled,
            'sms_enabled' => $this->smsEnabled
        ];
    }
}
?>