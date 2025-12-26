<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Load PHPMailer classes
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';
require 'PHPMailer-master/src/Exception.php';

function sendResetEmail($recipientEmail, $recipientName, $resetLink) {
    $mail = new PHPMailer(true);

    try {
        // Enable verbose debug output (remove this in production)
        $mail->SMTPDebug = 2; // Set to 0 in production
        $mail->Debugoutput = function($str, $level) {
            error_log("PHPMailer: $str");
        };

        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'jvcrujido@gmail.com';
        $mail->Password   = 'jqwcysmffzbxoeaj'; // FIXED: Removed spaces from app password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        
        // Additional options that often help
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        // Sender and recipient
        $mail->setFrom('testtesttestter5@gmail.com', 'LGU Quick Appoint');
        $mail->addAddress($recipientEmail, $recipientName);
        $mail->addReplyTo('testtesttestter5@gmail.com', 'LGU Support');

        // Email content
        $mail->isHTML(true);
        $mail->Subject = 'Password Reset Request - LGU Quick Appoint';
        $mail->Body    = "
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
                        <table width='600' cellpadding='0' cellspacing='0' style='background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);'>
                            <!-- Header -->
                            <tr>
                                <td style='background: linear-gradient(to right, #0d94f4bc, #27548ac3); padding: 30px; text-align: center;'>
                                    <h1 style='color: #ffffff; margin: 0; font-size: 28px;'>LGU Quick Appoint</h1>
                                    <p style='color: #ffffff; margin: 10px 0 0 0; font-size: 14px;'>Password Reset Request</p>
                                </td>
                            </tr>
                            
                            <!-- Body -->
                            <tr>
                                <td style='padding: 40px 30px;'>
                                    <p style='color: #333; font-size: 16px; line-height: 1.6; margin: 0 0 15px 0;'>
                                        Hi <strong>{$recipientName}</strong>,
                                    </p>
                                    <p style='color: #555; font-size: 15px; line-height: 1.6; margin: 0 0 25px 0;'>
                                        We received a request to reset your password. Click the button below to create a new password:
                                    </p>
                                    
                                    <table width='100%' cellpadding='0' cellspacing='0'>
                                        <tr>
                                            <td align='center' style='padding: 20px 0;'>
                                                <a href='{$resetLink}' style='display: inline-block; padding: 14px 40px; background: linear-gradient(to right, #0d94f4bc, #27548ac3); color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 16px;'>Reset Password</a>
                                            </td>
                                        </tr>
                                    </table>
                                    
                                    <p style='color: #555; font-size: 14px; line-height: 1.6; margin: 20px 0 0 0;'>
                                        Or copy and paste this link into your browser:
                                    </p>
                                    <p style='color: #0d94f4bc; font-size: 13px; word-break: break-all; margin: 10px 0 20px 0;'>
                                        {$resetLink}
                                    </p>
                                    
                                    <hr style='border: none; border-top: 1px solid #e0e0e0; margin: 30px 0;'>
                                    
                                    <p style='color: #999; font-size: 13px; line-height: 1.6; margin: 0;'>
                                        <strong>If you didn't request this password reset</strong>, please ignore this email or contact our support team if you have concerns.
                                    </p>
                                    <p style='color: #999; font-size: 13px; line-height: 1.6; margin: 10px 0 0 0;'>
                                        This link will expire in 1 hour for security reasons.
                                    </p>
                                </td>
                            </tr>
                            
                            <!-- Footer -->
                            <tr>
                                <td style='background-color: #f8f9fa; padding: 20px 30px; text-align: center; border-top: 1px solid #e0e0e0;'>
                                    <p style='color: #888; font-size: 12px; margin: 0 0 5px 0;'>
                                        © 2025 LGU Quick Appoint. All rights reserved.
                                    </p>
                                    <p style='color: #999; font-size: 11px; margin: 0;'>
                                        This is an automated message, please do not reply.
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

        // Plain text alternative
        $mail->AltBody = "Hi {$recipientName},\n\n"
                       . "We received a request to reset your password.\n\n"
                       . "Click this link to reset your password:\n{$resetLink}\n\n"
                       . "If you didn't request this, please ignore this email.\n\n"
                       . "This link will expire in 1 hour.\n\n"
                       . "LGU Quick Appoint Team";

        $mail->send();
        return true;
    } catch (Exception $e) {
        // Return the actual error message for debugging
        error_log("Email Error: " . $mail->ErrorInfo);
        return "Email Error: " . $mail->ErrorInfo;
    }
}

