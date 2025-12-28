<?php 
session_start();
require_once '../config.php';

// First-login forced password change is disabled; temp passwords can be used directly.
$error = '';
$successMessage = '';
$email_value = '';

// 1. Processing "Remember Me" Cookie Reading
if (isset($_COOKIE['remembered_email'])) {
    $email_value = $_COOKIE['remembered_email'];
}

// 2. Check if there is a message indicating successful cancellation
if (isset($_GET['logout']) && $_GET['logout'] === 'success') {
    $successMessage = 'You have been logged out successfully.';
}

// 3. Processing form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember_me = isset($_POST['remember_me']);
    
    $email_value = $email;

    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields';
    } 
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
    } 
    else {
        try {
            // Checking user
            $stmt = $pdo->prepare("SELECT user_id, name, email, password_hash, role, created_at FROM user WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            // Verify Password
            if (!$user || !password_verify($password, $user->password_hash)) {
                $error = 'Invalid email or password';
            } else {
                // --- Login successful ---

                // Setting Session
                $_SESSION['user_id'] = $user->user_id;
                $_SESSION['uSettingser_name'] = $user->name;
                $_SESSION['user_email'] = $user->email;
                $_SESSION['user_role'] = $user->role;
                $_SESSION['logged_in'] = true;
                
                // Setting successful
                $_SESSION['login_success'] = "Welcome back, " . htmlspecialchars($user->name) . "!";
                
                // Login logs
                $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
                $logStmt = $pdo->prepare("
                    INSERT INTO auditlog (user_id, action, category, description, ip_address) 
                    VALUES (?, 'Logged in', 'login', 'User logged into the system', ?)
                ");
                $logStmt->execute([$user->user_id, $ip_address]);
                
                // Handling "Remember Me" Cookie
                if ($remember_me) {
                    setcookie('remembered_email', $email, time() + (30 * 24 * 60 * 60), '/', '', false, true);
                } else {
                    if (isset($_COOKIE['remembered_email'])) {
                        setcookie('remembered_email', '', time() - 3600, '/', '', false, true);
                    }
                }
                
                // Check if need to change password
                switch ($user->role) {
                    case 'Admin':
                        header('Location: ../users_management/users.php');
                        break;
                    case 'Staff':
                        header('Location: ../schedule_service/schedule.php');
                        break;
                    case 'Customer':
                    default:
                        header('Location: ../index.php');
                        break;
                }
                exit();
            }
            
        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            $error = 'A system error occurred. Please try again later';
        }
    }
}

$pageTitle = 'Login - Cosmos Salon';
$pageCSS = '../css/auth.css';
include '../head.php';
?>

<div class="login-container">
    <div class="login-card">
        <div class="brand-section">
            <i class="bi bi-scissors brand-icon"></i>
            <h2 class="brand-title">Cosmos Salon</h2>
        </div>
        
        <p class="welcome-text">Welcome back! Please login to continue</p>
        
        <?php
        if (!empty($successMessage)) {
            echo '<div class="success-message">' . htmlspecialchars($successMessage) . '</div>';
        }
        
        if (!empty($error)) {
            echo '<div class="error-message">' . htmlspecialchars($error) . '</div>';
        }
        ?>
        
        <form id="loginForm" class="auth-form" method="POST" action="">
            <div class="form-group">
                <label for="login-email">
                    <i class="bi bi-envelope"></i>
                    Email
                </label>
                <input 
                    type="email" 
                    id="login-email" 
                    name="email" 
                    placeholder="your.email@example.com"
                    value="<?php echo htmlspecialchars($email_value); ?>" 
                    required
                >
            </div>
            
            <div class="form-group">
                <label for="login-password">
                    <i class="bi bi-lock"></i>
                    Password
                </label>
                <input 
                    type="password" 
                    id="login-password" 
                    name="password" 
                    placeholder="Enter your password" 
                    required
                >
            </div>
            
            <div class="remember-me-container">
                <div class="remember-me">
                    <input type="checkbox" id="rememberMe" name="remember_me" class="remember-checkbox" <?php echo !empty($_COOKIE['remembered_email']) ? 'checked' : ''; ?>>
                    <label for="rememberMe" class="remember-label">Remember me</label>
                </div>
                <div class="forgot-password">
                    <a href="forgot_password.php">Forgot Password?</a>
                </div>
            </div>
            
            <button type="submit" class="submit-btn">Login</button>
            
            <div class="switch-form">
                <p>Don't have an account? <a href="register.php">Register here</a></p>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const successMessage = document.querySelector('.success-message');
    const errorMessage = document.querySelector('.error-message');
    
    if (successMessage && errorMessage) {
        successMessage.style.display = 'none';
    }
    
    if (successMessage && !errorMessage) {
        setTimeout(function() {
            successMessage.style.opacity = '0';
            successMessage.style.transition = 'opacity 0.5s ease-out';
            setTimeout(function() {
                successMessage.style.display = 'none';
            }, 500);
        }, 3000);
    }
    
    if (errorMessage) {
        setTimeout(function() {
            errorMessage.style.opacity = '0';
            errorMessage.style.transition = 'opacity 0.5s ease-out';
            setTimeout(function() {
                errorMessage.style.display = 'none';
            }, 500);
        }, 5000);
    }
});
</script>

</body>
</html>
