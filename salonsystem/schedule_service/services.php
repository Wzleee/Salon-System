<?php
require_once '../config.php';
require_once '../users_management/auth_check.php';
requireLogin(['Admin']);

$pageTitle = 'Service Management - Cosmos Salon';
$pageCSS = '../css/services.css';
$currentPage = 'services';

$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
$categoryFilter = isset($_GET['category']) ? max(0, (int)$_GET['category']) : 0;
$perPage = 7;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $perPage;
$minServiceDurationMinutes = 30;
$minServicePriceRm = 20.0;

function buildPageLink($page, $categoryFilter, $searchTerm) {
    $params = [];
    if ($categoryFilter > 0) {
        $params['category'] = $categoryFilter;
    }
    if ($searchTerm !== '') {
        $params['search'] = $searchTerm;
    }
    $params['page'] = $page;
    return '?' . http_build_query($params);
}

// Add Category
if (isset($_POST['add_category'])) {
    $category_name = trim($_POST['category_name'] ?? '');
    $category_name = preg_replace('/\s+/', ' ', $category_name);
    
    if (!empty($category_name)) {
        try {
            $existingStmt = $pdo->query("SELECT category_name FROM category");
            $existingNames = $existingStmt->fetchAll(PDO::FETCH_COLUMN);
            $normalizedNew = strtolower($category_name);
            $duplicateFound = false;
            foreach ($existingNames as $existingName) {
                $existingName = preg_replace('/\s+/', ' ', trim((string)$existingName));
                if ($existingName === '') {
                    continue;
                }
                if (strtolower($existingName) === $normalizedNew) {
                    $duplicateFound = true;
                    break;
                }
            }

            if ($duplicateFound) {
                $_SESSION['error_message'] = "category '$category_name' already exists!";
            } else {
                $sql = "INSERT INTO category (category_name, status) VALUES (:name, 'Active')";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':name' => $category_name]);

                $_SESSION['success_message'] = "Category '$category_name' added successfully.";
            }
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Error adding category: " . $e->getMessage();
        }
    } else {
        $_SESSION['error_message'] = "Category name cannot be empty.";
    }
    header("Location: services.php");
    exit;
}


//Add Service
if (isset($_POST['add_service'])) {
    $service_name = trim($_POST['service_name'] ?? '');
    $duration_minutes = filter_var($_POST['duration_minutes'] ?? null, FILTER_VALIDATE_INT);
    $price = filter_var($_POST['price'] ?? null, FILTER_VALIDATE_FLOAT);
    $description = trim($_POST['description'] ?? '');
    $category_id = filter_var($_POST['category_id'] ?? null, FILTER_VALIDATE_INT);
    $status = $_POST['status'] ?? 'Active';

    $errors = [];
    if ($service_name === '') {
        $errors[] = "Service name cannot be empty.";
    }
    if ($category_id === false || $category_id <= 0) {
        $errors[] = "Please select a category.";
    }
    if ($duration_minutes === false || $duration_minutes < $minServiceDurationMinutes) {
        $errors[] = "Duration must be at least {$minServiceDurationMinutes} minutes.";
    }
    if ($price === false || $price < $minServicePriceRm) {
        $errors[] = "Price must be at least RM{$minServicePriceRm}.";
    }
    if (!in_array($status, ['Active', 'Inactive'], true)) {
        $errors[] = "Invalid status selected.";
    }

    if (!empty($errors)) {
        $_SESSION['error_message'] = implode(' ', $errors);
        header("Location: services.php");
        exit;
    }

    try{
        // Prevent duplicate service names (case-insensitive)
        $dupCheck = $pdo->prepare("SELECT COUNT(*) FROM service WHERE LOWER(service_name) = LOWER(:name)");
        $dupCheck->execute([':name' => $service_name]);
        if ((int)$dupCheck->fetchColumn() > 0) {
            $_SESSION['error_message'] = "A service named '{$service_name}' already exists. Please use a different name.";
            header("Location: services.php");
            exit;
        }

        $sql = "INSERT INTO service (service_name, duration_minutes, price, description, category_id, status) 
                VALUES (:name, :duration, :price, :description, :category_id, :status)";
    
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':name' => $service_name,
            ':duration' => $duration_minutes,
            ':price' => $price,
            ':description' => $description,
            ':category_id' => $category_id,
            ':status' => $status
        ]);
        $_SESSION['success_message'] = "Service '$service_name' added successfully.";
    } catch (PDOException $e) {
            $message = "Error adding service. Please try again.";
            if ((int)$e->getCode() === 23000 || str_contains($e->getMessage(), '1062')) {
                $message = "A service named '{$service_name}' already exists. Please use a different name.";
            }
            $_SESSION['error_message'] = $message;
    }
    header("Location: services.php");
    exit;
}

