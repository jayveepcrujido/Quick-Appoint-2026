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
        $mail->setFrom('jvcrujido@gmail.com', 'LGU Quick Appoint');
        $mail->addAddress($recipientEmail, $recipientName);
        $mail->addReplyTo('jvcrujido@gmail.com', 'LGU Support');

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

function sendPersonnelWelcomeEmail($recipientEmail, $recipientName, $details) {
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'jvcrujido@gmail.com';
        $mail->Password   = 'jqwcysmffzbxoeaj';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('jvcrujido@gmail.com', 'LGU Quick Appoint');
        $mail->addAddress($recipientEmail, $recipientName);
        $mail->addReplyTo('jvcrujido@gmail.com', 'LGU Support');

        // Role badge
        $roleBadge = $details['is_department_head'] 
            ? "<span style='background: #f59e0b; color: white; padding: 5px 12px; border-radius: 15px; font-size: 12px; font-weight: bold;'>👑 Department Head</span>"
            : "<span style='background: #3498db; color: white; padding: 5px 12px; border-radius: 15px; font-size: 12px; font-weight: bold;'>👤 Personnel</span>";

        $mail->isHTML(true);
        $mail->Subject = 'Welcome to LGU Quick Appoint - Account Created';
        
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
                            <!-- Header -->
                            <tr>
                                <td style='background: linear-gradient(135deg, #0D92F4, #27548A); padding: 30px; text-align: center;'>
                                    <h1 style='color: #ffffff; margin: 0; font-size: 28px;'>Welcome to LGU Quick Appoint!</h1>
                                    <p style='color: #ffffff; margin: 10px 0 0 0; font-size: 14px;'>Your account has been successfully created</p>
                                </td>
                            </tr>
                            
                            <!-- Body -->
                            <tr>
                                <td style='padding: 40px 30px;'>
                                    <p style='color: #333; font-size: 16px; line-height: 1.6; margin: 0 0 15px 0;'>
                                        Hi <strong>{$recipientName}</strong>,
                                    </p>
                                    <p style='color: #555; font-size: 15px; line-height: 1.6; margin: 0 0 25px 0;'>
                                        Your account has been created as an LGU Personnel. You can now access the system using the credentials below.
                                    </p>
                                    
                                    <!-- Account Info Box -->
                                    <table width='100%' cellpadding='0' cellspacing='0' style='background: linear-gradient(135deg, #f8f9fa, #e9ecef); border-radius: 8px; margin: 20px 0; border: 2px solid #0D92F4;'>
                                        <tr>
                                            <td style='padding: 25px;'>
                                                <h3 style='color: #2c3e50; margin: 0 0 15px 0; font-size: 18px; text-align: center;'>
                                                    🔐 Your Login Credentials
                                                </h3>
                                                
                                                <table width='100%' cellpadding='10' cellspacing='0'>
                                                    <tr>
                                                        <td style='color: #6c757d; font-size: 14px; padding: 8px 0;'><strong>Role:</strong></td>
                                                        <td style='padding: 8px 0;'>{$roleBadge}</td>
                                                    </tr>
                                                    <tr>
                                                        <td style='color: #6c757d; font-size: 14px; padding: 8px 0;'><strong>Department:</strong></td>
                                                        <td style='color: #2c3e50; font-size: 14px; padding: 8px 0;'>{$details['department_name']}</td>
                                                    </tr>
                                                    <tr>
                                                        <td style='color: #6c757d; font-size: 14px; padding: 8px 0;'><strong>Email:</strong></td>
                                                        <td style='color: #2c3e50; font-size: 14px; padding: 8px 0; font-family: monospace;'>{$details['email']}</td>
                                                    </tr>
                                                    <tr>
                                                        <td style='color: #6c757d; font-size: 14px; padding: 8px 0;'><strong>Password:</strong></td>
                                                        <td style='padding: 8px 0;'>
                                                            <div style='background: white; padding: 10px; border-radius: 6px; border: 2px dashed #0D92F4;'>
                                                                <code style='color: #e74c3c; font-size: 16px; font-weight: bold;'>{$details['password']}</code>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>
                                    </table>
                                    
                                    <!-- Login Button -->
                                    <table width='100%' cellpadding='0' cellspacing='0'>
                                        <tr>
                                            <td align='center' style='padding: 20px 0;'>
                                                <a href='https://unisan-lgu-quick-appoint.page.gd/login.php' style='display: inline-block; padding: 14px 40px; background: linear-gradient(135deg, #0D92F4, #27548A); color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 16px;'>
                                                    Login to Your Account
                                                </a>
                                            </td>
                                        </tr>
                                    </table>
                                    
                                    <!-- Security Warning -->
                                    <div style='background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 4px;'>
                                        <p style='color: #856404; font-size: 14px; margin: 0 0 10px 0;'><strong>⚠️ Security Reminder:</strong></p>
                                        <ul style='color: #856404; font-size: 13px; margin: 0; padding-left: 20px;'>
                                            <li style='margin: 5px 0;'>Please change your password after your first login</li>
                                            <li style='margin: 5px 0;'>Never share your credentials with anyone</li>
                                            <li style='margin: 5px 0;'>Use a strong, unique password</li>
                                        </ul>
                                    </div>
                                    
                                    <hr style='border: none; border-top: 1px solid #e0e0e0; margin: 30px 0;'>
                                    
                                    <p style='color: #999; font-size: 13px; line-height: 1.6; margin: 0;'>
                                        If you have any questions or didn't expect this email, please contact the administrator immediately.
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
        $mail->AltBody = "Welcome to LGU Quick Appoint!\n\n"
                       . "Hi {$recipientName},\n\n"
                       . "Your account has been created.\n\n"
                       . "Login Credentials:\n"
                       . "Email: {$details['email']}\n"
                       . "Password: {$details['password']}\n"
                       . "Department: {$details['department_name']}\n\n"
                       . "Login here: {$details['login_link']}\n\n"
                       . "IMPORTANT: Please change your password after your first login.\n\n"
                       . "LGU Quick Appoint Team";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Personnel Welcome Email Error: " . $mail->ErrorInfo);
        return false;
    }
}