function sendAppointmentConfirmation($recipientEmail, $recipientName, $appointmentDetails) {
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'jvcrujido@gmail.com';
        $mail->Password   = 'jqwcysmffzbxoeaj'; // Replace with your actual app password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Sender and recipient
        $mail->setFrom('jvcrujido@gmail.com', 'LGU Quick Appoint');
        $mail->addAddress($recipientEmail, $recipientName);
        $mail->addReplyTo('jvcrujido@gmail.com', 'LGU Support');

        // Format requirements list
        $requirementsList = '';
        if (!empty($appointmentDetails['requirements'])) {
            foreach ($appointmentDetails['requirements'] as $req) {
                $requirementsList .= "<li style='margin: 8px 0; color: #2c3e50;'>{$req}</li>";
            }
        } else {
            $requirementsList = "<li style='margin: 8px 0; color: #2c3e50;'>Valid ID</li>";
        }

        // Email content
        $mail->isHTML(true);
        $mail->Subject = 'Appointment Confirmed: ' . $appointmentDetails['service_name'] . ' on ' . $appointmentDetails['date'];
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
                        <table width='600' cellpadding='0' cellspacing='0' style='background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);'>
                            <tr>
                                <td style='background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%); padding: 30px; text-align: center;'>
                                    <h1 style='color: #ffffff; margin: 0; font-size: 28px;'>Appointment Confirmed!</h1>
                                    <p style='color: #ffffff; margin: 10px 0 0 0; font-size: 14px;'>Your booking has been successfully processed</p>
                                </td>
                            </tr>
                            
                            <tr>
                                <td style='padding: 40px 30px;'>
                                    <p style='color: #333; font-size: 16px; line-height: 1.6; margin: 0 0 15px 0;'>
                                        Dear <strong>{$recipientName}</strong>,
                                    </p>
                                    <p style='color: #555; font-size: 15px; line-height: 1.6; margin: 0 0 25px 0;'>
                                        Your appointment for <strong>{$appointmentDetails['service_name']}</strong> has been successfully booked.
                                    </p>
                                    
                                    <table width='100%' cellpadding='0' cellspacing='0' style='background: linear-gradient(135deg, #f8f9fa, #e9ecef); border-radius: 8px; margin: 20px 0; border: 2px solid #3498db;'>
                                        <tr>
                                            <td style='padding: 20px;'>
                                                <h3 style='color: #2c3e50; margin: 0 0 15px 0; font-size: 18px;'>Appointment Details</h3>
                                                
                                                <table width='100%' cellpadding='8' cellspacing='0'>
                                                    <tr>
                                                        <td style='color: #6c757d; font-size: 14px; padding: 8px 0;'><strong>Date:</strong></td>
                                                        <td style='color: #2c3e50; font-size: 14px; padding: 8px 0;'>{$appointmentDetails['date']}</td>
                                                    </tr>
                                                    <tr>
                                                        <td style='color: #6c757d; font-size: 14px; padding: 8px 0;'><strong>Time:</strong></td>
                                                        <td style='color: #2c3e50; font-size: 14px; padding: 8px 0;'>{$appointmentDetails['time']}</td>
                                                    </tr>
                                                    <tr>
                                                        <td style='color: #6c757d; font-size: 14px; padding: 8px 0;'><strong>Location:</strong></td>
                                                        <td style='color: #2c3e50; font-size: 14px; padding: 8px 0;'>{$appointmentDetails['department_name']}</td>
                                                    </tr>
                                                    <tr>
                                                        <td colspan='2' style='padding: 15px 0 8px 0;'>
                                                            <div style='background: white; padding: 15px; border-radius: 6px; text-align: center; border: 2px dashed #3498db;'>
                                                                <p style='color: #6c757d; font-size: 12px; margin: 0 0 5px 0;'>Reference Number</p>
                                                                <p style='color: #2c3e50; font-size: 24px; font-weight: bold; margin: 0; letter-spacing: 2px;'>{$appointmentDetails['transaction_id']}</p>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>
                                    </table>
                                    
                                    <div style='background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 4px;'>
                                        <p style='color: #856404; font-size: 14px; margin: 0 0 10px 0;'><strong>⚠️ Important Reminders:</strong></p>
                                        <ul style='color: #856404; font-size: 14px; margin: 0; padding-left: 20px;'>
                                            <li style='margin: 5px 0;'>Please arrive <strong>15 minutes early</strong></li>
                                            <li style='margin: 5px 0;'>Bring your reference number</li>
                                            <li style='margin: 5px 0;'>Late arrivals may result in appointment cancellation</li>
                                        </ul>
                                    </div>
                                    
                                    <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                                        <h3 style='color: #2c3e50; margin: 0 0 15px 0; font-size: 16px;'>📄 Required Documents:</h3>
                                        <ul style='margin: 0; padding-left: 20px;'>
                                            {$requirementsList}
                                        </ul>
                                    </div>
                                    
                                    <hr style='border: none; border-top: 1px solid #e0e0e0; margin: 30px 0;'>
                                    
                                    <p style='color: #999; font-size: 13px; line-height: 1.6; margin: 0;'>
                                        If you need to cancel or reschedule your appointment, please contact us as soon as possible.
                                    </p>
                                </td>
                            </tr>
                            
                            <tr>
                                <td style='background-color: #f8f9fa; padding: 20px 30px; text-align: center; border-top: 1px solid #e0e0e0;'>
                                    <p style='color: #888; font-size: 12px; margin: 0 0 5px 0;'>
                                        © 2025 LGU Quick Appoint. All rights reserved.
                                    </p>
                                    <p style='color: #999; font-size: 11px; margin: 0;'>
                                        This is an automated message, please do not reply.
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

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Appointment Email Error: " . $mail->ErrorInfo);
        return false;
    }
}


