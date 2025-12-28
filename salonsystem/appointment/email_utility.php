<?php
/**
 * Email Utility for Cosmos Salon - Fixed Version
 */

// Load the 3 PHPMailer files with correct paths
require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ========================================
// ⚠️ CHANGE THESE TWO LINES!
// ========================================
define('SMTP_USERNAME', 'noreplylawook@gmail.com');     // Your Gmail
define('SMTP_PASSWORD', 'qjwx gdkd ewqm aoiz');    // Your App Password

// Salon Information
define('SALON_EMAIL', 'info@cosmossalon.com');
define('SALON_NAME', 'Cosmos Salon');
define('SALON_PHONE', '+60 12-345 6789');
define('SALON_ADDRESS', 'Setapak Central Mall, Jalan Taman Ibu Kota, 53300 Kuala Lumpur, Malaysia');

/**
 * Send email using Gmail SMTP
 */
function sendEmail($to, $subject, $body, $toName = '') {
    $mail = new PHPMailer(true);
    
    try {
        // SMTP Configuration
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;
        
        // For XAMPP - disable SSL verification
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        // Email settings
        $mail->setFrom(SMTP_USERNAME, SALON_NAME);
        $mail->addAddress($to, $toName);
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = $subject;
        $mail->Body = getEmailTemplate($body);
        
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        error_log("Email Error: {$mail->ErrorInfo}");
        return false;
    }
}



/**
 * Email template wrapper - Enhanced design matching website
 */
