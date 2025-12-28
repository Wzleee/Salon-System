<?php 
session_start();
require_once '../config.php';
require_once '../users_management/auth_check.php';
require_once 'validation_utils.php';
requireLogin(['Admin']);

$pageTitle = 'User Management - Cosmos Salon';
$pageCSS = '../css/users.css';
$currentPage = 'users';

$roleFilter = $_GET['role'] ?? 'all';
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
$perPage = 7;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $perPage;

$allowedRoleFilters = ['all', 'Admin', 'Staff', 'Customer'];
if (!in_array($roleFilter, $allowedRoleFilters, true)) {
    $roleFilter = 'all';
}

function buildPageLink($page, $roleFilter, $searchQuery) {
    $params = [];
    if ($roleFilter !== 'all') {
        $params['role'] = $roleFilter;
    }
    if ($searchQuery !== '') {
        $params['search'] = $searchQuery;
    }
    $params['page'] = $page;
    return '?' . http_build_query($params);
}

// handle delete user
if (isset($_POST['delete_user'])) {
    $user_id = (int)($_POST['user_id'] ?? 0);

    if ($user_id <= 0) {
        $_SESSION['error_message'] = 'Invalid user selected.';
        header("Location: users.php");
        exit;
    }

    try {
        // Check if user is a stylist with appointments
        $stylistStmt = $pdo->prepare("SELECT stylist_id FROM stylist WHERE user_id = ?");
        $stylistStmt->execute([$user_id]);
        $stylistId = $stylistStmt->fetchColumn();

        if ($stylistId) {
            $apptCountStmt = $pdo->prepare("SELECT COUNT(*) FROM appointment WHERE stylist_id = ?");
            $apptCountStmt->execute([$stylistId]);
            $apptCount = (int)$apptCountStmt->fetchColumn();

            if ($apptCount > 0) {
                $_SESSION['error_message'] = 'Cannot delete this staff account because they still have appointments. Please reassign or cancel those appointments first.';
                header("Location: users.php");
                exit;
            }
        }

        $stmt = $pdo->prepare("DELETE FROM user WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $_SESSION['success_message'] = 'User deleted successfully!';
    } catch (PDOException $e) {
        $_SESSION['error_message'] = 'Unable to delete user. Please try again or contact support.';
    }
    header("Location: users.php");
    exit;
}

// handle add user
if (isset($_POST['add_user'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $role = $_POST['role'];
    
    $errors = [];
    
    // user input validation
    $nameValidation = validateName($name);
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
    
    // check for existing email
    if (empty($errors) && emailExists($pdo, $email)) {
        $errors[] = 'Email already exists';
    }
    
    if (empty($errors)) {
        // generate random password for new user
        $random_password = bin2hex(random_bytes(4));
        $password_hash = password_hash($random_password, PASSWORD_DEFAULT);
        
        try {
            $stmt = $pdo->prepare("INSERT INTO user (name, email, phone, password_hash, role) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$name, $email, $phone, $password_hash, $role]);
            
            // Staff and Admin email notification
            if ($role === 'Staff' || $role === 'Admin') {
                
                $emailUtilityPath = '../appointment/email_utility.php'; 
                if (file_exists($emailUtilityPath)) {
                    require_once $emailUtilityPath;
                } else {
                    require_once '../email_utility.php';
                }
                
                // build email content
                $roleText = $role === 'Admin' ? 'Administrator' : 'Staff Member';
                $emailBody = "
                    <h2>Welcome to Cosmos Salon</h2>
                    <p>Hi " . htmlspecialchars($name) . ",</p>
                    <p>Your " . strtolower($roleText) . " account has been created successfully. Here are your login credentials:</p>
                    <div style='background: #f9fafb; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                        <p><strong>Email:</strong> " . htmlspecialchars($email) . "</p>
                        <p><strong>Temporary Password:</strong> <code style='background: #e5e7eb; padding: 5px 10px; border-radius: 4px; font-size: 16px;'>" . $random_password . "</code></p>
                    </div>
                    <div class='warning-box' style='background: #fef3c7; padding: 15px; border-radius: 8px; border-left: 4px solid #f59e0b;'>
                        <h3 style='margin-top: 0; color: #92400e;'>Important:</h3>
                        <p style='color: #92400e;'>You can keep this password or change it anytime from your profile or via Forgot Password.</p>
                    </div>
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='" . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . "/salonsystem/users_management/login.php' style='background: #7c3aed; color: white; padding: 12px 30px; text-decoration: none; border-radius: 8px; display: inline-block;'>Login Now</a>
                    </div>
                ";

                // send email
                if (function_exists('sendEmail')) {
                    sendEmail($email, "Welcome to Cosmos Salon - Your Account Details", $emailBody, $name);
                }
            }
            
            $_SESSION['success_message'] = 'User added successfully! Login credentials have been sent to their email.';
        } catch(PDOException $e) {
            $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
        }
    } else {
        $_SESSION['error_message'] = 'Validation errors: ' . implode(', ', $errors);
    }
    
    header("Location: users.php");
    exit;
}

// handle edit user
if (isset($_POST['edit_user'])) {
    $user_id = $_POST['user_id'];
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $role = $_POST['role'];
    
    $errors = [];
    
    // user input validation
    $nameValidation = validateName($name);
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
    
    // check for existing email
    if (empty($errors) && emailExists($pdo, $email, $user_id)) {
        $errors[] = 'Email is already in use by another user';
    }
    
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("UPDATE user SET name = ?, email = ?, phone = ?, role = ? WHERE user_id = ?");
            $stmt->execute([$name, $email, $phone, $role, $user_id]);
            $_SESSION['success_message'] = 'User updated successfully!';
        } catch(PDOException $e) {
            $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
        }
    } else {
        $_SESSION['error_message'] = 'Validation errors: ' . implode(', ', $errors);
    }
    
    header("Location: users.php");
    exit;
}

// fetch user statistics
$total_users = $pdo->query("SELECT COUNT(*) FROM user")->fetchColumn();
$total_admins = $pdo->query("SELECT COUNT(*) FROM user WHERE role = 'Admin'")->fetchColumn();
$total_staff = $pdo->query("SELECT COUNT(*) FROM user WHERE role = 'Staff'")->fetchColumn();
$total_customers = $pdo->query("SELECT COUNT(*) FROM user WHERE role = 'Customer'")->fetchColumn();

// Pagination + filters (server-side)
$filterSql = " WHERE 1=1";
$queryParams = [];

if ($roleFilter !== 'all') {
    $filterSql .= " AND role = :role";
    $queryParams[':role'] = $roleFilter;
}

if ($searchQuery !== '') {
    $filterSql .= " AND (name LIKE :search OR email LIKE :search OR phone LIKE :search OR user_id LIKE :search)";
    $queryParams[':search'] = '%' . $searchQuery . '%';
}

$countSql = "SELECT COUNT(*) FROM user" . $filterSql;
$dataSql = "SELECT * FROM user" . $filterSql . " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";

$countStmt = $pdo->prepare($countSql);
foreach ($queryParams as $key => $value) {
    $countStmt->bindValue($key, $value);
}
$countStmt->execute();
$totalRecords = (int)$countStmt->fetchColumn();
$totalPages = $totalRecords > 0 ? (int)ceil($totalRecords / $perPage) : 1;

if ($totalPages > 0 && $page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

$stmt = $pdo->prepare($dataSql);
foreach ($queryParams as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', (int)$perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
$stmt->execute();
$users = $stmt->fetchAll();

include '../head.php'; 
include '../nav.php';
include '../flash_message.php';
?>

<!-- Main Content -->
<div class="main-content">
    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon total">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-info">
                <h3>Total Users</h3>
                <p class="stat-number"><?= $total_users ?></p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon admin">
                <i class="fas fa-user-shield"></i>
            </div>
            <div class="stat-info">
                <h3>Admins</h3>
                <p class="stat-number"><?= $total_admins ?></p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon staff">
                <i class="fas fa-user-tie"></i>
            </div>
            <div class="stat-info">
                <h3>Staff</h3>
                <p class="stat-number"><?= $total_staff ?></p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon customer">
                <i class="fas fa-user"></i>
            </div>
            <div class="stat-info">
                <h3>Customers</h3>
                <p class="stat-number"><?= $total_customers ?></p>
            </div>
        </div>
    </div>
    
    <!-- User Management Section -->
    <div class="content-card">
        <div class="section-header">
            <div>
                <h2 class="section-title">User Management</h2>
                <p class="section-subtitle">Manage all users information.</p>
            </div>
            <button class="btn-add-user" onclick="addUser()">
                <i class="fas fa-plus"></i>
                Add User
            </button>
        </div>
        
        <!-- Search and Filter Bar -->
        <form class="controls-bar" method="GET" action="">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" name="search" placeholder="Search by name, email, or phone..." value="<?php echo htmlspecialchars($searchQuery); ?>">
            </div>
            
            <select id="roleFilter" name="role" class="role-filter" onchange="this.form.submit()">
                <option value="all" <?php echo $roleFilter === 'all' ? 'selected' : ''; ?>>All Roles</option>
                <option value="Admin" <?php echo $roleFilter === 'Admin' ? 'selected' : ''; ?>>Admin</option>
                <option value="Staff" <?php echo $roleFilter === 'Staff' ? 'selected' : ''; ?>>Staff</option>
                <option value="Customer" <?php echo $roleFilter === 'Customer' ? 'selected' : ''; ?>>Customer</option>
            </select>

            <button type="submit" class="search-btn" style="padding:0.75rem 1.5rem; background:#7c3aed; color:white; border:none; border-radius:10px; cursor:pointer; font-weight:600; display:flex; align-items:center; gap:0.5rem; white-space:nowrap;">
                <i class="fas fa-search"></i>
                Search
            </button>
        </form>
        
        <!-- Users Table -->
        <div class="table-container">
            <table class="users-table">
                <thead>
                    <tr>
                        <th>User ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Role</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="usersTableBody">
                    <?php foreach($users as $user): ?>
                    <tr data-role="<?= strtolower($user->role) ?>">
                        <td>#<?= $user->user_id ?></td>
                        <td>
                            <div class="user-info">
                                <span class="user-name"><?= htmlspecialchars($user->name) ?></span>
                            </div>
                        </td>
                        <td>
                            <div class="contact-cell">
                                <i class="far fa-envelope"></i> <?= htmlspecialchars($user->email) ?>
                            </div>
                        </td>
                        <td>
                            <div class="contact-cell">
                                <i class="fas fa-phone"></i> <?= htmlspecialchars($user->phone) ?>
                            </div>
                        </td>
                        <td><span class="role-badge <?= strtolower($user->role) ?>"><?= $user->role ?></span></td>
                        <td><?= date('M d, Y', strtotime($user->created_at)) ?></td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn-icon edit" 
        onclick='editUser({
            user_id: <?= $user->user_id ?>,
            name: <?= json_encode($user->name) ?>,
            email: <?= json_encode($user->email) ?>,
            phone: <?= json_encode($user->phone) ?>,
            role: <?= json_encode($user->role) ?>
        })' 
        title="Edit">
    <i class="fas fa-edit"></i>
</button>
                                <button class="btn-icon delete" onclick="deleteUser(<?= $user->user_id ?>)" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if (isset($totalPages) && $totalPages > 1): ?>
        <div class="pagination" style="display:flex; gap:8px; align-items:center; margin-top:16px;">
            <a class="page-btn <?php echo $page <= 1 ? 'disabled' : ''; ?>" 
               href="<?php echo $page > 1 ? buildPageLink($page - 1, $roleFilter, $searchQuery) : '#'; ?>"
               style="padding:8px 12px; border:1px solid #e5e7eb; border-radius:6px; text-decoration:none; color:#1f2937; <?php echo $page <= 1 ? 'pointer-events:none; opacity:0.5;' : ''; ?>">
               Prev
            </a>

            <div class="page-numbers" style="display:flex; gap:6px; flex-wrap:wrap;">
                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                    <a class="page-number <?php echo $p === $page ? 'active' : ''; ?>" 
                       href="<?php echo buildPageLink($p, $roleFilter, $searchQuery); ?>"
                       style="min-width:36px; text-align:center; padding:8px; border:1px solid <?php echo $p === $page ? '#7c3aed' : '#e5e7eb'; ?>; border-radius:6px; text-decoration:none; color:<?php echo $p === $page ? '#7c3aed' : '#1f2937'; ?>; background:<?php echo $p === $page ? '#ede9fe' : 'white'; ?>;">
                       <?php echo $p; ?>
                    </a>
                <?php endfor; ?>
            </div>

            <a class="page-btn <?php echo $page >= $totalPages ? 'disabled' : ''; ?>" 
               href="<?php echo $page < $totalPages ? buildPageLink($page + 1, $roleFilter, $searchQuery) : '#'; ?>"
               style="padding:8px 12px; border:1px solid #e5e7eb; border-radius:6px; text-decoration:none; color:#1f2937; <?php echo $page >= $totalPages ? 'pointer-events:none; opacity:0.5;' : ''; ?>">
               Next
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function filterUsers() {
    const searchInput = document.getElementById('searchInput').value.toLowerCase();
    const roleFilter = document.getElementById('roleFilter').value.toLowerCase();
    const rows = document.querySelectorAll('#usersTableBody tr');
    
    rows.forEach(row => {
        const name = row.querySelector('.user-name').textContent.toLowerCase();
        const email = row.cells[2].textContent.toLowerCase();
        const role = row.getAttribute('data-role');
        
        const matchesSearch = name.includes(searchInput) || email.includes(searchInput);
        const matchesRole = !roleFilter || roleFilter === 'all' || role === roleFilter;
        
        row.style.display = matchesSearch && matchesRole ? '' : 'none';
    });
}

function editUser(user) {
  const modal = document.getElementById('editUserModal');
  modal.querySelector('#edit-user-id').value = user.user_id;
  modal.querySelector('#edit-name').value = user.name;
  modal.querySelector('#edit-email').value = user.email;
  modal.querySelector('#edit-role').value = user.role;
  modal.querySelector('#edit-phone').value = user.phone || '';
  modal.style.display = 'flex';
}

function closeEditModal() {
    document.getElementById('editUserModal').style.display = 'none';
}

function addUser() {
    document.getElementById('addUserForm').reset();
    document.getElementById('addUserModal').style.display = 'flex';
}

function closeAddModal() {
    document.getElementById('addUserModal').style.display = 'none';
}

function deleteUser(id) {
    if(confirm('Are you sure you want to delete this user?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="delete_user" value="1">
            <input type="hidden" name="user_id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

window.onclick = function(event) {
    const editModal = document.getElementById('editUserModal');
    const addModal = document.getElementById('addUserModal');
    
    if (event.target === editModal) {
        closeEditModal();
    }
    if (event.target === addModal) {
        closeAddModal();
    }
}

</script>

<!-- Edit User Modal -->
<div id="editUserModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Edit User</h3>
            <button class="close-btn" onclick="closeEditModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form id="editUserForm" method="POST">
            <input type="hidden" name="edit_user" value="1">
            <input type="hidden" id="edit-user-id" name="user_id">
            
            <div class="form-group">
                <label for="edit-name">Name <span class="required">*</span></label>
                <input type="text" id="edit-name" name="name" required maxlength="50">
            </div>
            
            <div class="form-group">
                <label for="edit-email">Email <span class="required">*</span></label>
                <input type="email" id="edit-email" name="email" required maxlength="100">
            </div>
            
            <div class="form-group">
                <label for="edit-phone">Phone <span class="required">*</span></label>
                <input 
                    type="tel" 
                    id="edit-phone" 
                    name="phone" 
                    pattern="^(01[0-9]-[0-9]{8,9})$"
                    title="Please enter a valid phone number (e.g., 012-34567890)"
                    required
                >
                <small style="font-size: 0.85rem; color: #6b7280; margin-top: 5px; display: block;">
                    Format: 01X-XXXXXXXX
                </small>
            </div>
            
            <div class="form-group">
                <label for="edit-role">Role <span class="required">*</span></label>
                <select id="edit-role" name="role" required>
                    <option value="Admin">Admin</option>
                    <option value="Staff">Staff</option>
                    <option value="Customer">Customer</option>
                </select>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeEditModal()">Cancel</button>
                <button type="submit" class="btn-save">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Add User Modal -->
<div id="addUserModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Add User</h3>
            <button class="close-btn" onclick="closeAddModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form id="addUserForm" method="POST">
            <input type="hidden" name="add_user" value="1">

            <div class="form-group">
                <label for="add-name">Name <span class="required">*</span></label>
                <input type="text" id="add-name" name="name" required maxlength="50">
            </div>
            
            <div class="form-group">
                <label for="add-email">Email <span class="required">*</span></label>
                <input type="email" id="add-email" name="email" required maxlength="100">
            </div>
            
            <div class="form-group">
                <label for="add-phone">Phone <span class="required">*</span></label>
                <input 
                    type="tel" 
                    id="add-phone" 
                    name="phone" 
                    placeholder="012-34567890" 
                    pattern="^(01[0-9]-[0-9]{8,9})$"
                    title="Please enter a valid phone number (e.g., 012-34567890)"
                    required
                >
                <small style="font-size: 0.85rem; color: #6b7280; margin-top: 5px; display: block;">
                    Format: 01X-XXXXXXXX
                </small>
            </div>
            
            <div class="form-group">
                <label for="add-role">Role <span class="required">*</span></label>
                <select id="add-role" name="role" required>
                    <option value="">Select Role</option>
                    <option value="Admin">Admin</option>
                    <option value="Staff">Staff</option>
                </select>
            </div>
            
            <div style="background: #f0f9ff; padding: 12px; border-radius: 8px; border-left: 4px solid #3b82f6; margin-bottom: 15px;">
                <p style="margin: 0; font-size: 0.85rem; color: #1e40af;">
                    <i class="fas fa-info-circle"></i> 
                    <strong>Note:</strong> A temporary password will be generated and sent to their email. They can keep it or change it anytime from their profile or via Forgot Password.
                </p>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeAddModal()">Cancel</button>
                <button type="submit" class="btn-save">Add User</button>
            </div>
        </form>
    </div>
</div>

</body>
</html>