function sendRescheduleNotification($recipientEmail, $recipientName, $details) {
    $mail = new PHPMailer(true);
    try {
        // SMTP Settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'jvcrujido@gmail.com'; 
        $mail->Password   = 'jqwcysmffzbxoeaj'; 
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('jvcrujido@gmail.com', 'LGU Quick Appoint');
        $mail->addAddress($recipientEmail, $recipientName);
        $mail->isHTML(true);
        $mail->Subject = 'Appointment Rescheduled: ' . $details['service_name'];
        
        $mail->Body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #f9f9f9; padding: 20px;'>
            <div style='background: linear-gradient(to right, #0d94f4bc, #27548ac3); padding: 20px; text-align: center; border-radius: 8px 8px 0 0;'>
                <h2 style='color: white; margin: 0;'>Appointment Update</h2>
            </div>
            <div style='background: white; padding: 20px; border-radius: 0 0 8px 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);'>
                <p>Hi <strong>{$recipientName}</strong>,</p>
                <p>Your appointment has been successfully rescheduled.</p>
                
                <div style='background: #fff3e0; border-left: 4px solid #27548ac3; padding: 15px; margin: 20px 0;'>
                    <p style='margin: 5px 0;'><strong>Service:</strong> {$details['service_name']}</p>
                    <p style='margin: 5px 0;'><strong>New Date:</strong> {$details['new_date']}</p>
                    <p style='margin: 5px 0;'><strong>New Time:</strong> {$details['new_time']}</p>
                    <p style='margin: 5px 0;'><strong>Reference:</strong> {$details['transaction_id']}</p>
                </div>
                
                <p style='font-size: 12px; color: #7f8c8d;'>If you did not request this change, please contact us immediately.</p>
            </div>
        </div>";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Resident Email Error: " . $mail->ErrorInfo);
        return false;
    }
}

function sendPersonnelRescheduleNotification($recipientEmail, $recipientName, $details) {
    $mail = new PHPMailer(true);

    try {
        // 1. Server Settings (Same as your working resident email)
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'jvcrujido@gmail.com'; 
        $mail->Password   = 'jqwcysmffzbxoeaj'; 
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // 2. Sender & Recipient
        $mail->setFrom('jvcrujido@gmail.com', 'LGU System Admin'); 
        $mail->addAddress($recipientEmail, $recipientName);

        // 3. Email Content
        $mail->isHTML(true);
        $mail->Subject = 'Activity Log: Appointment Rescheduled';
        
        $mail->Body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background-color: #f4f4f4; padding: 20px;'>
            <div style='background: #2c3e50; padding: 15px 20px; color: white; border-radius: 5px 5px 0 0;'>
                <h3 style='margin: 0;'>System Activity Log</h3>
            </div>
            <div style='background: white; padding: 20px; border: 1px solid #ddd; border-top: none; border-radius: 0 0 5px 5px;'>
                <p>Hi <strong>{$recipientName}</strong>,</p>
                <p>You have successfully rescheduled an appointment. Here are the details of your action:</p>
                
                <div style='background: #e8f4fd; border-left: 4px solid #3498db; padding: 15px; margin: 20px 0;'>
                    <table style='width: 100%; border-collapse: collapse;'>
                        <tr>
                            <td style='padding: 5px 0; color: #666; width: 120px;'>Resident:</td>
                            <td style='font-weight: bold;'>{$details['resident_name']}</td>
                        </tr>
                        <tr>
                            <td style='padding: 5px 0; color: #666;'>Service:</td>
                            <td style='font-weight: bold;'>{$details['service_name']}</td>
                        </tr>
                        <tr>
                            <td style='padding: 5px 0; color: #666;'>New Schedule:</td>
                            <td style='font-weight: bold; color: #27ae60;'>{$details['new_date']} @ {$details['new_time']}</td>
                        </tr>
                        <tr>
                            <td style='padding: 5px 0; color: #666;'>Transaction ID:</td>
                            <td style='font-family: monospace;'>{$details['transaction_id']}</td>
                        </tr>
                    </table>
                </div>
                
                <p style='font-size: 12px; color: #999; margin-top: 30px;'>
                    Action performed on: " . date('F j, Y g:i A') . "
                </p>
            </div>
        </div>";

        $mail->send();
        return true;
    } catch (Exception $e) {
        // Log error but don't stop the script
        error_log("Personnel Email Error: " . $mail->ErrorInfo);
        return false;
    }
}