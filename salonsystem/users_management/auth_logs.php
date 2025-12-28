<?php 
require_once '../config.php';
require_once '../users_management/auth_check.php';
requireLogin(['Admin']);

$pageTitle = 'Auth Logs - Cosmos Salon';
$pageCSS = '../css/auth_logs.css';
$currentPage = 'auth_logs';

$totalLogs = 0;
$loginLogs = 0;
$logoutLogs = 0;
$todayLogs = 0;
$logs = [];
$totalPages = 1;
$page = 1;
$errorMessage = '';
$searchTerm = isset($_GET['q']) ? trim($_GET['q']) : '';
$categoryFilter = isset($_GET['category']) ? strtolower(trim($_GET['category'])) : '';
$dateFrom = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$dateTo = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';


if ($dateFrom && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $dateFrom = '';
}
if ($dateTo && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $dateTo = '';
}
if (!in_array($categoryFilter, ['', 'login', 'logout'], true)) {
    $categoryFilter = '';
}

function buildPageLink($page, $searchTerm, $categoryFilter, $dateFrom, $dateTo) {
    $params = [];
    if ($searchTerm !== '') {
        $params['q'] = $searchTerm;
    }
    if ($categoryFilter !== '') {
        $params['category'] = $categoryFilter;
    }
    if ($dateFrom !== '') {
        $params['date_from'] = $dateFrom;
    }
    if ($dateTo !== '') {
        $params['date_to'] = $dateTo;
    }
    $params['page'] = $page;
    return '?' . http_build_query($params);
}