function sendCoPersonnelWelcomeEmail($recipientEmail, $recipientName, $details) {
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'jvcrujido@gmail.com';
        $mail->Password   = 'jqwcysmffzbxoeaj';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('jvcrujido@gmail.com', 'LGU Quick Appoint');
        $mail->addAddress($recipientEmail, $recipientName);
        $mail->addReplyTo('jvcrujido@gmail.com', 'LGU Support');

        $mail->isHTML(true);
        $mail->Subject = 'Welcome to LGU Quick Appoint - Co-Personnel Account Created';
        
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
                            <!-- Header -->
                            <tr>
                                <td style='background: linear-gradient(135deg, #10b981, #059669); padding: 30px; text-align: center;'>
                                    <h1 style='color: #ffffff; margin: 0; font-size: 28px;'>Welcome to the Team!</h1>
                                    <p style='color: #ffffff; margin: 10px 0 0 0; font-size: 14px;'>Your co-personnel account has been created</p>
                                </td>
                            </tr>
                            
                            <!-- Body -->
                            <tr>
                                <td style='padding: 40px 30px;'>
                                    <p style='color: #333; font-size: 16px; line-height: 1.6; margin: 0 0 15px 0;'>
                                        Hi <strong>{$recipientName}</strong>,
                                    </p>
                                    <p style='color: #555; font-size: 15px; line-height: 1.6; margin: 0 0 10px 0;'>
                                        <strong>{$details['created_by']}</strong> has added you as a co-personnel member in the <strong>{$details['department_name']}</strong> department.
                                    </p>
                                    <p style='color: #555; font-size: 15px; line-height: 1.6; margin: 0 0 25px 0;'>
                                        You can now access the LGU Quick Appoint system using the credentials below.
                                    </p>
                                    
                                    <!-- Account Info Box -->
                                    <table width='100%' cellpadding='0' cellspacing='0' style='background: linear-gradient(135deg, #f8f9fa, #e9ecef); border-radius: 8px; margin: 20px 0; border: 2px solid #10b981;'>
                                        <tr>
                                            <td style='padding: 25px;'>
                                                <h3 style='color: #2c3e50; margin: 0 0 15px 0; font-size: 18px; text-align: center;'>
                                                    🔐 Your Login Credentials
                                                </h3>
                                                
                                                <table width='100%' cellpadding='10' cellspacing='0'>
                                                    <tr>
                                                        <td style='color: #6c757d; font-size: 14px; padding: 8px 0;'><strong>Role:</strong></td>
                                                        <td style='padding: 8px 0;'>
                                                            <span style='background: #d1fae5; color: #065f46; padding: 5px 12px; border-radius: 15px; font-size: 12px; font-weight: bold;'>
                                                                👥 Co-Personnel
                                                            </span>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style='color: #6c757d; font-size: 14px; padding: 8px 0;'><strong>Department:</strong></td>
                                                        <td style='color: #2c3e50; font-size: 14px; padding: 8px 0;'>{$details['department_name']}</td>
                                                    </tr>
                                                    <tr>
                                                        <td style='color: #6c757d; font-size: 14px; padding: 8px 0;'><strong>Created By:</strong></td>
                                                        <td style='color: #2c3e50; font-size: 14px; padding: 8px 0;'>{$details['created_by']}</td>
                                                    </tr>
                                                    <tr>
                                                        <td style='color: #6c757d; font-size: 14px; padding: 8px 0;'><strong>Email:</strong></td>
                                                        <td style='color: #2c3e50; font-size: 14px; padding: 8px 0; font-family: monospace;'>{$details['email']}</td>
                                                    </tr>
                                                    <tr>
                                                        <td style='color: #6c757d; font-size: 14px; padding: 8px 0;'><strong>Password:</strong></td>
                                                        <td style='padding: 8px 0;'>
                                                            <div style='background: white; padding: 10px; border-radius: 6px; border: 2px dashed #10b981;'>
                                                                <code style='color: #e74c3c; font-size: 16px; font-weight: bold;'>{$details['password']}</code>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>
                                    </table>
                                    
                                    <!-- Login Button -->
                                    <table width='100%' cellpadding='0' cellspacing='0'>
                                        <tr>
                                            <td align='center' style='padding: 20px 0;'>
                                                <a href='http://localhost/capstone-2/capstone/login.php' style='display: inline-block; padding: 14px 40px; background: linear-gradient(135deg, #10b981, #059669); color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 16px;'>
                                                    Login to Your Account
                                                </a>
                                            </td>
                                        </tr>
                                    </table>
                                    
                                    <!-- What You Can Do -->
                                    <div style='background: #f0f9ff; border-left: 4px solid #0ea5e9; padding: 15px; margin: 20px 0; border-radius: 4px;'>
                                        <p style='color: #0369a1; font-size: 14px; margin: 0 0 10px 0;'><strong>📋 What You Can Do:</strong></p>
                                        <ul style='color: #0369a1; font-size: 13px; margin: 0; padding-left: 20px;'>
                                            <li style='margin: 5px 0;'>Manage appointments in your department</li>
                                            <li style='margin: 5px 0;'>Update appointment statuses</li>
                                            <li style='margin: 5px 0;'>View department schedules</li>
                                            <li style='margin: 5px 0;'>Collaborate with your department head</li>
                                        </ul>
                                    </div>
                                    
                                    <!-- Security Warning -->
                                    <div style='background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 4px;'>
                                        <p style='color: #856404; font-size: 14px; margin: 0 0 10px 0;'><strong>⚠️ Security Reminder:</strong></p>
                                        <ul style='color: #856404; font-size: 13px; margin: 0; padding-left: 20px;'>
                                            <li style='margin: 5px 0;'>Please change your password after your first login</li>
                                            <li style='margin: 5px 0;'>Never share your credentials with anyone</li>
                                            <li style='margin: 5px 0;'>Use a strong, unique password</li>
                                        </ul>
                                    </div>
                                    
                                    <hr style='border: none; border-top: 1px solid #e0e0e0; margin: 30px 0;'>
                                    
                                    <p style='color: #999; font-size: 13px; line-height: 1.6; margin: 0;'>
                                        If you have any questions or didn't expect this email, please contact your department head or administrator.
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
        $mail->AltBody = "Welcome to LGU Quick Appoint!\n\n"
                       . "Hi {$recipientName},\n\n"
                       . "{$details['created_by']} has added you as a co-personnel member.\n\n"
                       . "Login Credentials:\n"
                       . "Email: {$details['email']}\n"
                       . "Password: {$details['password']}\n"
                       . "Department: {$details['department_name']}\n\n"
                       . "Login here: {$details['login_link']}\n\n"
                       . "IMPORTANT: Please change your password after your first login.\n\n"
                       . "LGU Quick Appoint Team";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Co-Personnel Welcome Email Error: " . $mail->ErrorInfo);
        return false;
    }
}

