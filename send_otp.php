<?php
session_start();
header('Content-Type: application/json');

require_once 'sms_functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $name = $_POST['name'] ?? '';
    
    if (empty($email) || empty($phone) || empty($name)) {
        echo json_encode([
            'success' => false,
            'message' => 'Email, phone, and name are required.'
        ]);
        exit;
    }
    

    $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    
    $_SESSION['registration_otp'] = $otp;
    $_SESSION['registration_otp_expiry'] = time() + (5 * 60);
    $_SESSION['registration_email'] = $email;
    $_SESSION['registration_phone'] = $phone;
    $_SESSION['registration_name'] = $name;
    
    $smsService = new SMSService();
    
    $emailSent = false;
    $smsSent = false;
    $errors = [];
    
    try {
        $emailSent = sendOTPEmail($email, $name, $otp);
    } catch (Exception $e) {
        $errors[] = "Email: " . $e->getMessage();
        error_log("OTP Email Error: " . $e->getMessage());
    }
    
    try {
        $smsSent = $smsService->sendVerificationOTP($phone, $otp);
        if (!$smsSent) {
            $errors[] = "SMS: Failed to send";
        }
    } catch (Exception $e) {
        $errors[] = "SMS: " . $e->getMessage();
        error_log("OTP SMS Error: " . $e->getMessage());
    }
    
    if ($emailSent || $smsSent) {
        $message = 'OTP sent successfully!';
        if ($emailSent && $smsSent) {
            $message .= ' Check your email and phone.';
        } elseif ($emailSent) {
            $message .= ' Check your email. (SMS failed)';
        } elseif ($smsSent) {
            $message .= ' Check your phone. (Email failed)';
        }
        
        echo json_encode([
            'success' => true,
            'message' => $message,
            'email_sent' => $emailSent,
            'sms_sent' => $smsSent,
            'errors' => $errors
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to send OTP. Please try again.',
            'errors' => $errors
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method.'
    ]);
}

function sendOTPEmail($recipientEmail, $recipientName, $otp) {
    require_once 'PHPMailer-master/src/PHPMailer.php';
    require_once 'PHPMailer-master/src/SMTP.php';
    require_once 'PHPMailer-master/src/Exception.php';
    
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'jvcrujido@gmail.com';
        $mail->Password   = 'jqwcysmffzbxoeaj';
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        $mail->setFrom('jvcrujido@gmail.com', 'LGU Quick Appoint');
        $mail->addAddress($recipientEmail, $recipientName);
        $mail->isHTML(true);
        $mail->Subject = 'Registration OTP - LGU Quick Appoint';
        
        $mail->Body = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        </head>
        <body style='margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f4f4;'>
            <table width='100%' cellpadding='0' cellspacing='0' style='background-color: #f4f4f4; padding: 20px;'>
                <tr>
                    <td align='center'>
                        <table width='600' cellpadding='0' cellspacing='0' style='background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.1);'>
                            <tr>
                                <td style='background: linear-gradient(to right, #0d94f4bc, #27548ac3); padding: 30px; text-align: center;'>
                                    <h1 style='color: #ffffff; margin: 0; font-size: 28px;'>🔐 Registration OTP</h1>
                                    <p style='color: #ffffff; margin: 10px 0 0 0; font-size: 14px;'>Verify your email address</p>
                                </td>
                            </tr>
                            
                            <tr>
                                <td style='padding: 40px 30px; text-align: center;'>
                                    <p style='color: #333; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;'>
                                        Hi <strong>{$recipientName}</strong>,
                                    </p>
                                    <p style='color: #555; font-size: 15px; line-height: 1.6; margin: 0 0 30px 0;'>
                                        Your One-Time Password (OTP) for registration is:
                                    </p>
                                    
                                    <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 25px; border-radius: 12px; display: inline-block; margin-bottom: 20px;'>
                                        <p style='color: #ffffff; font-size: 42px; font-weight: bold; letter-spacing: 10px; margin: 0; font-family: monospace;'>{$otp}</p>
                                    </div>
                                    
                                    <div style='background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; text-align: left; border-radius: 4px;'>
                                        <p style='color: #856404; font-size: 14px; margin: 0; line-height: 1.6;'>
                                            <strong>⚠️ Important:</strong><br>
                                            • This OTP will expire in <strong>5 minutes</strong><br>
                                            • Do NOT share this code with anyone<br>
                                            • LGU staff will never ask for your OTP
                                        </p>
                                    </div>
                                    
                                    <hr style='border: none; border-top: 1px solid #e0e0e0; margin: 30px 0;'>
                                    
                                    <p style='color: #999; font-size: 13px; line-height: 1.6; margin: 0;'>
                                        If you didn't request this OTP, please ignore this email or contact our support team.
                                    </p>
                                </td>
                            </tr>
                            
                            <tr>
                                <td style='background-color: #f8f9fa; padding: 20px 30px; text-align: center;'>
                                    <p style='color: #888; font-size: 12px; margin: 0;'>
                                        © 2025 LGU Quick Appoint. All rights reserved.
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        ";
        
        $mail->AltBody = "Your LGU Quick Appoint registration OTP is: {$otp}\n\n"
                       . "This code will expire in 5 minutes.\n"
                       . "Do NOT share this code with anyone.\n\n"
                       . "If you didn't request this, please ignore this email.";
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("OTP Email Error: " . $mail->ErrorInfo);
        return false;
    }
}
?>