// Update Service
if (isset($_POST['update_service'])) {
    $service_id = filter_var($_POST['service_id'] ?? null, FILTER_VALIDATE_INT);
    $service_name = trim($_POST['service_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $duration_minutes = filter_var($_POST['duration_minutes'] ?? null, FILTER_VALIDATE_INT);
    $category_id = filter_var($_POST['category_id'] ?? null, FILTER_VALIDATE_INT);
    $price = filter_var($_POST['price'] ?? null, FILTER_VALIDATE_FLOAT);
    $status = $_POST['status'] ?? 'Active';

    $errors = [];
    if ($service_id === false || $service_id <= 0) {
        $errors[] = "Invalid service selected.";
    }
    if ($service_name === '') {
        $errors[] = "Service name cannot be empty.";
    }
    if ($category_id === false || $category_id <= 0) {
        $errors[] = "Please select a category.";
    }
    if ($duration_minutes === false || $duration_minutes < $minServiceDurationMinutes) {
        $errors[] = "Duration must be at least {$minServiceDurationMinutes} minutes.";
    }
    if ($price === false || $price < $minServicePriceRm) {
        $errors[] = "Price must be at least RM{$minServicePriceRm}.";
    }
    if (!in_array($status, ['Active', 'Inactive'], true)) {
        $errors[] = "Invalid status selected.";
    }

    if (!empty($errors)) {
        $_SESSION['error_message'] = implode(' ', $errors);
        header("Location: services.php");
        exit;
    }
    
    try {
        $sql = "UPDATE service 
                SET service_name = :name, description = :desc, duration_minutes = :duration, 
                    category_id = :category_id, price = :price, status = :status 
                WHERE service_id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':name' => $service_name,
            ':desc' => $description,
            ':duration' => $duration_minutes,
            ':category_id' => $category_id,
            ':price' => $price,
            ':status' => $status,
            ':id' => $service_id
        ]);
        $_SESSION['success_message'] = "Service updated successfully!";
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
    }
    header("Location: services.php");
    exit;
}