function sendRescheduleApprovedEmail($recipientEmail, $recipientName, $details) {
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
        $mail->addReplyTo('jvcrujido@gmail.com', 'LGU Support');

        $mail->isHTML(true);
        $mail->Subject = 'Reschedule Request Approved - ' . $details['service_name'];
        
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
                            <!-- Header -->
                            <tr>
                                <td style='background: linear-gradient(135deg, #0D92F4, #27548A); padding: 30px; text-align: center;'>
                                    <h1 style='color: #ffffff; margin: 0; font-size: 28px;'>Request Approved</h1>
                                    <p style='color: #ffffff; margin: 10px 0 0 0; font-size: 14px;'>Your reschedule request has been accepted</p>
                                </td>
                            </tr>
                            
                            <!-- Body -->
                            <tr>
                                <td style='padding: 40px 30px;'>
                                    <p style='color: #333; font-size: 16px; line-height: 1.6; margin: 0 0 15px 0;'>
                                        Dear <strong>{$recipientName}</strong>,
                                    </p>
                                    <p style='color: #555; font-size: 15px; line-height: 1.6; margin: 0 0 25px 0;'>
                                        Your request to reschedule your appointment has been approved by the department personnel.
                                    </p>
                                    
                                    <!-- Success Alert -->
                                    <div style='background: linear-gradient(135deg, #d6eaf8, #aed6f1); border-left: 4px solid #0D92F4; padding: 15px; margin: 20px 0; border-radius: 8px;'>
                                        <p style='color: #1b4f72; font-size: 14px; margin: 0;'>
                                            <strong>Your appointment has been successfully rescheduled</strong>
                                        </p>
                                    </div>
                                    
                                    <!-- Appointment Details Box -->
                                    <table width='100%' cellpadding='0' cellspacing='0' style='background: linear-gradient(135deg, #f8f9fa, #e9ecef); border-radius: 8px; margin: 20px 0; border: 2px solid #0D92F4;'>
                                        <tr>
                                            <td style='padding: 25px;'>
                                                <h3 style='color: #2c3e50; margin: 0 0 15px 0; font-size: 18px;'>New Appointment Details</h3>
                                                
                                                <table width='100%' cellpadding='8' cellspacing='0'>
                                                    <tr>
                                                        <td style='color: #6c757d; font-size: 14px; padding: 8px 0; width: 40%;'><strong>Service:</strong></td>
                                                        <td style='color: #2c3e50; font-size: 14px; padding: 8px 0;'>{$details['service_name']}</td>
                                                    </tr>
                                                    <tr>
                                                        <td style='color: #6c757d; font-size: 14px; padding: 8px 0;'><strong>Department:</strong></td>
                                                        <td style='color: #2c3e50; font-size: 14px; padding: 8px 0;'>{$details['department_name']}</td>
                                                    </tr>
                                                    <tr>
                                                        <td style='color: #6c757d; font-size: 14px; padding: 8px 0;'><strong>New Date:</strong></td>
                                                        <td style='color: #0D92F4; font-size: 15px; font-weight: bold; padding: 8px 0;'>{$details['new_date']}</td>
                                                    </tr>
                                                    <tr>
                                                        <td style='color: #6c757d; font-size: 14px; padding: 8px 0;'><strong>New Time:</strong></td>
                                                        <td style='color: #0D92F4; font-size: 15px; font-weight: bold; padding: 8px 0;'>{$details['new_time']}</td>
                                                    </tr>
                                                    <tr>
                                                        <td colspan='2' style='padding: 15px 0 8px 0;'>
                                                            <div style='background: white; padding: 15px; border-radius: 6px; text-align: center; border: 2px dashed #0D92F4;'>
                                                                <p style='color: #6c757d; font-size: 12px; margin: 0 0 5px 0;'>Reference Number</p>
                                                                <p style='color: #2c3e50; font-size: 24px; font-weight: bold; margin: 0; letter-spacing: 2px;'>{$details['transaction_id']}</p>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>
                                    </table>
                                    
                                    <!-- Important Reminders -->
                                    <div style='background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 8px;'>
                                        <p style='color: #856404; font-size: 14px; margin: 0 0 10px 0;'><strong>Important Reminders:</strong></p>
                                        <ul style='color: #856404; font-size: 13px; margin: 0; padding-left: 20px;'>
                                            <li style='margin: 5px 0;'>Please arrive <strong>15 minutes early</strong></li>
                                            <li style='margin: 5px 0;'>Bring your reference number and valid ID</li>
                                            <li style='margin: 5px 0;'>Bring all required documents</li>
                                            <li style='margin: 5px 0;'>Late arrivals may result in appointment cancellation</li>
                                        </ul>
                                    </div>
                                    
                                    <hr style='border: none; border-top: 1px solid #e0e0e0; margin: 30px 0;'>
                                    
                                    <p style='color: #999; font-size: 13px; line-height: 1.6; margin: 0;'>
                                        If you have any questions about your appointment, please contact the {$details['department_name']}.
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

        $mail->AltBody = "Dear {$recipientName},\n\n"
                       . "Your reschedule request has been approved.\n\n"
                       . "New Appointment Details:\n"
                       . "Service: {$details['service_name']}\n"
                       . "Date: {$details['new_date']}\n"
                       . "Time: {$details['new_time']}\n"
                       . "Reference: {$details['transaction_id']}\n\n"
                       . "Please arrive 15 minutes early with all required documents.\n\n"
                       . "LGU Quick Appoint Team";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Reschedule Approved Email Error: " . $mail->ErrorInfo);
        return false;
    }
}