try {
    $checkTable = $pdo->query("SHOW TABLES LIKE 'auditlog'");
    if ($checkTable->rowCount() == 0) {
        $errorMessage = "Error: The 'auditlog' table does not exist. Please run the SQL script to create it.";
    } else {
        //Get statistical data
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM auditlog");
        $totalLogs = $stmt->fetch()->total;
        
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM auditlog WHERE category = 'login'");
        $loginLogs = $stmt->fetch()->total;
        
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM auditlog WHERE category = 'logout'");
        $logoutLogs = $stmt->fetch()->total;
        
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM auditlog WHERE DATE(created_at) = CURDATE()");
        $todayLogs = $stmt->fetch()->total;
 
        $conditions = [];
        $bindParams = [];

        if ($searchTerm !== '') {
            $conditions[] = "(LOWER(u.name) LIKE :search OR LOWER(u.role) LIKE :search OR LOWER(a.action) LIKE :search OR LOWER(a.description) LIKE :search)";
            $bindParams[':search'] = '%' . strtolower($searchTerm) . '%';
        }
        if ($categoryFilter !== '') {
            $conditions[] = "LOWER(a.category) = :category";
            $bindParams[':category'] = $categoryFilter;
        }
        if ($dateFrom !== '') {
            $conditions[] = "a.created_at >= :dateFrom";
            $bindParams[':dateFrom'] = $dateFrom . ' 00:00:00';
        }
        if ($dateTo !== '') {
            $conditions[] = "a.created_at <= :dateTo";
            $bindParams[':dateTo'] = $dateTo . ' 23:59:59';
        }

        $whereSql = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';

        $countSql = "
            SELECT COUNT(*) as total
            FROM auditlog a
            JOIN user u ON a.user_id = u.user_id
            $whereSql
        ";
        $stmt = $pdo->prepare($countSql);
        foreach ($bindParams as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $filteredTotal = (int)$stmt->fetch()->total;

        // Get Log Lists
        $perPage = 7;
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $totalPages = $filteredTotal > 0 ? (int)ceil($filteredTotal / $perPage) : 1;
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;
        
        $listSql = "
            SELECT 
                a.log_id,
                a.action,
                a.category,
                a.description,
                a.created_at,
                u.user_id,
                u.name,
                u.email,
                u.role
            FROM auditlog a
            JOIN user u ON a.user_id = u.user_id
            $whereSql
            ORDER BY a.created_at DESC
            LIMIT :limit OFFSET :offset
        ";
        $stmt = $pdo->prepare($listSql);
        foreach ($bindParams as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', (int)$perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        $stmt->execute();

        $logs = $stmt->fetchAll(PDO::FETCH_OBJ);
    }
    
} catch (PDOException $e) {
    $errorMessage = "Database Error: " . $e->getMessage();
    error_log("Audit logs error: " . $e->getMessage());
}

include '../head.php'; 
include '../nav.php';
?>

<!-- Main Content -->
<div class="main-content">
    <?php if (!empty($errorMessage)): ?>
        <div style="background: #fee2e2; border: 2px solid #ef4444; border-radius: 8px; padding: 1rem; margin-bottom: 1.5rem; color: #991b1b;">
            <strong>ƒsÿ‹,? Error:</strong> <?php echo htmlspecialchars($errorMessage); ?>
        </div>
    <?php endif; ?>

    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon total">
                <i class="fas fa-list"></i>
            </div>
            <div class="stat-info">
                <h3>Total Logs</h3>
                <p class="stat-number"><?php echo $totalLogs; ?></p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon login">
                <i class="fas fa-sign-in-alt"></i>
            </div>
            <div class="stat-info">
                <h3>Login</h3>
                <p class="stat-number"><?php echo $loginLogs; ?></p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon logout">
                <i class="fas fa-sign-out-alt"></i>
            </div>
            <div class="stat-info">
                <h3>Logout</h3>
                <p class="stat-number"><?php echo $logoutLogs; ?></p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon today">
                <i class="fas fa-calendar-day"></i>
            </div>
            <div class="stat-info">
                <h3>Today</h3>
                <p class="stat-number"><?php echo $todayLogs; ?></p>
            </div>
        </div>
    </div>
    
    <!-- Audit Logs Section -->
    <div class="content-card">
        <div class="section-header">
            <div>
                <h2 class="section-title">Audit Logs</h2>
                <p class="section-subtitle">Track all system login and logout activities.</p>
            </div>
        </div>
        
        <!-- Search and Filter Bar -->
        <form id="filterForm" method="get" class="controls-bar">
            <input type="hidden" name="page" id="pageInput" value="<?php echo (int)$page; ?>">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" name="q" value="<?php echo htmlspecialchars($searchTerm); ?>" placeholder="Search by user or action...">
            </div>
            
            <div class="date-range">
                <input type="date" id="dateFrom" name="date_from" class="date-input" value="<?php echo htmlspecialchars($dateFrom); ?>">
                <span style="color:#6b7280;font-weight:500;">TO</span>
                <input type="date" id="dateTo" name="date_to" class="date-input" value="<?php echo htmlspecialchars($dateTo); ?>">
            </div>
            
            <select id="categoryFilter" name="category" class="category-filter">
                <option value="" <?php echo $categoryFilter === '' ? 'selected' : ''; ?>>All Categories</option>
                <option value="login" <?php echo $categoryFilter === 'login' ? 'selected' : ''; ?>>Login</option>
                <option value="logout" <?php echo $categoryFilter === 'logout' ? 'selected' : ''; ?>>Logout</option>
            </select>

            <button type="button" id="clearFilters" class="clearbtn">
                Clear
            </button>
        </form>
        
        <!-- Audit Logs Table -->
        <div class="table-container">
            <table class="logs-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Action</th>
                        <th>Category</th>
                        <th>Description</th>
                        <th>Timestamp</th>
                    </tr>
                </thead>
                <tbody id="logsTable">
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 2rem; color: #6b7280;">
                                No logs found
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td>
                                    <div class="user-info">
                                        <div>
                                            <span class="user-name"><?php echo htmlspecialchars($log->name); ?></span>
                                            <span class="user-role"><?php echo htmlspecialchars($log->role); ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="action-text"><?php echo htmlspecialchars($log->action); ?></span></td>
                                <td><span class="category-badge <?php echo strtolower($log->category); ?>"><?php echo htmlspecialchars(ucfirst($log->category)); ?></span></td>
                                <td>
                                    <div class="description-text">
                                        <?php
                                            $description = $log->description;
                                            if (stripos($description, 'user ') === 0) {
                                                $description = $log->role . substr($description, 4);
                                            }
                                            echo htmlspecialchars($description);
                                        ?>
                                    </div>
                                </td>
                                <?php $date = new DateTime($log->created_at); ?>
                                <td>
                                    <div class="timestamp" data-iso="<?php echo htmlspecialchars($date->format('c'), ENT_QUOTES); ?>">
                                        <?php 
                                        echo strtoupper($date->format('d M Y')) . '<br>' . $date->format('H:i:s');
                                        ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if (isset($totalPages) && $totalPages > 1): ?>
        <div class="pagination" style="display:flex; gap:8px; align-items:center; margin-top:16px;">
            <a class="page-btn <?php echo $page <= 1 ? 'disabled' : ''; ?>" 
               href="<?php echo $page > 1 ? buildPageLink($page - 1, $searchTerm, $categoryFilter, $dateFrom, $dateTo) : '#'; ?>"
               style="padding:8px 12px; border:1px solid #e5e7eb; border-radius:6px; text-decoration:none; color:#1f2937; <?php echo $page <= 1 ? 'pointer-events:none; opacity:0.5;' : ''; ?>">
               Prev
            </a>

            <div class="page-numbers" style="display:flex; gap:6px; flex-wrap:wrap;">
                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                    <a class="page-number <?php echo $p === $page ? 'active' : ''; ?>" 
                       href="<?php echo buildPageLink($p, $searchTerm, $categoryFilter, $dateFrom, $dateTo); ?>"
                       style="min-width:36px; text-align:center; padding:8px; border:1px solid <?php echo $p === $page ? '#7c3aed' : '#e5e7eb'; ?>; border-radius:6px; text-decoration:none; color:<?php echo $p === $page ? '#7c3aed' : '#1f2937'; ?>; background:<?php echo $p === $page ? '#ede9fe' : 'white'; ?>;">
                       <?php echo $p; ?>
                    </a>
                <?php endfor; ?>
            </div>

            <a class="page-btn <?php echo $page >= $totalPages ? 'disabled' : ''; ?>" 
               href="<?php echo $page < $totalPages ? buildPageLink($page + 1, $searchTerm, $categoryFilter, $dateFrom, $dateTo) : '#'; ?>"
               style="padding:8px 12px; border:1px solid #e5e7eb; border-radius:6px; text-decoration:none; color:#1f2937; <?php echo $page >= $totalPages ? 'pointer-events:none; opacity:0.5;' : ''; ?>">
               Next
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
const filterForm = document.getElementById('filterForm');
const pageInput = document.getElementById('pageInput');

function submitFilters() {
    if (pageInput) pageInput.value = 1;
    filterForm.submit();
}

function debounceSubmit() {
    let timer;
    return function() {
        clearTimeout(timer);
        timer = setTimeout(submitFilters, 300);
    };
}

const debounced = debounceSubmit();
document.getElementById('searchInput').addEventListener('input', debounced);
document.getElementById('dateFrom').addEventListener('change', submitFilters);
document.getElementById('dateTo').addEventListener('change', submitFilters);
document.getElementById('categoryFilter').addEventListener('change', submitFilters);

document.getElementById('clearFilters').addEventListener('click', function() {
    document.getElementById('searchInput').value = '';
    document.getElementById('dateFrom').value = '';
    document.getElementById('dateTo').value = '';
    document.getElementById('categoryFilter').value = '';
    submitFilters();
});
</script>

</body>
</html>