// Delete Service
if (isset($_GET['delete_id'])) {
    $service_id = (int)$_GET['delete_id'];
    
    try {
        // Check if service has appointment items
        $checkSql = "SELECT COUNT(*) FROM AppointmentItem WHERE service_id = :id";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->execute([':id' => $service_id]);
        $appointmentCount = $checkStmt->fetchColumn();
        
        if ($appointmentCount > 0) {
            $_SESSION['error_message'] = "Cannot delete service with existing appointments! Consider marking it as Inactive instead.";
        } else {
            $sql = "DELETE FROM Service WHERE service_id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id' => $service_id]);
            $_SESSION['success_message'] = "Service deleted successfully!";
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
    }
    header("Location: services.php");
    exit;
}

// Delete Category (only if no services under it)
if (isset($_GET['delete_category_id'])) {
    $deleteCategoryId = (int)$_GET['delete_category_id'];
    if ($deleteCategoryId > 0) {
        try {
            $checkServices = $pdo->prepare("SELECT COUNT(*) FROM service WHERE category_id = :cid");
            $checkServices->execute([':cid' => $deleteCategoryId]);
            $serviceCount = (int)$checkServices->fetchColumn();

            if ($serviceCount > 0) {
                $_SESSION['error_message'] = "Cannot delete category with existing services. Please move or delete its services first.";
            } else {
                $deleteCat = $pdo->prepare("DELETE FROM category WHERE category_id = :cid");
                $deleteCat->execute([':cid' => $deleteCategoryId]);
                $_SESSION['success_message'] = "Category deleted successfully.";
            }
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Error deleting category: " . $e->getMessage();
        }
    }
    header("Location: services.php");
    exit;
}

// Fetch all services grouped by category
try {
    $sql = "SELECT * FROM category WHERE status='Active' ORDER BY category_name ASC";
    $stmt = $pdo->query($sql);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
        $categories = [];
    }
    
$totalPages = 1;
try {
    $filterSql = " WHERE 1=1";
    $params = [];

    if ($categoryFilter > 0) {
        $filterSql .= " AND s.category_id = :categoryId";
        $params[':categoryId'] = $categoryFilter;
    }

    if ($searchTerm !== '') {
        $filterSql .= " AND (s.service_name LIKE :searchTerm OR c.category_name LIKE :searchTerm)";
        $params[':searchTerm'] = '%' . $searchTerm . '%';
    }

    $countSql = "SELECT COUNT(*)
        FROM Service s
        INNER JOIN Category c ON s.category_id = c.category_id" . $filterSql;
    $countStmt = $pdo->prepare($countSql);
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value);
    }
    $countStmt->execute();
    $totalRecords = (int)$countStmt->fetchColumn();
    $totalPages = $totalRecords > 0 ? (int)ceil($totalRecords / $perPage) : 1;
    if ($totalPages > 0 && $page > $totalPages) {
        $page = $totalPages;
        $offset = ($page - 1) * $perPage;
    }

    $dataSql = "SELECT s.*, c.category_name
            FROM Service s 
            INNER JOIN Category c ON s.category_id = c.category_id" . $filterSql . "
            ORDER BY c.category_name, s.service_name
            LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($dataSql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', (int)$perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
    $stmt->execute();
    $pagedServices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group by category
    $servicesByCategory = [];
    foreach ($pagedServices as $service) {
        $categoryKey = $service['category_id'];
        if (!isset($servicesByCategory[$categoryKey])) {
            $servicesByCategory[$categoryKey] = [
                'name' => $service['category_name'],
                'services' => []
            ];
        }
        $servicesByCategory[$categoryKey]['services'][] = $service;
    }
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Error loading services: " . $e->getMessage();
    $servicesByCategory = [];
    $totalPages = 1;
}

