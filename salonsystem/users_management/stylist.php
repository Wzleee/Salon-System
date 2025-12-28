<?php 
session_start();
require_once '../config.php';
require_once '../users_management/auth_check.php';
requireLogin(['Admin']);


$pageTitle = 'Stylist Management - Cosmos Salon';
$pageCSS = '../css/stylist.css';
$currentPage = 'stylist';

$worktypeFilter = $_GET['work_type'] ?? 'all';
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
$perPage = 7;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $perPage;

$allowedWorktypes = ['all', 'full-time', 'part-time'];
if (!in_array($worktypeFilter, $allowedWorktypes, true)) {
    $worktypeFilter = 'all';
}

function buildPageLink($page, $worktypeFilter, $searchQuery) {
    $params = [];
    if ($worktypeFilter !== 'all') {
        $params['work_type'] = $worktypeFilter;
    }
    if ($searchQuery !== '') {
        $params['search'] = $searchQuery;
    }
    $params['page'] = $page;
    return '?' . http_build_query($params);
}

// Delete Stylist
if (isset($_POST['delete_stylist'])) {
    $stylist_id = (int)($_POST['stylist_id'] ?? 0);

    if ($stylist_id <= 0) {
        $_SESSION['error_message'] = 'Invalid stylist selected.';
        header("Location: stylist.php");
        exit;
    }

    try {
        // Block deletion if stylist still has appointments
        $apptCountStmt = $pdo->prepare("SELECT COUNT(*) FROM appointment WHERE stylist_id = ?");
        $apptCountStmt->execute([$stylist_id]);
        $apptCount = (int)$apptCountStmt->fetchColumn();

        if ($apptCount > 0) {
            $_SESSION['error_message'] = 'Cannot delete stylist with existing appointments. Please reassign or cancel their appointments first.';
            header("Location: stylist.php");
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM stylist WHERE stylist_id = ?");
        $stmt->execute([$stylist_id]);
        $_SESSION['success_message'] = 'Stylist deleted successfully!';
    } catch (PDOException $e) {
        $_SESSION['error_message'] = 'Unable to delete stylist. Please try again or contact support.';
    }
    header("Location: stylist.php");
    exit;
}

// Add Stylist
if (isset($_POST['add_stylist'])) {
    $user_id = $_POST['user_id'];
    $specialization = $_POST['specialization'];
    $qualifications = $_POST['qualifications'];
    $experience_years = $_POST['experience_years'];
    $work_type = $_POST['work_type'];
    $address = $_POST['address'];

    // Guard: avoid duplicate stylist entry for the same user_id
    $existsStmt = $pdo->prepare("SELECT 1 FROM stylist WHERE user_id = ?");
    $existsStmt->execute([$user_id]);
    if ($existsStmt->fetch()) {
        $_SESSION['error_message'] = 'This user is already a stylist.';
        header("Location: stylist.php");
        exit;
    }
    
    // Handle photo upload (base64)
    $photo = null;
    if (!empty($_POST['photo_base64'])) {
        $base64_image = $_POST['photo_base64'];
        
        // Convert base64 image to file and save
        if (preg_match('/^data:image\/(\w+);base64,/', $base64_image, $type)) {
            $base64_image = substr($base64_image, strpos($base64_image, ',') + 1);
            $type = strtolower($type[1]); // jpg, png, gif
            
            $base64_image = base64_decode($base64_image);
            
            // Save image to disk
            $filename = 'stylist_' . time() . '_' . rand(1000, 9999) . '.' . $type;
            $filepath = $_SERVER['DOCUMENT_ROOT'] . '/salonsystem/images/' . $filename;
            
            if (file_put_contents($filepath, $base64_image)) {
                $photo = '/salonsystem/images/' . $filename;
            }
        }
    }
    
    try {
        $pdo->beginTransaction(); //add
        $stmt = $pdo->prepare("INSERT INTO stylist (user_id, photo, specialization, qualifications, experience_years, work_type, address) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $photo, $specialization, $qualifications, $experience_years, $work_type, $address]);
        $stylistId = $pdo->lastInsertId();//add
        // Use current business hours (table has no override_date column in this schema)
        $hours = $pdo->query("SELECT day_of_week, opening_time, closing_time, is_closed 
                              FROM businesshours")->fetchAll(PDO::FETCH_ASSOC); //add

        $ins = $pdo->prepare(" 
            INSERT INTO schedule (stylist_id, day_of_week, schedule_scope, start_time, end_time, break_start, break_end, is_available)
            VALUES (:sid, :dow, 'weekly', :start, :end, :bstart, :bend, :avail)
        ");//add

        foreach ($hours as $h) { //add
            $isOpen = ((int)$h['is_closed'] === 0) && ($h['opening_time'] !== $h['closing_time']);
            $bStart = $bEnd = null;
            if ($isOpen &&
                strtotime($h['opening_time']) <= strtotime('12:00:00') &&
                strtotime($h['closing_time']) >= strtotime('13:00:00')) {
                $bStart = '12:00:00';
                $bEnd   = '13:00:00';
            }
            $ins->execute([ //add
                ':sid'    => $stylistId,
                ':dow'    => $h['day_of_week'],
                ':start'  => $isOpen ? $h['opening_time'] : null,
                ':end'    => $isOpen ? $h['closing_time'] : null,
                ':bstart' => $bStart,
                ':bend'   => $bEnd,
                ':avail'  => $isOpen ? 1 : 0,
            ]);
        }

        $pdo->commit(); //add
        $_SESSION['success_message'] = 'Stylist added successfully!';
    } catch(PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error_message'] = 'Error adding stylist: ' . $e->getMessage();
    }
    
    header("Location: stylist.php");
    exit;
}

// Edit Stylist
if (isset($_POST['edit_stylist'])) {
    $stylist_id = $_POST['stylist_id'];
    $specialization = $_POST['specialization'];
    $qualifications = $_POST['qualifications'];
    $experience_years = $_POST['experience_years'];
    $work_type = $_POST['work_type'];
    $address = $_POST['address'];
    
    $photo = $_POST['old_photo']; 

    $base64_input = isset($_POST['photo_base64']) ? $_POST['photo_base64'] : '';

    
    
    if ($base64_input === 'default') {
        
        if ($photo && strpos($photo, 'default') === false && file_exists($_SERVER['DOCUMENT_ROOT'] . $photo)) {
            unlink($_SERVER['DOCUMENT_ROOT'] . $photo);
        }
        $photo = null; 
    }

    elseif (!empty($base64_input)) {
        $base64_image = $base64_input;
        

        if (preg_match('/^data:image\/(\w+);base64,/', $base64_image, $type)) {
            $base64_image = substr($base64_image, strpos($base64_image, ',') + 1);
            $type = strtolower($type[1]);
            
            $base64_image = base64_decode($base64_image);
            
   
            $filename = 'stylist_' . time() . '_' . rand(1000, 9999) . '.' . $type;
            $filepath = $_SERVER['DOCUMENT_ROOT'] . '/salonsystem/images/' . $filename;
            

            if (file_put_contents($filepath, $base64_image)) {

                if ($photo && strpos($photo, 'default') === false && file_exists($_SERVER['DOCUMENT_ROOT'] . $photo)) {
                    unlink($_SERVER['DOCUMENT_ROOT'] . $photo);
                }
                $photo = '/salonsystem/images/' . $filename;
            }
        }
    }

    $stmt = $pdo->prepare("UPDATE stylist SET photo = ?, specialization = ?, qualifications = ?, experience_years = ?, work_type = ?, address = ? WHERE stylist_id = ?");
    $stmt->execute([$photo, $specialization, $qualifications, $experience_years, $work_type, $address, $stylist_id]);
    
    $_SESSION['success_message'] = 'Stylist updated successfully!';
    header("Location: stylist.php");
    exit;
}


$total_stylists = $pdo->query("SELECT COUNT(*) FROM stylist")->fetchColumn();
$total_fulltime = $pdo->query("SELECT COUNT(*) FROM stylist WHERE work_type = 'full-time'")->fetchColumn();
$total_parttime = $pdo->query("SELECT COUNT(*) FROM stylist WHERE work_type = 'part-time'")->fetchColumn();
$total_active = $total_stylists; 


// Pagination + filters (server-side)
$filterSql = " WHERE 1=1";
$queryParams = [];

if ($worktypeFilter !== 'all') {
    $filterSql .= " AND s.work_type = :work_type";
    $queryParams[':work_type'] = $worktypeFilter;
}

if ($searchQuery !== '') {
    $filterSql .= " AND (u.name LIKE :search OR u.email LIKE :search OR s.specialization LIKE :search)";
    $queryParams[':search'] = '%' . $searchQuery . '%';
}

$countSql = "SELECT COUNT(*) FROM stylist s INNER JOIN user u ON s.user_id = u.user_id" . $filterSql;
$dataSql = "SELECT s.*, u.name, u.email, u.phone
    FROM stylist s
    INNER JOIN user u ON s.user_id = u.user_id" . $filterSql . "
    ORDER BY s.created_at DESC
    LIMIT :limit OFFSET :offset";

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
$stylists = $stmt->fetchAll();

$staff_users = $pdo->query("
    SELECT u.user_id, u.name, u.email 
    FROM user u 
    WHERE u.role = 'Staff' 
    AND u.user_id NOT IN (SELECT user_id FROM stylist)
    ORDER BY u.name
")->fetchAll();

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
                <i class="fas fa-user-tie"></i>
            </div>
            <div class="stat-info">
                <h3>Total Stylists</h3>
                <p class="stat-number"><?= $total_stylists ?></p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon fulltime">
                <i class="fas fa-briefcase"></i>
            </div>
            <div class="stat-info">
                <h3>Full-time</h3>
                <p class="stat-number"><?= $total_fulltime ?></p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon parttime">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-info">
                <h3>Part-time</h3>
                <p class="stat-number"><?= $total_parttime ?></p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon active">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-info">
                <h3>Active</h3>
                <p class="stat-number"><?= $total_active ?></p>
            </div>
        </div>
    </div>
    
    <!-- Stylist Management Section -->
    <div class="content-card">
        <div class="section-header">
            <div>
                <h2 class="section-title">Stylist Management</h2>
                <p class="section-subtitle">Manage salon staff details.</p>
            </div>
            <button class="btn-add-stylist" onclick="addStylist()">
                <i class="fas fa-plus"></i>
                Add Stylist
            </button>
        </div>
        
        <!-- Search Bar -->
        <form class="controls-bar" method="GET" action="">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" name="search" placeholder="Search by name, email, or specialization..." value="<?php echo htmlspecialchars($searchQuery); ?>">
            </div>

            <select id="worktypeFilter" name="work_type" class="worktype-filter" onchange="this.form.submit()">
                <option value="all" <?php echo $worktypeFilter === 'all' ? 'selected' : ''; ?>>All Work Type</option>
                <option value="full-time" <?php echo $worktypeFilter === 'full-time' ? 'selected' : ''; ?>>Full-Time</option>
                <option value="part-time" <?php echo $worktypeFilter === 'part-time' ? 'selected' : ''; ?>>Part-Time</option>
            </select>

            <button type="submit" class="search-btn" style="padding:0.75rem 1.5rem; background:#7c3aed; color:white; border:none; border-radius:10px; cursor:pointer; font-weight:600; display:flex; align-items:center; gap:0.5rem; white-space:nowrap;">
                <i class="fas fa-search"></i>
                Search
            </button>
        </form>
        
        <!-- Stylists Table -->
        <div class="table-container">
            <table class="stylists-table">
                <thead>
                    <tr>
                        <th>Stylist ID</th>
                        <th>Name</th>
                        <th>Specialization</th>
                        <th>Qualifications</th>
                        <th>Experience</th>
                        <th>Address</th>
                        <th>Work Type</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="stylistsTableBody">
                    <?php foreach($stylists as $stylist): ?>
                    <tr data-worktype="<?= htmlspecialchars($stylist->work_type) ?>">
                        <td>#<?= $stylist->stylist_id ?></td>
                        <td>
                            <div class="stylist-info">
                                <img src="<?= $stylist->photo ? htmlspecialchars($stylist->photo) : '/salonsystem/images/default-pic.jpg' ?>" 
                                     alt="<?= htmlspecialchars($stylist->name) ?>" 
                                     class="stylist-photo"
                                     onerror="this.src='/salonsystem/images/default-pic.jpg'">
                                <span class="stylist-name"><?= htmlspecialchars($stylist->name) ?></span>
                            </div>
                        </td>
                        <td><?= htmlspecialchars($stylist->specialization) ?></td>
                        <td><?= htmlspecialchars($stylist->qualifications ?: 'N/A') ?></td>
                        <td><?= $stylist->experience_years ?> years</td>
                        <td>
                            <?php if($stylist->address): ?>
                                <?php $address = htmlspecialchars($stylist->address); ?>
                                <div class="address-cell">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <span><?= $address ?></span>
                                </div>
                            <?php else: ?>
                                N/A
                            <?php endif; ?>
                        </td>

                        <td><span class="work-type-badge <?= $stylist->work_type ?>"><?= ucfirst($stylist->work_type) ?></span></td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn-icon edit" onclick='editStylist(<?= json_encode($stylist) ?>)' title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn-icon delete" onclick="deleteStylist(<?= $stylist->stylist_id ?>)" title="Delete">
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
               href="<?php echo $page > 1 ? buildPageLink($page - 1, $worktypeFilter, $searchQuery) : '#'; ?>"
               style="padding:8px 12px; border:1px solid #e5e7eb; border-radius:6px; text-decoration:none; color:#1f2937; <?php echo $page <= 1 ? 'pointer-events:none; opacity:0.5;' : ''; ?>">
               Prev
            </a>

            <div class="page-numbers" style="display:flex; gap:6px; flex-wrap:wrap;">
                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                    <a class="page-number <?php echo $p === $page ? 'active' : ''; ?>" 
                       href="<?php echo buildPageLink($p, $worktypeFilter, $searchQuery); ?>"
                       style="min-width:36px; text-align:center; padding:8px; border:1px solid <?php echo $p === $page ? '#7c3aed' : '#e5e7eb'; ?>; border-radius:6px; text-decoration:none; color:<?php echo $p === $page ? '#7c3aed' : '#1f2937'; ?>; background:<?php echo $p === $page ? '#ede9fe' : 'white'; ?>;">
                       <?php echo $p; ?>
                    </a>
                <?php endfor; ?>
            </div>

            <a class="page-btn <?php echo $page >= $totalPages ? 'disabled' : ''; ?>" 
               href="<?php echo $page < $totalPages ? buildPageLink($page + 1, $worktypeFilter, $searchQuery) : '#'; ?>"
               style="padding:8px 12px; border:1px solid #e5e7eb; border-radius:6px; text-decoration:none; color:#1f2937; <?php echo $page >= $totalPages ? 'pointer-events:none; opacity:0.5;' : ''; ?>">
               Next
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Edit Stylist Modal -->
<div id="editStylistModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Edit Stylist</h3>
            <button class="close-btn" onclick="closeEditModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="edit_stylist" value="1">
            <input type="hidden" id="edit-stylist-id" name="stylist_id">
            <input type="hidden" id="edit-old-photo" name="old_photo">
            <input type="hidden" id="edit-photo-base64" name="photo_base64">
            
            <!-- Photo Upload Section -->
            <div class="photo-upload-section">
                <div class="photo-preview-container">
                    <img id="edit-photo-preview" src="/salonsystem/images/default-pic.jpg" alt="Stylist Photo" class="photo-preview">
                    <label for="edit-photo-input" class="photo-edit-btn" title="Change Photo">
                        <i class="fas fa-edit"></i>
                    </label>
                    <input type="file" id="edit-photo-input" accept="image/png, image/jpeg, image/jpg, image/gif, image/webp" style="display: none;" onchange="handleEditPhotoUpload(event)">
                </div>
                <button type="button" class="btn-default-photo" onclick="useDefaultPhoto('edit')">
                    <i class="fas fa-undo"></i>
                    Use Default
                </button>
            </div>
            
            <div class="form-group">
                <label>Name</label>
                <input type="text" id="edit-name-display" disabled style="background: #f5f5f5; cursor: not-allowed;">
                <small style="color: #666; font-size: 12px;">
                    <i class="fas fa-info-circle"></i> Name is linked to user account. Edit in User Management.
                </small>
            </div>
            
            <div class="form-group">
                <label for="edit-specialization">Specialization <span class="required">*</span></label>
                <input type="text" id="edit-specialization" name="specialization" placeholder="e.g., Hair Coloring & Styling" required>
            </div>
            
            <div class="form-group">
                <label for="edit-qualifications">Qualifications <span class="optional">(Optional)</span></label>
                <input type="text" id="edit-qualifications" name="qualifications" placeholder="e.g., Certified Hair Colorist">
            </div>
            
            <div class="form-group">
                <label for="edit-experience">Experience (years) <span class="required">*</span></label>
                <input type="number" id="edit-experience" name="experience_years" min="0" required>
            </div>

            <div class="form-group">
                <label for="edit-address">Address <span class="optional">(Optional)</span></label>
                <input type="text" id="edit-address" name="address" placeholder="Enter address">
            </div>
            
            <div class="form-group">
                <label for="edit-work-type">Work Type <span class="required">*</span></label>
                <select id="edit-work-type" name="work_type" required>
                    <option value="full-time">Full-time</option>
                    <option value="part-time">Part-time</option>
                </select>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeEditModal()">Cancel</button>
                <button type="submit" class="btn-save">Update Stylist</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Stylist Modal -->
<div id="addStylistModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Add New Stylist</h3>
            <button class="close-btn" onclick="closeAddModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form id="addStylistForm" method="POST">
            <input type="hidden" name="add_stylist" value="1">
            <input type="hidden" id="add-photo-base64" name="photo_base64">
            
            <!-- Photo Upload Section -->
            <div class="photo-upload-section">
                <div class="photo-preview-container">
                    <img id="add-photo-preview" src="/salonsystem/images/default-pic.jpg" alt="Stylist Photo" class="photo-preview">
                    <label for="add-photo-input" class="photo-edit-btn" title="Upload Photo">
                        <i class="fas fa-edit"></i>
                    </label>
                    <input type="file" id="add-photo-input" accept="image/png, image/jpeg, image/jpg, image/gif, image/webp" style="display: none;" onchange="handleAddPhotoUpload(event)">
                </div>
                <button type="button" class="btn-default-photo" onclick="useDefaultPhoto('add')">
                    <i class="fas fa-undo"></i>
                    Use Default
                </button>
            </div>
            
            <div class="form-group">
                <label for="add-user-id">Select Staff Member <span class="required">*</span></label>
                <select id="add-user-id" name="user_id" required>
                    <option value="">Choose a staff member...</option>
                    <?php foreach($staff_users as $user): ?>
                        <option value="<?= $user->user_id ?>"><?= htmlspecialchars($user->name) ?> (<?= htmlspecialchars($user->email) ?>)</option>
                    <?php endforeach; ?>
                </select>
                <small style="color: #666; font-size: 12px;">
                    <i class="fas fa-info-circle"></i> Only staff members who are not yet stylists are shown.
                </small>
            </div>
            
            <div class="form-group">
                <label for="add-specialization">Specialization <span class="required">*</span></label>
                <input type="text" id="add-specialization" name="specialization" placeholder="e.g., Hair Coloring & Styling" required>
            </div>
            
            <div class="form-group">
                <label for="add-qualifications">Qualifications <span class="optional">(Optional)</span></label>
                <input type="text" id="add-qualifications" name="qualifications" placeholder="e.g., Certified Hair Colorist">
            </div>
            
            <div class="form-group">
                <label for="add-experience">Experience (years) <span class="required">*</span></label>
                <input type="number" id="add-experience" name="experience_years" min="0" placeholder="0" required>
            </div>

            <div class="form-group">
                <label for="add-address">Address <span class="optional">(Optional)</span></label>
                <input type="text" id="add-address" name="address" placeholder="Enter address">
            </div>
            
            <div class="form-group">
                <label for="add-work-type">Work Type <span class="required">*</span></label>
                <select id="add-work-type" name="work_type" required>
                    <option value="">Select Work Type</option>
                    <option value="full-time">Full-time</option>
                    <option value="part-time">Part-time</option>
                </select>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeAddModal()">Cancel</button>
                <button type="submit" class="btn-save">Add Stylist</button>
            </div>
        </form>
    </div>
</div>

<script>
function filterStylists() {
    const searchInput = document.getElementById('searchInput').value.toLowerCase();
    const worktypeFilter = document.getElementById('worktypeFilter').value; 
    const rows = document.querySelectorAll('#stylistsTableBody tr');

    rows.forEach(row => {
        const name = row.querySelector('.stylist-name').textContent.toLowerCase();
        const specialization = row.cells[2].textContent.toLowerCase();
        const rowWorkType = row.getAttribute('data-worktype');

        const matchesSearch = name.includes(searchInput) || specialization.includes(searchInput);
        const matchesWorkType = worktypeFilter === "" || worktypeFilter === "all" || rowWorkType === worktypeFilter;

        row.style.display = (matchesSearch && matchesWorkType) ? "" : "none";
    });
}

function editStylist(stylist) {
    document.getElementById('edit-stylist-id').value = stylist.stylist_id;
    document.getElementById('edit-name-display').value = stylist.name;
    document.getElementById('edit-old-photo').value = stylist.photo || '';
    document.getElementById('edit-photo-preview').src = stylist.photo || '/salonsystem/images/default-pic.jpg';
    document.getElementById('edit-photo-base64').value = '';
    document.getElementById('edit-specialization').value = stylist.specialization;
    document.getElementById('edit-qualifications').value = stylist.qualifications || '';
    document.getElementById('edit-experience').value = stylist.experience_years;
    document.getElementById('edit-address').value = stylist.address || '';
    document.getElementById('edit-work-type').value = stylist.work_type;
    
    document.getElementById('editStylistModal').style.display = 'flex';
}

function handleEditPhotoUpload(event) {
    const file = event.target.files[0];
    if (file) {
        const validTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'image/webp'];
        if (!validTypes.includes(file.type)) {
            alert('Please upload a valid image file (PNG, JPEG, JPG, GIF, WEBP)');
            return;
        }
        
        if (file.size > 5 * 1024 * 1024) {
            alert('File size must be less than 5MB');
            return;
        }
        
        const reader = new FileReader();
        reader.onload = function(e) {
            const img = new Image();
            img.onload = function() {
                const canvas = document.createElement('canvas');
                const size = Math.min(img.width, img.height);
                canvas.width = size;
                canvas.height = size;
                
                const ctx = canvas.getContext('2d');
                const offsetX = (img.width - size) / 2;
                const offsetY = (img.height - size) / 2;
                
                ctx.drawImage(img, offsetX, offsetY, size, size, 0, 0, size, size);
                
                const croppedImage = canvas.toDataURL(file.type);
                document.getElementById('edit-photo-preview').src = croppedImage;
                document.getElementById('edit-photo-base64').value = croppedImage;
            };
            img.src = e.target.result;
        };
        reader.readAsDataURL(file);
    }
}

function handleAddPhotoUpload(event) {
    const file = event.target.files[0];
    if (file) {
        const validTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'image/webp'];
        if (!validTypes.includes(file.type)) {
            alert('Please upload a valid image file (PNG, JPEG, JPG, GIF, WEBP)');
            return;
        }
        
        if (file.size > 5 * 1024 * 1024) {
            alert('File size must be less than 5MB');
            return;
        }
        
        const reader = new FileReader();
        reader.onload = function(e) {
            const img = new Image();
            img.onload = function() {
                const canvas = document.createElement('canvas');
                const size = Math.min(img.width, img.height);
                canvas.width = size;
                canvas.height = size;
                
                const ctx = canvas.getContext('2d');
                const offsetX = (img.width - size) / 2;
                const offsetY = (img.height - size) / 2;
                
                ctx.drawImage(img, offsetX, offsetY, size, size, 0, 0, size, size);
                
                const croppedImage = canvas.toDataURL(file.type);
                document.getElementById('add-photo-preview').src = croppedImage;
                document.getElementById('add-photo-base64').value = croppedImage;
            };
            img.src = e.target.result;
        };
        reader.readAsDataURL(file);
    }
}

function useDefaultPhoto(type) {
    const defaultPhoto = '/salonsystem/images/default-pic.jpg';
    if (type === 'edit') {
        document.getElementById('edit-photo-preview').src = defaultPhoto;
        
        document.getElementById('edit-photo-base64').value = 'default'; 
        document.getElementById('edit-photo-input').value = '';
    } else if (type === 'add') {
        document.getElementById('add-photo-preview').src = defaultPhoto;
        document.getElementById('add-photo-base64').value = '';
        document.getElementById('add-photo-input').value = '';
    }
}

function closeEditModal() {
    document.getElementById('editStylistModal').style.display = 'none';
}

function addStylist() {
    document.getElementById('addStylistForm').reset();
    document.getElementById('add-photo-preview').src = '/salonsystem/images/default-pic.jpg';
    document.getElementById('add-photo-base64').value = '';
    document.getElementById('addStylistModal').style.display = 'flex';
}

function closeAddModal() {
    document.getElementById('addStylistModal').style.display = 'none';
}

function deleteStylist(id) {
    if(confirm('Are you sure you want to delete this stylist?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="delete_stylist" value="1">
            <input type="hidden" name="stylist_id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

window.onclick = function(event) {
    const editModal = document.getElementById('editStylistModal');
    const addModal = document.getElementById('addStylistModal');
    
    if (event.target === editModal) {
        closeEditModal();
    }
    if (event.target === addModal) {
        closeAddModal();
    }
}

</script>

</body>
</html>
