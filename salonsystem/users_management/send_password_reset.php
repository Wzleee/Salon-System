<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

// Check if the user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

try {
    $userId = $_SESSION['user_id'];
    
    // Get user information
    $stmt = $pdo->prepare("SELECT user_id, name, email, password_hash FROM user WHERE user_id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_OBJ);
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit();
    }
    
    // Introducing Email Tools
    $emailUtilityPath = '../appointment/email_utility.php'; 
    if (file_exists($emailUtilityPath)) {
        require_once $emailUtilityPath;
    } else {
        require_once '../email_utility.php';
    }
    
    // Ensure key definition
    if (!defined('SECRET_KEY')) {
        define('SECRET_KEY', 'MySuperSecretKey_RandomString12345!@#');
    }
    
    // Generate a token (marked as coming from Profile, requires current password verification)
    $expire = time() + 300; // 5-minute validity period
    $data = $user->user_id . $user->password_hash . $expire;
    $signature = hash_hmac('sha256', $data, SECRET_KEY);
    $token = base64_encode($user->user_id . '.' . $expire . '.' . $signature);
    
    // Generate reset link
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $path = dirname(dirname($_SERVER['PHP_SELF']));
    $resetLink = "$protocol://$host$path/users_management/reset_password.php?token=" . urlencode($token) . "&source=profile_email";
    
    // Build email content
    $emailBody = "
        <h2>Password Reset Request</h2>
        <p>Hi " . htmlspecialchars($user->name) . ",</p>
        <p>You have requested to change your password from your profile settings.</p>
        <div style='text-align: center; margin: 30px 0;'>
            <a href='" . $resetLink . "' style='background: #7c3aed; color: white; padding: 12px 30px; text-decoration: none; border-radius: 8px; display: inline-block;'>Reset Password</a>
        </div>
        <p>Or copy and paste this link into your browser:</p>
        <p style='word-break: break-all; font-size: 13px; color: #999;'>" . $resetLink . "</p>
        <div class='warning-box' style='background: #fef3c7; padding: 15px; border-radius: 8px; border-left: 4px solid #f59e0b; margin-top: 20px;'>
            <h3 style='margin-top: 0; color: #92400e;'>Important:</h3>
            <p style='color: #92400e; margin: 0;'>You will need to enter your <strong>current password</strong> to verify your identity before setting a new password. This link is valid for <strong>5 minutes</strong>.</p>
        </div>
        <p style='margin-top: 20px; font-size: 0.9rem; color: #6b7280;'>If you did not request this password reset, please ignore this email and your password will remain unchanged.</p>
    ";
    
    // Send email
    $sent = false;
    if (function_exists('sendEmail')) {
        $sent = sendEmail($user->email, "Password Reset Request - Cosmos Salon", $emailBody, $user->name);
    }
    
    if ($sent) {
        echo json_encode([
            'success' => true, 
            'message' => 'Password reset link has been sent to your email'
        ]);
    } else {
        // If it's a local environment, provide a debug link
        if ($_SERVER['HTTP_HOST'] === 'localhost' || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false) {
            echo json_encode([
                'success' => false, 
                'message' => 'Email sending failed (localhost). Debug link: ' . $resetLink,
                'debug_link' => $resetLink
            ]);
        } else {
            echo json_encode([
                'success' => false, 
                'message' => 'Failed to send email. Please try again later.'
            ]);
        }
    }
    
} catch (Exception $e) {
    error_log("Password reset request error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'System error occurred']);
}
?>