include '../head.php';
include '../nav.php';
include '../flash_message.php';
?>

    <!-- Main Content -->
    <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <div class="page-header-content">
                <h2>Service Management</h2>
                <p>Manage salon services and pricing</p>
            </div>
            <div class="page-header-right">
                <form class="search-form" method="GET" action="">
                    <?php if ($categoryFilter > 0): ?>
                        <input type="hidden" name="category" value="<?php echo (int)$categoryFilter; ?>">
                    <?php endif; ?>
                    <div class="search-input">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" placeholder="Search services or categories" value="<?php echo htmlspecialchars($searchTerm); ?>">
                        <?php if ($searchTerm !== ''): ?>
                            <button type="button" class="clear-search" onclick="window.location.href='<?php echo htmlspecialchars(buildPageLink(1, $categoryFilter, ''), ENT_QUOTES, 'UTF-8'); ?>'">&times;</button>
                        <?php endif; ?>
                    </div>
                    <button type="submit" class="search-btn">Search</button>
                </form>

                <div class="action-buttons">
                    <button class="add-service-btn" onclick="openModal('addCategoryModal')" style="background-color: #6b7280;">
                        <i class="fas fa-folder-plus"></i>
                        Add Category
                    </button>

                    <button class="add-service-btn" onclick="openAddModal()">
                        <i class="fas fa-plus"></i>
                        Add Service
                    </button>
                </div>
            </div>
        </div>

        <!-- Services Section -->
        <div class="page-section active" data-section="services" id="servicesSection">
            <!-- Category Tabs -->
            <?php if (!empty($categories)): ?>
            <div class="category-tabs">
                <button type="button" class="category-tab <?php echo $categoryFilter === 0 ? 'active' : ''; ?>" data-category="all" onclick="window.location.href='<?php echo htmlspecialchars(buildPageLink(1, 0, $searchTerm), ENT_QUOTES, 'UTF-8'); ?>'">
                    All
                </button>
                <?php foreach ($categories as $cat): ?>
                    <div class="category-tab-wrapper">
                        <button type="button" class="category-tab <?php echo (int)$cat['category_id'] === $categoryFilter ? 'active' : ''; ?>" data-category="<?php echo $cat['category_id']; ?>" onclick="window.location.href='<?php echo htmlspecialchars(buildPageLink(1, (int)$cat['category_id'], $searchTerm), ENT_QUOTES, 'UTF-8'); ?>'">
                            <?php echo htmlspecialchars($cat['category_name']); ?>
                        </button>
                        <button type="button" class="category-delete-btn" title="Delete category" onclick="confirmDeleteCategory(<?php echo $cat['category_id']; ?>, '<?php echo htmlspecialchars($cat['category_name'], ENT_QUOTES, 'UTF-8'); ?>')">
                            &times;
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Service Categories -->
             <?php if (empty($servicesByCategory)): ?>
                <div class="empty-state">
                    <i class="fas <?php echo $searchTerm !== '' ? 'fa-search' : 'fa-inbox'; ?>"></i>
                     <?php if ($searchTerm !== ''): ?>
                         <p>No services match "<?php echo htmlspecialchars($searchTerm); ?>".</p>
                         <button class="btn-secondary" onclick="window.location.href='<?php echo htmlspecialchars(buildPageLink(1, $categoryFilter, ''), ENT_QUOTES, 'UTF-8'); ?>'">Clear search</button>
                     <?php else: ?>
                         <p>No services available. Please add a service.</p>
                     <?php endif; ?>
                 </div>
            <?php else: ?>

                <?php foreach ($servicesByCategory as $categoryData): ?>
                    <div class="service-category" data-category-id="<?php echo $categoryData['services'][0]['category_id']; ?>">
                        <h3 class="category-title">
                            <?php echo htmlspecialchars($categoryData['name']); ?></h3>
                        
                        <div class="service-table">
                            <div class="service-table-header">
                                <div>Service Name</div>
                                <div>Duration</div>
                                <div>Price</div>
                                <div>Description</div>
                                <div>Status</div>
                                <div>Actions</div>
                            </div>

                            <?php foreach ($categoryData['services'] as $service): ?>
                                <div class="service-row">
                                    <div class="service-name-col"><?php echo htmlspecialchars($service['service_name']); ?></div>
                                    <div class="duration-col">
                                        <i class="far fa-clock"></i>
                                        <span><?php echo htmlspecialchars($service['duration_minutes']); ?> min</span>
                                    </div>
                                    <div class="price-col">
                                        <p>RM</p>
                                        <span><?php echo htmlspecialchars($service['price']); ?></span>
                                    </div>
                                    <div class="description-col"><?php echo htmlspecialchars($service['description'] ?: 'N/A'); ?></div>
                                    <div class="status-col"><?php echo htmlspecialchars($service['status'] ); ?></div>

                                    <div class="actions-col">
                                       <button class="action-btn edit-btn" onclick='openEditModal(<?php echo htmlspecialchars(json_encode($service), ENT_QUOTES, "UTF-8"); ?>)'>
                                            <i class="fas fa-pen"></i>
                                        </button>
                                        <button class="action-btn delete-btn" onclick="confirmDelete(<?php echo $service['service_id']; ?>, '<?php echo htmlspecialchars($service['service_name']); ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                 <?php endforeach; ?>
             <?php endif; ?>

            <?php if (isset($totalPages) && $totalPages > 1): ?>
            <div class="pagination" style="display:flex; gap:8px; align-items:center; margin-top:16px;">
                <a class="page-btn <?php echo $page <= 1 ? 'disabled' : ''; ?>" 
                   href="<?php echo $page > 1 ? buildPageLink($page - 1, $categoryFilter, $searchTerm) : '#'; ?>"
                   style="padding:8px 12px; border:1px solid #e5e7eb; border-radius:6px; text-decoration:none; color:#1f2937; <?php echo $page <= 1 ? 'pointer-events:none; opacity:0.5;' : ''; ?>">
                   Prev
                </a>

                <div class="page-numbers" style="display:flex; gap:6px; flex-wrap:wrap;">
                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                        <a class="page-number <?php echo $p === $page ? 'active' : ''; ?>" 
                           href="<?php echo buildPageLink($p, $categoryFilter, $searchTerm); ?>"
                           style="min-width:36px; text-align:center; padding:8px; border:1px solid <?php echo $p === $page ? '#7c3aed' : '#e5e7eb'; ?>; border-radius:6px; text-decoration:none; color:<?php echo $p === $page ? '#7c3aed' : '#1f2937'; ?>; background:<?php echo $p === $page ? '#ede9fe' : 'white'; ?>;">
                           <?php echo $p; ?>
                        </a>
                    <?php endfor; ?>
                </div>

                <a class="page-btn <?php echo $page >= $totalPages ? 'disabled' : ''; ?>" 
                   href="<?php echo $page < $totalPages ? buildPageLink($page + 1, $categoryFilter, $searchTerm) : '#'; ?>"
                   style="padding:8px 12px; border:1px solid #e5e7eb; border-radius:6px; text-decoration:none; color:#1f2937; <?php echo $page >= $totalPages ? 'pointer-events:none; opacity:0.5;' : ''; ?>">
                   Next
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>

