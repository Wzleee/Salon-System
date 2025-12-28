<?php
require_once '../config.php';
require_once '../users_management/auth_check.php';
requireLogin(['Admin']);

$pageTitle = 'Booking Management - Cosmos Salon';
$pageCSS = '../css/bookings.css';
$currentPage = 'bookings';
include '../head.php';
include '../nav.php';

$statusFilter = isset($_GET['status']) ? $_GET['status'] : 'all';
$searchQuery = isset($_GET['search']) ? $_GET['search'] : '';
$allowedStatusFilters = ['all', 'Confirmed', 'Cancelled'];
if (!in_array($statusFilter, $allowedStatusFilters, true)) {
    $statusFilter = 'all';
}
$perPage = 7;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $perPage;

function buildPageLink($page, $statusFilter, $searchQuery) {
    $params = [];
    if ($statusFilter !== 'all') {
        $params['status'] = $statusFilter;
    }
    if ($searchQuery !== '') {
        $params['search'] = $searchQuery;
    }
    $params['page'] = $page;
    return '?' . http_build_query($params);
}

$filterSql = " WHERE 1=1";
$queryParams = [];

if ($statusFilter === 'Cancelled') {
    $filterSql .= " AND a.status = 'Cancelled'";
} elseif ($statusFilter === 'Confirmed') {
    $filterSql .= " AND a.status <> 'Cancelled'";
}

if ($searchQuery !== '') {
    $filterSql .= " AND (u.name LIKE :search OR u.email LIKE :search OR s.service_name LIKE :search OR a.appointment_id LIKE :search)";
    $queryParams[':search'] = '%' . $searchQuery . '%';
}

$countSql = "SELECT COUNT(DISTINCT a.appointment_id) as total
FROM appointment a
INNER JOIN user u ON a.user_id = u.user_id
INNER JOIN stylist st ON a.stylist_id = st.stylist_id
INNER JOIN user su ON st.user_id = su.user_id
LEFT JOIN appointmentitem ai ON a.appointment_id = ai.appointment_id
LEFT JOIN service s ON ai.service_id = s.service_id" . $filterSql;

$dataSql = "SELECT 
    a.appointment_id,
    a.appointment_date,
    a.appointment_time,
    a.status,
    a.total_price,
    u.name as customer_name,
    u.email as customer_email,
    u.phone as customer_phone,
    su.name as stylist_name,
    GROUP_CONCAT(DISTINCT s.service_name ORDER BY s.service_name SEPARATOR ', ') as services,
    GROUP_CONCAT(DISTINCT s.duration_minutes ORDER BY s.duration_minutes DESC SEPARATOR ',') as durations
FROM appointment a
INNER JOIN user u ON a.user_id = u.user_id
INNER JOIN stylist st ON a.stylist_id = st.stylist_id
INNER JOIN user su ON st.user_id = su.user_id
LEFT JOIN appointmentitem ai ON a.appointment_id = ai.appointment_id
LEFT JOIN service s ON ai.service_id = s.service_id" . $filterSql . "
GROUP BY a.appointment_id 
ORDER BY a.appointment_id DESC
LIMIT :limit OFFSET :offset";

try{
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
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $statsSQL = "SELECT
    COUNT(*) as total,
        SUM(CASE WHEN status <> 'Cancelled' THEN 1 ELSE 0 END) as confirmed,
        SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled
    FROM appointment";

    $statsStmt = $pdo->prepare($statsSQL);
    $statsStmt->execute();
    $stats = $statsStmt ->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
    
}

?>

    <!-- Main Content -->
    <div class="main-content">

    <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background: #dbeafe; color: #1e40af;">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-info">
                    <h3>Confirmed</h3>
                    <div class="stat-number"><?php echo $stats['confirmed'] ?? 0; ?></div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: #fee2e2; color: #991b1b;">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-info">
                    <h3>Cancelled</h3>
                    <div class="stat-number"><?php echo $stats['cancelled'] ?? 0; ?></div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: #ede9fe; color: #7c3aed;">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="stat-info">
                    <h3>Total</h3>
                    <div class="stat-number"><?php echo $stats['total'] ?? 0; ?></div>
                </div>
            </div>
        </div>

        <!-- Section Title -->
        <div class="section-title">
            <h2>Booking Management</h2>

            <form method="GET" action="" style="display: flex; gap: 1rem; width: 100%;">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" name="search" placeholder="Search by booking id, customer name, email, or service..."
                    value="<?php echo htmlspecialchars($searchQuery); ?>">
            </div>
            <div class="status-filter">
                <select name="status" onchange="this.form.submit()">
                    <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                    <option value="Confirmed" <?php echo $statusFilter === 'Confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                    <option value="Cancelled" <?php echo $statusFilter === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>
            <button type="submit" class="search-btn">
                <i class="fas fa-search"></i> Search
            </button>
        </form>



