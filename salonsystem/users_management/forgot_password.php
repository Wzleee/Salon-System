<?php 
session_start();
require_once '../config.php'; 

$step = 'form'; 
$msgTitle = '';
$msgContent = '';
$msgType = ''; // 'success' or 'error'

// POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. Introducing Email Tools
    $emailUtilityPath = '../appointment/email_utility.php'; 
    if (file_exists($emailUtilityPath)) {
        require_once $emailUtilityPath;
    } else {
        require_once '../email_utility.php';
    }

    // Ensure key definition
    if (!defined('SECRET_KEY')) define('SECRET_KEY', 'MySuperSecretKey_RandomString12345!@#'); 

    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        $step = 'form';
        $msgType = 'error';
        $msgContent = 'Please enter your email.';
    } else {
        try {
            // Check email
            $stmt = $pdo->prepare("SELECT user_id, name, password_hash FROM user WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_OBJ);

            $step = 'message';
            $msgTitle = "Check Your Email";
            $msgContent = "If an account exists for this email, we have sent a reset link.";

            if ($user) {
                // Generate Token
                $expire = time() + 300; 
                $data = $user->user_id . $user->password_hash . $expire;
                $signature = hash_hmac('sha256', $data, SECRET_KEY);
                $token = base64_encode($user->user_id . '.' . $expire . '.' . $signature);
                
                // Automatically generate the correct link path (Based on the current server)
                $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
                $host = $_SERVER['HTTP_HOST'];
                // Note: This assumes reset_password.php and forgot_password.php in the same directory
                $path = dirname($_SERVER['PHP_SELF']);
                $resetLink = "$protocol://$host$path/reset_password.php?token=" . urlencode($token);

                // Build email content
                $emailBody = "
                    <h2>Reset Your Password</h2>
                    <p>Hi " . htmlspecialchars($user->name) . ",</p>
                    <p>We received a request to reset your password for your Cosmos Salon account.</p>
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='" . $resetLink . "' class='btn'>Reset Password</a>
                    </div>
                    <p>Or copy and paste this link into your browser:</p>
                    <p style='word-break: break-all; font-size: 13px; color: #999;'>" . $resetLink . "</p>
                    <div class='warning-box'>
                        <h3>Note:</h3>
                        <p>This link is valid for <strong>5 minutes</strong>. If you did not request a password reset, please ignore this email.</p>
                    </div>
                ";

                // Send email
                $sent = false;
                if (function_exists('sendEmail')) {
                    $sent = sendEmail($email, "Reset Your Password - Cosmos Salon", $emailBody, $user->name);
                }

                if ($sent) {
                    $msgContent = "We have sent a password reset link to <strong>" . htmlspecialchars($email) . "</strong>.";
                } else {
                    // Debug link for local test failures (Please delete the Debug Link below before deployment)
                    $msgTitle = "Email Sending Failed (Localhost?)";
                    $msgContent = "Could not send email via PHPMailer.<br>Debug Link: <a href='$resetLink'>Click here to Reset</a>";
                }
            }

        } catch (Exception $e) {
            $step = 'message';
            $msgTitle = "Error";
            $msgContent = "System error: " . $e->getMessage();
        }
    }
}

// --- Display Page ---
$pageTitle = 'Forgot Password - Cosmos Salon';
$pageCSS = '../css/auth.css';
include '../head.php';
?>

<div class="login-container">
    <div class="login-card">
        
        <?php if ($step === 'message'): ?>
            <div class="msg-box" style="text-align: center;">
                <h2 style="color: #333; margin-top: 0;"><?php echo $msgTitle; ?></h2>
                <p style="color: #666; line-height: 1.6; margin: 20px 0;">
                    <?php echo $msgContent; ?>
                </p>
                <a href="login.php" class="submit-btn" style="text-decoration: none; display: inline-block; width: auto; padding: 10px 30px;">Back to Login</a>
            </div>

        <?php else: ?>
            <a href="login.php" class="back-to-login">
                <i class="fas fa-arrow-left"></i>
                Back to Login
            </a>
            
            <p class="forgot-description">
                Enter your email address and we'll send you a code to reset your password
            </p>
            
            <?php if (!empty($msgContent) && $msgType === 'error'): ?>
                <div class="error-message" style="color: red; margin-bottom: 15px; font-size: 0.9em;">
                    <?php echo htmlspecialchars($msgContent); ?>
                </div>
            <?php endif; ?>
            
            <form id="forgotPasswordForm" class="auth-form" method="POST" action="">
                <div class="form-group">
                    <label for="email">
                        <i class="bi bi-envelope"></i>
                        Email Address
                    </label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        placeholder="your.email@example.com" 
                        required
                    >
                </div>
                
                <button type="submit" class="submit-btn">Send Reset Code</button>
            </form>
        <?php endif; ?>
        
    </div>
</div>

</body>
</html>