function sendRescheduleRejectedEmail($recipientEmail, $recipientName, $details) {
    $mail = new PHPMailer(true);
    
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'jvcrujido@gmail.com';
        $mail->Password   = 'jqwcysmffzbxoeaj';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('jvcrujido@gmail.com', 'LGU Quick Appoint');
        $mail->addAddress($recipientEmail, $recipientName);
        $mail->addReplyTo('jvcrujido@gmail.com', 'LGU Support');

        $mail->isHTML(true);
        $mail->Subject = 'Reschedule Request Declined - ' . $details['service_name'];
        
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
                            <!-- Header -->
                            <tr>
                                <td style='background: linear-gradient(135deg, #e74c3c, #c0392b); padding: 30px; text-align: center;'>
                                    <h1 style='color: #ffffff; margin: 0; font-size: 28px;'>Request Declined</h1>
                                    <p style='color: #ffffff; margin: 10px 0 0 0; font-size: 14px;'>Your reschedule request was not approved</p>
                                </td>
                            </tr>
                            
                            <!-- Body -->
                            <tr>
                                <td style='padding: 40px 30px;'>
                                    <p style='color: #333; font-size: 16px; line-height: 1.6; margin: 0 0 15px 0;'>
                                        Dear <strong>{$recipientName}</strong>,
                                    </p>
                                    <p style='color: #555; font-size: 15px; line-height: 1.6; margin: 0 0 25px 0;'>
                                        We regret to inform you that your request to reschedule your appointment has been declined by the department personnel.
                                    </p>
                                    
                                    <!-- Original Appointment Details -->
                                    <table width='100%' cellpadding='0' cellspacing='0' style='background: linear-gradient(135deg, #f8f9fa, #e9ecef); border-radius: 8px; margin: 20px 0; border: 2px solid #e74c3c;'>
                                        <tr>
                                            <td style='padding: 25px;'>
                                                <h3 style='color: #2c3e50; margin: 0 0 15px 0; font-size: 18px;'>Original Appointment</h3>
                                                
                                                <table width='100%' cellpadding='8' cellspacing='0'>
                                                    <tr>
                                                        <td style='color: #6c757d; font-size: 14px; padding: 8px 0; width: 40%;'><strong>Service:</strong></td>
                                                        <td style='color: #2c3e50; font-size: 14px; padding: 8px 0;'>{$details['service_name']}</td>
                                                    </tr>
                                                    <tr>
                                                        <td style='color: #6c757d; font-size: 14px; padding: 8px 0;'><strong>Department:</strong></td>
                                                        <td style='color: #2c3e50; font-size: 14px; padding: 8px 0;'>{$details['department_name']}</td>
                                                    </tr>
                                                    <tr>
                                                        <td style='color: #6c757d; font-size: 14px; padding: 8px 0;'><strong>Status:</strong></td>
                                                        <td style='color: #e74c3c; font-size: 14px; font-weight: bold; padding: 8px 0;'>No Show</td>
                                                    </tr>
                                                    <tr>
                                                        <td colspan='2' style='padding: 15px 0 8px 0;'>
                                                            <div style='background: white; padding: 15px; border-radius: 6px; text-align: center; border: 2px dashed #e74c3c;'>
                                                                <p style='color: #6c757d; font-size: 12px; margin: 0 0 5px 0;'>Reference Number</p>
                                                                <p style='color: #2c3e50; font-size: 24px; font-weight: bold; margin: 0; letter-spacing: 2px;'>{$details['transaction_id']}</p>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>
                                    </table>
                                    
                                    <!-- Rejection Reason -->
                                    <div style='background: #fee; border-left: 4px solid #e74c3c; padding: 15px; margin: 20px 0; border-radius: 8px;'>
                                        <p style='color: #c0392b; font-size: 14px; margin: 0 0 10px 0;'><strong>Reason for Rejection:</strong></p>
                                        <p style='color: #922b21; font-size: 14px; margin: 0; line-height: 1.6;'>{$details['rejection_reason']}</p>
                                    </div>
                                    
                                    <!-- Next Steps -->
                                    <div style='background: #f0f9ff; border-left: 4px solid #0ea5e9; padding: 15px; margin: 20px 0; border-radius: 8px;'>
                                        <p style='color: #0369a1; font-size: 14px; margin: 0 0 10px 0;'><strong>What You Can Do:</strong></p>
                                        <ul style='color: #0c4a6e; font-size: 13px; margin: 0; padding-left: 20px;'>
                                            <li style='margin: 5px 0;'>Review the rejection reason provided above</li>
                                            <li style='margin: 5px 0;'>Contact the {$details['department_name']} for clarification</li>
                                            <li style='margin: 5px 0;'>Book a new appointment if needed</li>
                                            <li style='margin: 5px 0;'>Submit another reschedule request with different details</li>
                                        </ul>
                                    </div>
                                    
                                    <hr style='border: none; border-top: 1px solid #e0e0e0; margin: 30px 0;'>
                                    
                                    <p style='color: #999; font-size: 13px; line-height: 1.6; margin: 0;'>
                                        If you have questions about this decision, please contact the {$details['department_name']} directly for more information.
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

        $mail->AltBody = "Dear {$recipientName},\n\n"
                       . "Your reschedule request has been rejected.\n\n"
                       . "Original Appointment:\n"
                       . "Service: {$details['service_name']}\n"
                       . "Reference: {$details['transaction_id']}\n\n"
                       . "Reason for Rejection:\n{$details['rejection_reason']}\n\n"
                       . "You may contact the {$details['department_name']} for more information or submit a new request.\n\n"
                       . "LGU Quick Appoint Team";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Reschedule Rejected Email Error: " . $mail->ErrorInfo);
        return false;
    }
}