function getEmailTemplate($content) {
    return '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { 
                font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; 
                line-height: 1.8; 
                color: #2d2d2d; 
                background: linear-gradient(135deg, #fdf4ff 0%, #f9f9f9 100%); 
                padding: 40px 20px; 
            }
            .email-wrapper { 
                max-width: 600px; 
                margin: 0 auto; 
            }
            .email-container { 
                background: #ffffff; 
                border-radius: 15px; 
                overflow: hidden; 
                box-shadow: 0 10px 40px rgba(147, 51, 234, 0.15); 
            }
            
            /* Header Section */
            .email-header { 
                background: linear-gradient(135deg, #9333ea 0%, #7c3aed 100%); 
                color: white; 
                padding: 50px 40px 40px; 
                text-align: center; 
                position: relative;
            }
            .email-header::before {
                content: "";
                position: absolute;
                bottom: -2px;
                left: 0;
                right: 0;
                height: 3px;
                background: linear-gradient(90deg, transparent, white, transparent);
                opacity: 0.3;
            }
            .logo-container { 
                margin-bottom: 20px; 
            }
            .logo { 
                font-size: 50px; 
                line-height: 1;
                animation: float 3s ease-in-out infinite;
            }
            @keyframes float {
                0%, 100% { transform: translateY(0px); }
                50% { transform: translateY(-8px); }
            }
            .brand-title { 
                font-size: 32px; 
                font-weight: 300; 
                letter-spacing: 8px; 
                margin: 15px 0 0; 
                text-transform: uppercase;
            }
            .tagline {
                font-size: 14px;
                letter-spacing: 3px;
                opacity: 0.9;
                margin-top: 10px;
                font-weight: 300;
            }
            
            /* Body Section */
            .email-body { 
                padding: 40px 40px 30px; 
                background: #ffffff;
            }
            .email-body h2 { 
                color: #9333ea; 
                font-size: 28px; 
                font-weight: 400; 
                margin: 0 0 20px; 
                letter-spacing: 1px;
            }
            .email-body p { 
                font-size: 16px; 
                color: #666; 
                margin-bottom: 15px; 
                line-height: 1.8;
            }
            
            /* Info Box */
            .info-box { 
                background: linear-gradient(135deg, #fdf4ff 0%, #f3e8ff 100%); 
                border: 2px solid #e9d5ff;
                border-radius: 12px; 
                padding: 25px; 
                margin: 25px 0; 
                box-shadow: 0 3px 10px rgba(147, 51, 234, 0.08);
            }
            .info-box h3 { 
                color: #9333ea; 
                font-size: 20px; 
                font-weight: 500; 
                margin: 0 0 20px; 
                padding-bottom: 15px;
                border-bottom: 2px solid #e9d5ff;
                letter-spacing: 0.5px;
            }
            
            /* Detail Rows */
            .detail-row { 
                display: flex;
                padding: 12px 0; 
                border-bottom: 1px solid #e9d5ff; 
                align-items: flex-start;
            }
            .detail-row:last-child { 
                border-bottom: none; 
                padding-bottom: 0;
            }
            .detail-label { 
                font-weight: 600; 
                color: #666; 
                min-width: 130px;
                font-size: 15px;
            }
            .detail-value { 
                color: #2d2d2d; 
                font-size: 15px;
                flex: 1;
            }
            .detail-value strong {
                color: #9333ea;
                font-weight: 600;
            }
            
            /* Button */
            .btn { 
                display: inline-block; 
                padding: 15px 40px; 
                background: #9333ea; 
                color: white !important; 
                text-decoration: none; 
                border-radius: 8px; 
                margin: 25px 0; 
                font-weight: 600; 
                font-size: 14px;
                letter-spacing: 2px;
                text-transform: uppercase;
                box-shadow: 0 5px 15px rgba(147, 51, 234, 0.3);
                transition: all 0.3s ease;
            }
            .btn:hover {
                background: #7c3aed;
                box-shadow: 0 7px 20px rgba(147, 51, 234, 0.4);
            }
            
            /* Lists */
            ul { 
                padding-left: 20px; 
                margin: 15px 0; 
            }
            ul li { 
                color: #666; 
                margin-bottom: 10px; 
                font-size: 15px;
                line-height: 1.6;
            }
            
            /* Warning Box */
            .warning-box {
                background: #fef3c7;
                border: 2px solid #fbbf24;
                border-radius: 10px;
                padding: 20px;
                margin: 20px 0;
            }
            .warning-box h3 {
                color: #92400e;
                font-size: 18px;
                margin-bottom: 10px;
            }
            
            /* Success Icon */
            .success-icon {
                font-size: 60px;
                text-align: center;
                margin: 20px 0;
            }
            
            /* Footer Section */
            .email-footer { 
                background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%); 
                padding: 30px 40px; 
                text-align: center; 
                border-top: 3px solid #e9d5ff; 
            }
            .footer-brand { 
                font-size: 20px; 
                font-weight: 600; 
                color: #2d2d2d; 
                letter-spacing: 3px;
                margin-bottom: 15px;
            }
            .footer-address { 
                font-size: 14px; 
                color: #666; 
                line-height: 1.8; 
                margin-bottom: 15px;
            }
            .footer-contact { 
                font-size: 14px; 
                color: #9333ea; 
                margin-bottom: 20px;
            }
            .footer-contact a {
                color: #9333ea;
                text-decoration: none;
            }
            .footer-divider {
                width: 60%;
                height: 1px;
                background: linear-gradient(90deg, transparent, #e9d5ff, transparent);
                margin: 20px auto;
            }
            .footer-note { 
                font-size: 12px; 
                color: #999; 
                font-style: italic;
            }
            
            /* Responsive */
            @media only screen and (max-width: 600px) {
                body { padding: 20px 10px; }
                .email-header { padding: 30px 20px; }
                .email-body { padding: 25px 20px; }
                .email-footer { padding: 20px; }
                .brand-title { font-size: 24px; letter-spacing: 5px; }
                .email-body h2 { font-size: 22px; }
                .detail-row { flex-direction: column; }
                .detail-label { margin-bottom: 5px; }
            }
        </style>
    </head>
    <body>
        <div class="email-wrapper">
            <div class="email-container">
                <div class="email-header">
                    <div class="brand-title">' . SALON_NAME . '</div>
                    <div class="tagline">Hair & Beauty Salon</div>
                </div>
                <div class="email-body">' . $content . '</div>
                <div class="email-footer">
                    <div class="footer-brand">' . SALON_NAME . '</div>
                    <div class="footer-address">' . SALON_ADDRESS . '</div>
                    <div class="footer-contact">
                        Phone: ' . SALON_PHONE . '<br>
                        Email: <a href="mailto:' . SALON_EMAIL . '">' . SALON_EMAIL . '</a>
                    </div>
                    <div class="footer-divider"></div>
                    <div class="footer-note">This is an automated message. Please do not reply to this email.</div>
                </div>
            </div>
        </div>
    </body>
    </html>';
}

/**
 * Send booking confirmation email
 */
function sendBookingConfirmation($customerEmail, $customerName, $bookingDetails) {
    $services = implode('<br>', array_map(function($s) {
        return '<div style="padding: 8px 0; color: #666;">
                    <span style="color: #9333ea;">✓</span> ' . 
                    htmlspecialchars($s['service_name']) . 
                    ' <span style="color: #999; font-size: 14px;">(' . $s['duration_minutes'] . ' min)</span>
                    <span style="float: right; color: #9333ea; font-weight: 600;">RM ' . number_format($s['service_price'], 2) . '</span>
                </div>';
    }, $bookingDetails['services']));
    
    $content = '
        <div class="success-icon">🎉</div>
        <h2 style="text-align: center;">Booking Confirmed!</h2>
        <p>Dear <strong>' . htmlspecialchars($customerName) . '</strong>,</p>
        <p>Thank you for choosing Cosmos Salon! We are delighted to confirm your appointment. Our team is looking forward to providing you with an exceptional experience.</p>
        
        <div class="info-box">
            <h3>Appointment Details</h3>
            <div class="detail-row">
                <span class="detail-label">Booking ID</span>
                <span class="detail-value"><strong>#' . str_pad($bookingDetails['appointment_id'], 6, '0', STR_PAD_LEFT) . '</strong></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Date</span>
                <span class="detail-value">' . date('l, F j, Y', strtotime($bookingDetails['appointment_date'])) . '</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Time</span>
                <span class="detail-value">' . date('g:i A', strtotime($bookingDetails['appointment_time'])) . '</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Stylist</span>
                <span class="detail-value">' . htmlspecialchars($bookingDetails['stylist_name']) . '</span>
            </div>
            <div class="detail-row" style="border-bottom: 2px solid #e9d5ff; padding-bottom: 15px; margin-bottom: 15px;">
                <span class="detail-label">Services</span>
                <span class="detail-value">' . $services . '</span>
            </div>
            <div class="detail-row" style="padding-top: 10px;">
                <span class="detail-label" style="font-size: 17px;">Total Price</span>
                <span class="detail-value" style="font-size: 24px; color: #9333ea;"><strong>RM ' . number_format($bookingDetails['total_price'], 2) . '</strong></span>
            </div>
        </div>
        
        <div style="background: #fef3c7; border-left: 4px solid #f59e0b; padding: 20px; border-radius: 10px; margin: 25px 0;">
            <p style="margin: 0 0 10px 0; color: #92400e; font-weight: 600; font-size: 16px;">
                <span style="font-size: 20px;">⚠️</span> Important Reminders
            </p>
            <ul style="margin: 0; padding-left: 20px; color: #92400e;">
                <li>Please arrive <strong>10 minutes before</strong> your scheduled time</li>
                <li>Cancellations must be made at least <strong>24 hours in advance</strong></li>
                <li>You will receive a reminder <strong>24 hours</strong> before your appointment</li>
            </ul>
        </div>
        
        <p style="text-align: center; font-size: 16px; color: #2d2d2d; margin: 30px 0 20px;">
            We look forward to pampering you!
        </p>
        
        <p style="color: #666; margin-top: 30px;">
            Warm regards,<br>
            <strong style="color: #9333ea;">The Cosmos Salon Team</strong>
        </p>';
    
    return sendEmail($customerEmail, '✨ Booking Confirmed - ' . SALON_NAME, $content, $customerName);
}

/**
 * Send reschedule confirmation email
 */
function sendRescheduleConfirmation($customerEmail, $customerName, $bookingDetails, $oldDate, $oldTime) {
    $services = implode(', ', array_map(function($s) {
        return htmlspecialchars($s['service_name']);
    }, $bookingDetails['services']));
    
    $content = '
        <h2 style="color: #9333ea; margin-top: 0;">Appointment Rescheduled</h2>
        <p>Dear ' . htmlspecialchars($customerName) . ',</p>
        <p>Your appointment has been successfully rescheduled.</p>
        <div class="info-box" style="background: #fef3c7; border-left-color: #f59e0b;">
            <h3 style="margin-top: 0; color: #92400e;">Previous Appointment</h3>
            <p style="margin: 5px 0;"><strong>Date:</strong> ' . date('l, F j, Y', strtotime($oldDate)) . '</p>
            <p style="margin: 5px 0;"><strong>Time:</strong> ' . date('g:i A', strtotime($oldTime)) . '</p>
        </div>
        <div class="info-box">
            <h3 style="margin-top: 0; color: #9333ea;">New Appointment Details</h3>
            <div class="detail-row">
                <span class="detail-label">Booking ID:</span>
                <span class="detail-value">#' . str_pad($bookingDetails['appointment_id'], 6, '0', STR_PAD_LEFT) . '</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Date:</span>
                <span class="detail-value">' . date('l, F j, Y', strtotime($bookingDetails['appointment_date'])) . '</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Time:</span>
                <span class="detail-value">' . date('g:i A', strtotime($bookingDetails['appointment_time'])) . '</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Stylist:</span>
                <span class="detail-value">' . htmlspecialchars($bookingDetails['stylist_name']) . '</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Services:</span>
                <span class="detail-value">' . $services . '</span>
            </div>
        </div>
        <p>Please arrive 10 minutes before your scheduled time. We look forward to seeing you!</p>
        <p>Best regards,<br>The ' . SALON_NAME . ' Team</p>';
    
    return sendEmail($customerEmail, 'Appointment Rescheduled - ' . SALON_NAME, $content, $customerName);
}

/**
 * Send cancellation confirmation email
 */
function sendCancellationConfirmation($customerEmail, $customerName, $bookingDetails) {
    $services = implode(', ', array_map(function($s) {
        return htmlspecialchars($s['service_name']);
    }, $bookingDetails['services']));
    
    $content = '
        <h2 style="color: #dc2626; margin-top: 0;">Appointment Cancelled</h2>
        <p>Dear ' . htmlspecialchars($customerName) . ',</p>
        <p>Your appointment has been cancelled as requested.</p>
        <div class="info-box">
            <h3 style="margin-top: 0; color: #dc2626;">Cancelled Appointment</h3>
            <div class="detail-row">
                <span class="detail-label">Booking ID:</span>
                <span class="detail-value">#' . str_pad($bookingDetails['appointment_id'], 6, '0', STR_PAD_LEFT) . '</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Date:</span>
                <span class="detail-value">' . date('l, F j, Y', strtotime($bookingDetails['appointment_date'])) . '</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Time:</span>
                <span class="detail-value">' . date('g:i A', strtotime($bookingDetails['appointment_time'])) . '</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Services:</span>
                <span class="detail-value">' . $services . '</span>
            </div>
        </div>
        <p>We\'re sorry to see you cancel. If you\'d like to book another appointment, we\'re always here to help.</p>
        <a href="http://localhost/cosmos-salon/appointment/appointment.php" class="btn">Book New Appointment</a>
        <p>Best regards,<br>The ' . SALON_NAME . ' Team</p>';
    
    return sendEmail($customerEmail, 'Appointment Cancelled - ' . SALON_NAME, $content, $customerName);
}

/**
 * Send appointment reminder (1 day before)
 */
function sendAppointmentReminder($customerEmail, $customerName, $bookingDetails) {
    $services = implode('<br>', array_map(function($s) {
        return '• ' . htmlspecialchars($s['service_name']) . ' (' . $s['duration_minutes'] . ' min)';
    }, $bookingDetails['services']));
    
    $content = '
        <h2 style="color: #9333ea; margin-top: 0;">Appointment Reminder ⏰</h2>
        <p>Dear ' . htmlspecialchars($customerName) . ',</p>
        <p>This is a friendly reminder about your upcoming appointment <strong>tomorrow</strong>!</p>
        <div class="info-box">
            <h3 style="margin-top: 0; color: #9333ea;">Your Appointment</h3>
            <div class="detail-row">
                <span class="detail-label">Date:</span>
                <span class="detail-value">' . date('l, F j, Y', strtotime($bookingDetails['appointment_date'])) . '</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Time:</span>
                <span class="detail-value">' . date('g:i A', strtotime($bookingDetails['appointment_time'])) . '</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Stylist:</span>
                <span class="detail-value">' . htmlspecialchars($bookingDetails['stylist_name']) . '</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Services:</span>
                <span class="detail-value">' . $services . '</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Total:</span>
                <span class="detail-value"><strong>RM ' . number_format($bookingDetails['total_price'], 2) . '</strong></span>
            </div>
        </div>
        <p><strong>Please Note:</strong></p>
        <ul>
            <li>Arrive 10 minutes early</li>
            <li>If you need to cancel or reschedule, please contact us as soon as possible</li>
        </ul>
        <p>We look forward to seeing you tomorrow!</p>
        <p>Best regards,<br>The ' . SALON_NAME . ' Team</p>';
    
    return sendEmail($customerEmail, 'Appointment Reminder - Tomorrow at ' . date('g:i A', strtotime($bookingDetails['appointment_time'])), $content, $customerName);
}

/**
 * Send contact form submission confirmation
 */
function sendContactFormConfirmation($email, $name, $subject, $message) {
    $content = '
        <h2 style="color: #9333ea; margin-top: 0;">Thank You for Contacting Us!</h2>
        <p>Dear ' . htmlspecialchars($name) . ',</p>
        <p>We have received your message and will get back to you as soon as possible, typically within 24 hours.</p>
        <div class="info-box">
            <h3 style="margin-top: 0; color: #9333ea;">Your Message</h3>
            <div class="detail-row">
                <span class="detail-label">Subject:</span>
                <span class="detail-value">' . htmlspecialchars($subject) . '</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Message:</span>
                <span class="detail-value">' . nl2br(htmlspecialchars($message)) . '</span>
            </div>
        </div>
        <p>If your inquiry is urgent, please feel free to call us at ' . SALON_PHONE . '.</p>
        <p>Best regards,<br>The ' . SALON_NAME . ' Team</p>';
    
    return sendEmail($email, 'We Received Your Message - ' . SALON_NAME, $content, $name);
}

/**
 * Send contact form notification to salon
 */
function sendContactFormNotification($name, $email, $phone, $subject, $message) {
    $content = '
        <h2 style="color: #9333ea; margin-top: 0;">New Contact Form Submission</h2>
        <p>You have received a new message from your website contact form.</p>
        <div class="info-box">
            <h3 style="margin-top: 0; color: #9333ea;">Contact Details</h3>
            <div class="detail-row">
                <span class="detail-label">Name:</span>
                <span class="detail-value">' . htmlspecialchars($name) . '</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Email:</span>
                <span class="detail-value"><a href="mailto:' . htmlspecialchars($email) . '">' . htmlspecialchars($email) . '</a></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Phone:</span>
                <span class="detail-value">' . htmlspecialchars($phone) . '</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Subject:</span>
                <span class="detail-value">' . htmlspecialchars($subject) . '</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Message:</span>
                <span class="detail-value">' . nl2br(htmlspecialchars($message)) . '</span>
            </div>
        </div>
        <p>Please respond to this inquiry at your earliest convenience.</p>';
    
    return sendEmail(SMTP_USERNAME, 'New Contact Form Submission - ' . $subject, $content);
}
?>