<div id="addCategoryModal" class="modal">
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header">
            <h3>Add New Category</h3>
            <span class="close" onclick="closeModal('addCategoryModal')">&times;</span>
        </div>
        <form method="POST" action="" id="addServiceForm">
            <div class="form-group">
                <label>Category Name *</label>
                <input type="text" name="category_name" placeholder="e.g., Hair Treatment" required>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('addCategoryModal')">Cancel</button>
                <button type="submit" name="add_category" class="btn-primary">Add Category</button>
            </div>
        </form>
    </div>
</div>
    
<!-- Add Service Modal -->
<div id="addModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Add New Service</h3>
            <span class="close" onclick="closeAddModal()">&times;</span>
        </div>
        <form method="POST" action="">
            <div class="form-group">
                <label>Service Name *</label>
                <input type="text" name="service_name" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Category *</label>
                    <select name="category_id" required>
                        <option value="">Select Category</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['category_id']; ?>">
                                <?php echo htmlspecialchars($cat['category_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                 <div class="form-group">
                      <label>Duration (minutes) *</label>
                    <input type="number" name="duration_minutes" min="<?php echo (int)$minServiceDurationMinutes; ?>" required>
                    <small class="input-hint">Minimum <?php echo (int)$minServiceDurationMinutes; ?> minutes</small>
                 </div>
                  <div class="form-group">
                      <label>Price (RM) *</label>
                    <input type="number" name="price" step="0.01" min="<?php echo (float)$minServicePriceRm; ?>" required>
                    <small class="input-hint">Minimum RM<?php echo htmlspecialchars((string)$minServicePriceRm); ?></small>
                  </div>
            </div>
            <div class="form-group">
                <label>Status *</label>
                <select name="status" required>
                    <option value="Active">Active</option>
                    <option value="Inactive">Inactive</option>
                </select>
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" rows="3" placeholder="Service description..."></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeAddModal()">Cancel</button>
                <button type="submit" name="add_service" class="btn-primary">Add Service</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Service Modal -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Edit Service</h3>
            <span class="close" onclick="closeEditModal()">&times;</span>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="service_id" id="edit_service_id">
            <div class="form-group">
                <label>Service Name *</label>
                <input type="text" name="service_name" id="edit_service_name" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Category *</label>
                    <select name="category_id" id="edit_category_id" required>
                        <option value="">Select Category</option>
                    <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['category_id']; ?>">
                                <?php echo htmlspecialchars($cat['category_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                 <div class="form-group">
                      <label>Duration (minutes) *</label>
                    <input type="number" name="duration_minutes" id="edit_duration" min="<?php echo (int)$minServiceDurationMinutes; ?>" required>
                    <small class="input-hint">Minimum <?php echo (int)$minServiceDurationMinutes; ?> minutes</small>
                 </div>
                  <div class="form-group">
                      <label>Price (RM) *</label>
                    <input type="number" name="price" id="edit_price" step="0.01" min="<?php echo (float)$minServicePriceRm; ?>" required>
                    <small class="input-hint">Minimum RM<?php echo htmlspecialchars((string)$minServicePriceRm); ?></small>
                  </div>
            </div>
            <div class="form-group">
                <label>Status *</label>
                <select name="status" id="edit_status" required>
                    <option value="Active">Active</option>
                    <option value="Inactive">Inactive</option>
                </select>
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" id="edit_description" rows="3"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeEditModal()">Cancel</button>
                <button type="submit" name="update_service" class="btn-primary">Update Service</button>
            </div>
        </form>
    </div>
</div>

<script src="../js/services.js"></script>
</body>
</html>
