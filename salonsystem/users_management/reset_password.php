
<?php
session_start();
require_once '../config.php';
require_once 'validation_utils.php';

if (!defined('SECRET_KEY')) define('SECRET_KEY', 'MySuperSecretKey_RandomString12345!@#'); 

$error = '';
$tokenRaw = $_REQUEST['token'] ?? '';
$source = $_GET['source'] ?? '';

$fromProfileEmail = ($source === 'profile_email'); // Change password flow launched from profile email

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $currentPassword = $_POST['current_password'] ?? '';
    $postSource = $_POST['source'] ?? '';
    $postToken = $_POST['token'] ?? '';

    // Track validation state
    $isValid = false;
    $targetUserId = null;

    // Basic required + match + strength checks
    if (empty($password)) {
        $error = "Password is required.";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    } else {
        $strengthCheck = validatePasswordStrength($password);
        if ($strengthCheck !== true) {
            $error = $strengthCheck;
        }
    }

    if (empty($error)) {
        // Flow 1: profile email change (requires current password + token)
        if ($postSource === 'profile_email') {
            if (empty($currentPassword)) {
                $error = "Please enter your current password.";
            } elseif (empty($postToken)) {
                $error = "Invalid request. No token provided.";
            } else {
                $decoded = base64_decode($postToken);
                $parts = explode('.', $decoded);

                if (count($parts) === 3) {
                    $uid = $parts[0];
                    $expiry = $parts[1];
                    $sig = $parts[2];

                    if (time() < $expiry) {
                        $stmt = $pdo->prepare("SELECT password_hash FROM user WHERE user_id = ?");
                        $stmt->execute([$uid]);
                        $user = $stmt->fetch(PDO::FETCH_OBJ);

                        if ($user) {
                            $checkData = $uid . $user->password_hash . $expiry;
                            $expectedSig = hash_hmac('sha256', $checkData, SECRET_KEY);

                            if (hash_equals($expectedSig, $sig)) {
                                if (password_verify($currentPassword, $user->password_hash)) {
                                    $isValid = true;
                                    $targetUserId = $uid;
                                } else {
                                    $error = "Current password is incorrect.";
                                }
                            } else {
                                $error = "Invalid token.";
                            }
                        } else {
                            $error = "User not found.";
                        }
                    } else {
                        $error = "Token has expired. Please request a new password reset.";
                    }
                } else {
                    $error = "Invalid token format.";
                }
            }
        }
        // Flow 2: forgot password (token only)
        else {
            if (empty($postToken)) {
                $error = "Invalid request. No token provided.";
            } else {
                $decoded = base64_decode($postToken);
                $parts = explode('.', $decoded);

                if (count($parts) === 3) {
                    $uid = $parts[0];
                    $expiry = $parts[1];
                    $sig = $parts[2];

                    if (time() < $expiry) {
                        $stmt = $pdo->prepare("SELECT password_hash FROM user WHERE user_id = ?");
                        $stmt->execute([$uid]);
                        $u = $stmt->fetch(PDO::FETCH_OBJ);

                        if ($u) {
                            $checkData = $uid . $u->password_hash . $expiry;
                            $expectedSig = hash_hmac('sha256', $checkData, SECRET_KEY);

                            if (hash_equals($expectedSig, $sig)) {
                                $isValid = true;
                                $targetUserId = $uid;
                            } else {
                                $error = "Invalid token.";
                            }
                        } else {
                            $error = "User not found.";
                        }
                    } else {
                        $error = "Token has expired. Please request a new password reset.";
                    }
                } else {
                    $error = "Invalid token format.";
                }
            }
        }
    }

    // If validation passes, update password
    if (empty($error) && $isValid && $targetUserId) {
        try {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE user SET password_hash = ? WHERE user_id = ?");
            $stmt->execute([$newHash, $targetUserId]);
            
            // Clear any legacy force-change flags
            if (isset($_SESSION['must_change_password'])) {
                unset($_SESSION['must_change_password']);
                unset($_SESSION['password_change_reason']);
            }
            
            // Always redirect to login after password change
            $_SESSION['register_success'] = "Password changed successfully! Please login with your new password.";
            session_destroy();
            header('Location: login.php');
            exit();
            
        } catch (PDOException $e) {
            $error = "Database error. Please try again.";
        }
    }
}

$pageHeading = 'Reset Password';
$pageDescription = 'Please enter your new password below.';
$requireCurrentPassword = false;

if ($fromProfileEmail) {
    $pageHeading = 'Change Password';
    $pageDescription = 'For security, please verify your current password before setting a new one.';
    $requireCurrentPassword = true;
}

$pageTitle = $pageHeading . ' - Cosmos Salon';
$pageCSS = '../css/auth.css';
include '../head.php'; 
?>

<div class="login-container">
    <div class="login-card">
        <div class="brand-section">
            <h2 class="brand-title"><?php echo $pageHeading; ?></h2>
        </div>

        <p class="welcome-text"><?php echo $pageDescription; ?></p>

        <?php if ($fromProfileEmail): ?>
            <div style="background: #f0f9ff; padding: 12px; border-radius: 8px; border-left: 4px solid #3b82f6; margin-bottom: 20px;">
                <p style="margin: 0; font-size: 0.9rem; color: #1e40af;">
                    <i class="fas fa-info-circle"></i> 
                    You requested this password change from your profile. Please verify your identity with your current password.
                </p>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger" style="background-color: #fee2e2; color: #991b1b; padding: 12px; margin-bottom: 15px; border-radius: 8px; border-left: 4px solid #dc2626;">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form action="" method="POST" class="auth-form" id="resetForm">
            <input type="hidden" name="source" value="<?php echo htmlspecialchars($source); ?>">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($tokenRaw); ?>">

            <?php if ($requireCurrentPassword): ?>
                <div class="form-group">
                    <label>Current Password <span style="color: #dc2626;">*</span></label>
                    <input type="password" name="current_password" id="current_password" required placeholder="Enter your current password">
                </div>
            <?php endif; ?>
            
            <div class="form-group">
                <label>New Password <span style="color: #dc2626;">*</span></label>
                <input type="password" name="password" id="password" required placeholder="Enter new password (min. 8 characters, letters & numbers)" minlength="8">
            </div>

            <div class="form-group">
                <label>Confirm Password <span style="color: #dc2626;">*</span></label>
                <input type="password" name="confirm_password" id="confirm_password" required placeholder="Confirm new password">
            </div>

            <button type="submit" class="submit-btn">
                <?php echo $fromProfileEmail ? 'Change Password' : 'Reset Password'; ?>
            </button>
        </form>
    </div>
</div>



</body>
</html>
