<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include config to access database
require_once __DIR__ . '/config.php';

// Get user details from database if logged in
$currentUser = null;
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true && isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT user_id, name, email, phone, role FROM user WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Update session with latest data
        if ($currentUser) {
            $_SESSION['user_name'] = $currentUser['name'];
            $_SESSION['user_email'] = $currentUser['email'];
            $_SESSION['user_role'] = $currentUser['role'];
        }
    } catch (PDOException $e) {
        error_log("Error fetching user data: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle : 'Cosmos Salon'; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="/salonsystem/css/common.css">
    <?php if(isset($pageCSS)): ?>
        <link rel="stylesheet" href="<?php echo $pageCSS; ?>">
    <?php endif; ?>
    <script>
        // Global flash message duration (milliseconds). Increase this if users miss the message.
        // Default: 10 seconds.
        window.FLASH_MESSAGE_DURATION_MS = window.FLASH_MESSAGE_DURATION_MS || 10000;
    </script>
</head>
<body>

<?php 

if (isset($useIndexNav) && $useIndexNav === true): 
    // Index.php Dedicated navigation bar
?>
    <nav class="nav">
        <div class="logo-section">
            <a href="/salonsystem/index.php" style="text-decoration: none; display: flex; align-items: center; gap: 0.5rem;">
                <div class="logo">
                    <svg width="60" height="60" viewBox="0 0 60 60" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M30 10C25 15 20 25 20 35C20 42 24 48 30 50C36 48 40 42 40 35C40 25 35 15 30 10Z" stroke="#9333ea" stroke-width="1.5" fill="none"/>
                        <path d="M15 30C12 35 10 40 15 45C20 48 25 45 27 40C28 35 27 30 25 25C22 22 17 25 15 30Z" stroke="#9333ea" stroke-width="1.5" fill="none"/>
                        <path d="M45 30C48 35 50 40 45 45C40 48 35 45 33 40C32 35 33 30 35 25C38 22 43 25 45 30Z" stroke="#9333ea" stroke-width="1.5" fill="none"/>
                        <circle cx="30" cy="15" r="1.5" fill="#9333ea"/>
                        <circle cx="30" cy="20" r="1" fill="#9333ea"/>
                    </svg>
                </div>
                <span class="brand-name">COSMOS SALON</span>
            </a>
        </div>
        <ul class="nav-links">
            <li><a href="#" class="active">HOME</a></li>
            <li><a href="#about">ABOUT</a></li>
            <li><a href="#treatments">SERVICES</a></li>
            <li><a href="#pricing">PRICING</a></li>
            <li><a href="#availability">AVAILABILITY</a></li>
            <li><a href="#contact">CONTACT</a></li>
            
            <?php if($currentUser): ?>
                <?php
                // Determine button text and link based on role
                $roleButtonText = 'MY BOOKINGS';
                $roleButtonLink = 'appointment/my_bookings.php';
                
                if ($currentUser['role'] === 'Admin') {
                    $roleButtonText = 'ADMIN';
                    $roleButtonLink = 'users_management/users.php';
                } elseif ($currentUser['role'] === 'Staff') {
                    $roleButtonText = 'SCHEDULE';
                    $roleButtonLink = 'schedule_service/schedule.php';
                }
                ?>
                <li><a href="<?php echo $roleButtonLink; ?>" class="btn-outline-nav"><?php echo $roleButtonText; ?></a></li>
                
                <li>
                    <div class="user-profile-nav" onclick="toggleProfileMenuNav()">
                        <div class="avatar">
                            <img src="/salonsystem/images/default-pic.jpg" alt="<?php echo htmlspecialchars($currentUser['name']); ?>" class="avatar-img">
                        </div>
                        <div class="profile-dropdown" id="profileDropdownNav">
                            <div class="dropdown-header">
                                <div class="dropdown-user-info">
                                    <strong><?php echo htmlspecialchars($currentUser['name']); ?></strong>
                                    <span><?php echo htmlspecialchars($currentUser['email']); ?></span>
                                </div>
                            </div>
                            <div class="dropdown-item" onclick="viewProfile()">
                                <i class="fas fa-user-circle"></i>
                                <span>My Profile</span>
                            </div>
                            <div class="dropdown-item" onclick="logout()">
                                <i class="fas fa-sign-out-alt"></i>
                                <span>Logout</span>
                            </div>
                        </div>
                    </div>
                </li>
            <?php else: ?>
                <li><a href="users_management/login.php" class="btn-filled">LOGIN</a></li>
            <?php endif; ?>
        </ul>
    </nav>

<?php elseif (!isset($hideCommonHeader) || $hideCommonHeader === false): 
?>
    <div class="header">
        <div class="header-left">
            <a href="/salonsystem/index.php" style="text-decoration: none; display: flex; align-items: center; gap: 0.5rem;">
                <svg width="60" height="60" viewBox="0 0 60 60" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M30 10C25 15 20 25 20 35C20 42 24 48 30 50C36 48 40 42 40 35C40 25 35 15 30 10Z" stroke="#9333ea" stroke-width="1.5" fill="none"/>
                    <path d="M15 30C12 35 10 40 15 45C20 48 25 45 27 40C28 35 27 30 25 25C22 22 17 25 15 30Z" stroke="#9333ea" stroke-width="1.5" fill="none"/>
                    <path d="M45 30C48 35 50 40 45 45C40 48 35 45 33 40C32 35 33 30 35 25C38 22 43 25 45 30Z" stroke="#9333ea" stroke-width="1.5" fill="none"/>
                    <circle cx="30" cy="15" r="1.5" fill="#9333ea"/>
                    <circle cx="30" cy="20" r="1" fill="#9333ea"/>
                </svg>
            </a>
            <div class="header-title">
                <span>COSMOS SALON</span>
                <?php if($currentUser): ?>
                    <p>Welcome, <?php echo htmlspecialchars($currentUser['name']); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <?php if($currentUser): ?>
            <div class="user-profile" onclick="toggleProfileMenu()">
                <div class="avatar">
                    <img src="/salonsystem/images/default-pic.jpg" alt="<?php echo htmlspecialchars($currentUser['name']); ?>" class="avatar-img">
                </div>
                <div class="profile-dropdown" id="profileDropdown">
                    <div class="dropdown-header">
                        <div class="dropdown-user-info">
                            <strong><?php echo htmlspecialchars($currentUser['name']); ?></strong>
                            <span><?php echo htmlspecialchars($currentUser['email']); ?></span>
                        </div>
                    </div>
                    <div class="dropdown-item" onclick="viewProfile()">
                        <i class="fas fa-user-circle"></i>
                        <span>My Profile</span>
                    </div>
                    <div class="dropdown-item" onclick="logout()">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<script>
// Toggle Profile Dropdown (For Standard Header)
function toggleProfileMenu() {
    const dropdown = document.getElementById('profileDropdown');
    if(dropdown) dropdown.classList.toggle('show');
}

// Toggle Profile Dropdown (For Index Nav)
function toggleProfileMenuNav() {
    const dropdown = document.getElementById('profileDropdownNav');
    if(dropdown) dropdown.classList.toggle('show');
}

// View Profile (Available globally)
function viewProfile() {
    const modal = document.getElementById('profileModal');
    if(modal) {
        modal.style.display = 'flex';
        // Hide standard dropdown if open
        const dropdown = document.getElementById('profileDropdown');
        if(dropdown) dropdown.classList.remove('show');
        // Hide nav dropdown (for index.php) if open
        const navDropdown = document.getElementById('profileDropdownNav');
        if(navDropdown) navDropdown.classList.remove('show');
    }
}

// Global Click Listener for Dropdowns
window.onclick = function(event) {
    // For Standard Header
    const dropdown = document.getElementById('profileDropdown');
    if (dropdown && !event.target.closest('.user-profile')) {
        dropdown.classList.remove('show');
    }
    
    // For Index Nav Header
    const navDropdown = document.getElementById('profileDropdownNav');
    if (navDropdown && !event.target.closest('.user-profile-nav')) {
        navDropdown.classList.remove('show');
    }
}

// Logout function
function logout() {
    if (confirm('Are you sure you want to logout?')) {
        window.location.href = '/salonsystem/users_management/logout.php'; 
    }
}
</script>

<?php if($currentUser): ?>
    <div id="profileModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>My Profile</h3>
                <button class="close-btn" onclick="closeProfileModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div id="profileViewMode">
                <div class="profile-info-display">
                    <div class="info-row">
                        <div class="info-label">
                            <i class="fas fa-user"></i>
                            <span>Name</span>
                        </div>
                        <div class="info-value" id="display-name"><?php echo htmlspecialchars($currentUser['name']); ?></div>
                    </div>

                    <div class="info-row">
                        <div class="info-label">
                            <i class="fas fa-envelope"></i>
                            <span>Email</span>
                        </div>
                        <div class="info-value" id="display-email"><?php echo htmlspecialchars($currentUser['email']); ?></div>
                    </div>

                    <div class="info-row">
                        <div class="info-label">
                            <i class="fas fa-phone"></i>
                            <span>Phone</span>
                        </div>
                        <div class="info-value" id="display-phone"><?php echo htmlspecialchars($currentUser['phone']); ?></div>
                    </div>

                    <div class="info-row">
                        <div class="info-label">
                            <i class="fas fa-shield-alt"></i>
                            <span>Role</span>
                        </div>
                        <div class="info-value">
                            <span class="role-badge <?php echo strtolower($currentUser['role']); ?>"><?php echo htmlspecialchars($currentUser['role']); ?></span>
                        </div>
                    </div>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn-save" onclick="enableEditMode()">
                        <i class="fas fa-edit"></i>
                        Edit Profile
                    </button>
                </div>
            </div>

            <div id="profileEditMode" style="display: none;">
                <form id="profileForm" onsubmit="saveProfile(event)">
                    <input type="hidden" name="user_id" value="<?php echo $currentUser['user_id']; ?>">
                    
                    <div class="form-group">
                        <label for="profile-name">Name <span class="required">*</span></label>
                        <input type="text" id="profile-name" name="name" value="<?php echo htmlspecialchars($currentUser['name']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="profile-email">Email <span class="required">*</span></label>
                        <input type="email" id="profile-email" name="email" value="<?php echo htmlspecialchars($currentUser['email']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit-phone">Phone <span class="required">*</span></label>
                        <input type="tel" id="edit-phone" name="phone" placeholder="012-34567890" pattern="^(01[0-9]-[0-9]{8,9})$" title="Please enter a valid phone number (e.g., 012-34567890)" required value="<?php echo htmlspecialchars($currentUser['phone']); ?>">
                        <small style="font-size: 0.85rem; color: #6b7280; margin-top: 5px; display: block;">
                            Format: 01X-XXXXXXXX
                        </small>
                    </div>

                    <div class="password-change-section">
                        <button type="button" class="btn-change-password" onclick="requestPasswordReset()">
                            <i class="fas fa-lock"></i> Request Password Reset
                        </button>
                        <p style="font-size: 0.85rem; color: #6b7280; margin: 0.5rem 0 0 0;">
                            A password reset link will be sent to <strong><?php echo htmlspecialchars($currentUser['email']); ?></strong>
                        </p>
                    </div>
                    
                    <div class="modal-actions">
                        <button type="button" class="btn-cancel" onclick="cancelEdit()">Cancel</button>
                        <button type="submit" class="btn-save">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

<style>
.btn-change-password {
    width: 100%;
    padding: 0.75rem 1rem;
    background: #f3f4f6;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    color: #4b5563;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.btn-change-password:hover {
    background: #e5e7eb;
    border-color: #9ca3af;
    color: #1f2937;
}
</style>

<script>
// Modal JS Logic (Shared)
function closeProfileModal() {
    document.getElementById('profileModal').style.display = 'none';
}

function enableEditMode() {
    document.getElementById('profileViewMode').style.display = 'none';
    document.getElementById('profileEditMode').style.display = 'block';
}

function cancelEdit() {
    document.getElementById('profileEditMode').style.display = 'none';
    document.getElementById('profileViewMode').style.display = 'block';
}

async function saveProfile(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    
    try {
        const response = await fetch('/salonsystem/users_management/profile.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Update display values
            const nameEl = document.getElementById('display-name');
            const emailEl = document.getElementById('display-email');
            const phoneEl = document.getElementById('display-phone');
            
            if(nameEl) nameEl.textContent = formData.get('name');
            if(emailEl) emailEl.textContent = formData.get('email');
            if(phoneEl) phoneEl.textContent = formData.get('phone');
            
            alert('Profile updated successfully!');
            cancelEdit();
            
            // Reload page to update header/nav
            location.reload();
        } else {
            alert(result.message || 'Failed to update profile');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('An error occurred while updating profile');
    }
}

async function requestPasswordReset() {
    if (!confirm('A password reset link will be sent to your email. Continue?')) {
        return;
    }
    
    try {
        const response = await fetch('/salonsystem/users_management/send_password_reset.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            }
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('Password reset link has been sent to your email!');
            closeProfileModal();
        } else {
            alert(result.message || 'Failed to send reset link');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
    }
}

// Close modal when clicking outside
window.addEventListener('click', function(event) {
    const modal = document.getElementById('profileModal');
    if (event.target === modal) {
        closeProfileModal();
    }
});
</script>
<?php endif; ?>