<!-- Booking Table -->
        <div class="booking-table">
            <div class="table-header">
                <div>Booking ID</div>
                <div>Customer</div>
                <div>Service</div>
                <div>Stylist</div>
                <div>Date & Time</div>
                <div>Status</div>
            </div>

            <?php if (empty($appointments)): ?>
                <div class="no-data">
                    <i class="fas fa-calendar-times"></i>
                    <p>No appointments found.</p>
                </div>
            <?php else: ?>
                <?php foreach ($appointments as $appointment): ?>
                    <?php $displayStatus = ($appointment['status'] === 'Cancelled') ? 'Cancelled' : 'Confirmed'; ?>
                    <div class="table-row">
                        <div style="font-weight: 600;">#<?php echo htmlspecialchars($appointment['appointment_id']); ?></div>
                        <div class="customer-info">
                            <span class="customer-name">
                                <i class="fas fa-user"></i>
                                <?php echo htmlspecialchars($appointment['customer_name']); ?>
                            </span>
                            <span class="customer-contact">
                                <i class="fas fa-envelope"></i>
                                <?php echo htmlspecialchars($appointment['customer_email']); ?>
                            </span>
                            <span class="customer-contact">
                                <i class="fas fa-phone"></i>
                                <?php echo htmlspecialchars($appointment['customer_phone']); ?>
                            </span>
                        </div>

                        <div class="service-info">
                            <div class="service-icon">
                                <i class="fas fa-cut"></i>
                            </div>
                            <div class="service-details">
                                <span class="service-name"><?php echo htmlspecialchars($appointment['services']); ?></span>
                                <span class="service-meta">
                                    <?php echo $appointment['durations']; ?> min • RM<?php echo number_format($appointment['total_price'], 2); ?>
                                </span>
                            </div>
                        </div>
                        <div style="font-weight: 500; color: #1f2937;">
                            <?php echo htmlspecialchars($appointment['stylist_name']); ?>
                        </div>

                        <div class="datetime-info">
                            <i class="far fa-calendar"></i>
                            <div class="date-time">
                                <span class="date"><?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?></span>
                                <span class="time"><?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?></span>
                            </div>
                        </div>
                        
                        <div>
                            <span class="status-badge <?php echo strtolower($displayStatus); ?>">
                                <?php echo $displayStatus; ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if (isset($totalPages) && $totalPages > 1): ?>
        <div class="pagination" style="display:flex; gap:8px; align-items:center; margin-top:16px;">
            <a class="page-btn <?php echo $page <= 1 ? 'disabled' : ''; ?>" 
               href="<?php echo $page > 1 ? buildPageLink($page - 1, $statusFilter, $searchQuery) : '#'; ?>"
               style="padding:8px 12px; border:1px solid #e5e7eb; border-radius:6px; text-decoration:none; color:#1f2937; <?php echo $page <= 1 ? 'pointer-events:none; opacity:0.5;' : ''; ?>">
               Prev
            </a>

            <div class="page-numbers" style="display:flex; gap:6px; flex-wrap:wrap;">
                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                    <a class="page-number <?php echo $p === $page ? 'active' : ''; ?>" 
                       href="<?php echo buildPageLink($p, $statusFilter, $searchQuery); ?>"
                       style="min-width:36px; text-align:center; padding:8px; border:1px solid <?php echo $p === $page ? '#7c3aed' : '#e5e7eb'; ?>; border-radius:6px; text-decoration:none; color:<?php echo $p === $page ? '#7c3aed' : '#1f2937'; ?>; background:<?php echo $p === $page ? '#ede9fe' : 'white'; ?>;">
                       <?php echo $p; ?>
                    </a>
                <?php endfor; ?>
            </div>

            <a class="page-btn <?php echo $page >= $totalPages ? 'disabled' : ''; ?>" 
               href="<?php echo $page < $totalPages ? buildPageLink($page + 1, $statusFilter, $searchQuery) : '#'; ?>"
               style="padding:8px 12px; border:1px solid #e5e7eb; border-radius:6px; text-decoration:none; color:#1f2937; <?php echo $page >= $totalPages ? 'pointer-events:none; opacity:0.5;' : ''; ?>">
               Next
            </a>
        </div>
        <?php endif; ?>
        </div>
        
</body>
</html>
