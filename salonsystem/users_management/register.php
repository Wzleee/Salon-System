<?php 
session_start();
require_once '../config.php';
require_once 'validation_utils.php'; 

$fullname = '';
$email = '';
$phone = '';
$address = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Get and clean data
    $fullname = trim($_POST['fullname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Use a shared verification function
    $nameValidation = validateName($fullname);
    if ($nameValidation !== true) {
        $errors[] = $nameValidation;
    }

    $emailValidation = validateEmail($email);
    if ($emailValidation !== true) {
        $errors[] = $emailValidation;
    }

    $phoneValidation = validateMalaysianPhone($phone);
    if ($phoneValidation !== true) {
        $errors[] = $phoneValidation;
    }

    $addressValidation = validateAddress($address);
    if ($addressValidation !== true) {
        $errors[] = $addressValidation;
    }

    if (empty($password)) {
        $errors[] = "Password is required";
    } else {
        $passwordValidation = validatePasswordStrength($password);
        if ($passwordValidation !== true) {
            $errors[] = $passwordValidation;
        }
    }

    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }

    // If there are no basic validation errors, check the database
    if (empty($errors)) {
        try {
            // Use a shared function to check if the email address already exists.
            if (emailExists($pdo, $email)) {
                $errors[] = "Email is already registered";
            } else {
                // Email is available; proceed with registration.
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                
                $stmt = $pdo->prepare("
                    INSERT INTO user (name, email, phone, password_hash, role) 
                    VALUES (?, ?, ?, ?, 'Customer')
                ");
                
                $stmt->execute([
                    $fullname,
                    $email,
                    $phone,
                    $password_hash
                ]);
                
                $user_id = $pdo->lastInsertId();
                
                // Registration successful, login automatically
                $_SESSION['user_id'] = $user_id;
                $_SESSION['user_name'] = $fullname;
                $_SESSION['user_email'] = $email;
                $_SESSION['user_role'] = 'Customer';
                $_SESSION['logged_in'] = true;
                $_SESSION['register_success'] = "Registration successful! Welcome to Cosmos Salon.";
                
                header('Location: ../index.php');
                exit();
            }
            
        } catch (PDOException $e) {
            error_log("Registration error: " . $e->getMessage());
            $errors[] = "A system error occurred. Please try again later.";
        }
    }
}

$pageTitle = 'Register - Cosmos Salon';
$pageCSS = '../css/auth.css';
include '../head.php';
?>

<div class="login-container">
    <div class="login-card">
        <div class="brand-section">
            <i class="bi bi-scissors brand-icon"></i>
            <h2 class="brand-title">Cosmos Salon</h2>
        </div>
        
        <p class="welcome-text">Welcome~ Let's create your account now!</p>
        
        <?php if (!empty($errors)): ?>
        <div class="alert alert-danger" id="validation-errors">
            <ul style="margin: 0; padding-left: 20px;">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
        
        <form id="registerForm" class="auth-form" method="POST" action="">
            <div class="form-group">
                <label for="register-name">
                    <i class="bi bi-person"></i>Full Name <span class="required">*</span>
                </label>
                <input 
                    type="text" 
                    id="register-name" 
                    name="fullname" 
                    placeholder="Enter your full name"
                    value="<?php echo htmlspecialchars($fullname); ?>"
                    maxlength="50"
                    required
                >
            </div>
            
            <div class="form-group">
                <label for="register-email">
                    <i class="bi bi-envelope"></i>Email <span class="required">*</span>
                </label>
                <input 
                    type="email" 
                    id="register-email" 
                    name="email" 
                    placeholder="your.email@example.com"
                    value="<?php echo htmlspecialchars($email); ?>"
                    maxlength="100"
                    required
                >
            </div>
            
            <div class="form-group">
                <label for="register-phone">
                    <i class="bi bi-telephone"></i>
                    Phone Number <span class="required">*</span>
                </label>
                <input 
                    type="tel" 
                    id="register-phone" 
                    name="phone" 
                    placeholder="01X-XXXXXXXX"
                    value="<?php echo htmlspecialchars($phone); ?>"
                    pattern="^(01[0-9]-[0-9]{8,9})$"
                    title="Please enter a valid phone number (e.g., 012-34567890)"
                    required
                >
                <small class="phone-hint" style="font-size: 0.85rem; color: #6b7280; margin-top: 5px; display: block;">
                    Format: 01X-XXXXXXXX
                </small>
            </div>
            
            <div class="form-group">
                <label for="register-password">
                    <i class="bi bi-lock"></i>
                    Password <span class="required">*</span>
                </label>
                <input 
                    type="password" 
                    id="register-password" 
                    name="password" 
                    placeholder="Min. 8 characters with letters & numbers" 
                    minlength="8"
                    required
                >
            </div>
            
            <div class="form-group">
                <label for="register-confirm-password">
                    <i class="bi bi-lock"></i>
                    Confirm Password <span class="required">*</span>
                </label>
                <input 
                    type="password" 
                    id="register-confirm-password" 
                    name="confirm_password" 
                    placeholder="Re-enter your password" 
                    required
                >
            </div>
            
            <button type="submit" class="submit-btn">Register</button>
            
            <div class="switch-form">
                <p>Already have an account? <a href="login.php">Login here</a></p>
            </div>
        </form>
    </div>
</div>

<style>
.alert {
    padding: 12px 15px;
    margin-bottom: 20px;
    border-radius: 8px;
    font-size: 14px;
}

.alert-danger {
    background-color: #fee2e2;
    border: 1px solid #fca5a5;
    color: #991b1b;
}

.alert ul {
    margin: 0;
    padding-left: 20px;
}

.alert li {
    margin: 5px 0;
}
</style>


<script>

document.addEventListener('DOMContentLoaded', function() {
    const errorAlert = document.querySelector('.alert-danger');
    if (errorAlert) {
        setTimeout(function() {
            errorAlert.style.opacity = '0';
            errorAlert.style.transition = 'opacity 0.5s ease-out';
            setTimeout(function() {
                errorAlert.style.display = 'none';
            }, 500);
        }, 5000); 
    }
    
});
</script>

</body>
</html>