<?php
require_once '../config.php';

$pageTitle = 'Reports - Cosmos Salon';
$pageCSS = '/salonsystem/css/report.css';
$currentPage = 'reports';

include '../head.php';
include '../nav.php';

$startDate = $_GET['start'] ?? '';
$endDate = $_GET['end'] ?? '';
$status = $_GET['status'] ?? 'all';
$period = $_GET['period'] ?? 'daily';


/* -----------------------
   FILTER QUERY
------------------------ */
$filterSql = " WHERE 1=1";
$queryParams = [];

if ($startDate !== '') {
    $filterSql .= " AND a.appointment_date >= :start";
    $queryParams[':start'] = $startDate;
}

if ($endDate !== '') {
    $filterSql .= " AND a.appointment_date <= :end";
    $queryParams[':end'] = $endDate;
}

if ($status !== 'all') {
    $filterSql .= " AND a.status = :status";
    $queryParams[':status'] = $status;
}
/* -----------------------
   PERIOD GROUPING
------------------------ */
switch ($period) {
    case 'weekly':
        $groupBy = "YEAR(a.appointment_date), WEEK(a.appointment_date)";
        $label = "CONCAT('Week ', WEEK(a.appointment_date))";
        break;

    case 'monthly':
        $groupBy = "YEAR(a.appointment_date), MONTH(a.appointment_date)";
        $label = "DATE_FORMAT(a.appointment_date, '%b %Y')";
        break;

    default:
        $groupBy = "DATE(a.appointment_date)";
        $label = "DATE(a.appointment_date)";

        $userGroupBy = "DATE(u.created_at)";
        $userLabel = "DATE(u.created_at)";
}
if ($period === 'weekly') {
    $userGroupBy = "YEAR(u.created_at), WEEK(u.created_at)";
    $userLabel = "CONCAT('Week ', WEEK(u.created_at))";
} elseif ($period === 'monthly') {
    $userGroupBy = "YEAR(u.created_at), MONTH(u.created_at)";
    $userLabel = "DATE_FORMAT(u.created_at, '%b %Y')";
}


/* -----------------------
   TREND QUERING
------------------------ */
$trendSQL = "
SELECT 
    $label AS period_label,
    COUNT(*) AS total,
    SUM(status = 'Completed') as completed,
    SUM(status = 'Confirmed') as confirmed,
    SUM(status = 'Cancelled') as cancelled,
    SUM(status = 'No-Show') as noshow
FROM appointment a
$filterSql
GROUP BY $groupBy
ORDER BY a.appointment_date
";

$trendStmt = $pdo->prepare($trendSQL);
$trendStmt->execute($queryParams);
$appointmentTrend = $trendStmt->fetchAll(PDO::FETCH_ASSOC);



// Peak Hour
$peakHourSQL = "
    SELECT HOUR(appointment_date) as h, COUNT(*) as c 
    FROM appointment a 
    $filterSql 
    GROUP BY h 
    ORDER BY c DESC 
    LIMIT 1
";
$peakStmt = $pdo->prepare($peakHourSQL);
$peakStmt->execute($queryParams);
$peakHourData = $peakStmt->fetch(PDO::FETCH_ASSOC);
$peakHourDisplay = $peakHourData ? date("g A", strtotime("{$peakHourData['h']}:00")) : 'N/A';

// Busiest & Most Cancelled Period
$busiestPeriod = ['label' => 'N/A', 'count' => 0];
$mostCancelledPeriod = ['label' => 'N/A', 'count' => 0];

if (!empty($appointmentTrend)) {

    usort($appointmentTrend, fn($a, $b) => $b['total'] <=> $a['total']);
    $busiestPeriod = ['label' => $appointmentTrend[0]['period_label'], 'count' => $appointmentTrend[0]['total']];

    $tempTrend = $appointmentTrend;

    usort($tempTrend, fn($a, $b) => $b['cancelled'] <=> $a['cancelled']);
    $mostCancelledPeriod = ['label' => $tempTrend[0]['period_label'], 'count' => $tempTrend[0]['cancelled']];

    $maxTotal = -1;
    $maxCanc = -1;

    $trendStmt->execute($queryParams);
    $appointmentTrend = $trendStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($appointmentTrend as $row) {
        if ($row['total'] > $maxTotal) {
            $maxTotal = $row['total'];
            $busiestPeriod = ['label' => $row['period_label'], 'count' => $row['total']];
        }
        if ($row['cancelled'] > $maxCanc) {
            $maxCanc = $row['cancelled'];
            $mostCancelledPeriod = ['label' => $row['period_label'], 'count' => $row['cancelled']];
        }
    }
}



// Duration Utilization Elements 
$durationSQL = "
    SELECT 
        $label AS period_label,
        SUM(s.duration_minutes) as total_minutes
    FROM appointment a
    JOIN appointmentitem ai ON a.appointment_id = ai.appointment_id
    JOIN service s ON ai.service_id = s.service_id
    $filterSql
    GROUP BY $groupBy
    ORDER BY a.appointment_date
";
$durationStmt = $pdo->prepare($durationSQL);
$durationStmt->execute($queryParams);
$durationData = $durationStmt->fetchAll(PDO::FETCH_ASSOC);

/* -----------------------
   APPOINTMENT REPORTS
------------------------ */
$appointmentSummarySQL = "
SELECT
    COUNT(*) AS total,
    SUM(status = 'Completed') AS completed,
    SUM(status = 'Confirmed') AS confirmed,
    SUM(status = 'Cancelled') AS cancelled,
    SUM(status = 'No-Show') AS no_show
FROM appointment a
$filterSql
";

$stmt = $pdo->prepare($appointmentSummarySQL);
$stmt->execute($queryParams);
$appointmentStats = $stmt->fetch(PDO::FETCH_ASSOC);

$totalAppts = $appointmentStats['total'] > 0 ? $appointmentStats['total'] : 1;
$completionRate = round(($appointmentStats['completed'] / $totalAppts) * 100, 1);
$confirmedRate = round(($appointmentStats['confirmed'] / $totalAppts) * 100, 1);
$cancellationRate = round(($appointmentStats['cancelled'] / $totalAppts) * 100, 1);
$noShowRate = round((($appointmentStats['no_show'] ?? 0) / $totalAppts) * 100, 1);

/* -----------------------
   CUSTOMER REPORTS
------------------------ */

$totalCustomers = $pdo->query("SELECT COUNT(*) FROM user WHERE role = 'Customer'")->fetchColumn();

$userFilterSql = " WHERE role = 'Customer'";
$userParams = [];
if ($startDate !== '') {
    $userFilterSql .= " AND u.created_at >= :start";
    $userParams[':start'] = $startDate;
}
if ($endDate !== '') {
    $userFilterSql .= " AND u.created_at <= :end";
    $userParams[':end'] = $endDate;
}

$growthSQL = "
    SELECT 
        $userLabel as period_label,
        COUNT(*) as new_count
    FROM user u
    $userFilterSql
    GROUP BY $userGroupBy
    ORDER BY u.created_at
";
$growthStmt = $pdo->prepare($growthSQL);
$growthStmt->execute($userParams);
$customerGrowth = $growthStmt->fetchAll(PDO::FETCH_ASSOC);

// Retention & Segmentation

$segmentSQL = "
    SELECT 
        u.user_id, 
        COUNT(a.appointment_id) as visit_count,
        MAX(a.appointment_date) as last_visit
    FROM user u
    LEFT JOIN appointment a ON u.user_id = a.user_id
    WHERE u.role = 'Customer'
    GROUP BY u.user_id
";

$segmentStmt = $pdo->query($segmentSQL);
$allCustomerStats = $segmentStmt->fetchAll(PDO::FETCH_ASSOC);

$highValue = 0; // > 5 visits
$regular = 0;   // 3-5 visits
$oneTime = 0;   // 1 visit
$dormant = 0;   // No visit in 90 days (and has visited before)
$churnrisk = 0; // No visit > 60 days
$retainedCount = 0; // > 1 visit

$ninetyDaysAgo = date('Y-m-d', strtotime('-90 days'));

foreach ($allCustomerStats as $cust) {
    $cnt = $cust['visit_count'];
    $last = $cust['last_visit'];

    if ($cnt > 1)
        $retainedCount++;

    if ($cnt > 5) {
        $highValue++;
    } elseif ($cnt >= 3) {
        $regular++;
    } elseif ($cnt == 1) {
        $oneTime++;
    }

    if ($last && $last < $ninetyDaysAgo) {
        $dormant++;
    }
}


$retentionRate = $totalCustomers > 0 ? round(($retainedCount / $totalCustomers) * 100, 1) : 0;

$churnRate = $totalCustomers > 0 ? round(($dormant / $totalCustomers) * 100, 1) : 0;

// Service Combo 
$comboSQL = "
    SELECT 
        CONCAT(s1.service_name, ' + ', s2.service_name) as combo_name,
        COUNT(*) as frequency
    FROM appointmentitem ai1
    JOIN appointmentitem ai2 ON ai1.appointment_id = ai2.appointment_id AND ai1.service_id < ai2.service_id
    JOIN service s1 ON ai1.service_id = s1.service_id
    JOIN service s2 ON ai2.service_id = s2.service_id
    JOIN appointment a ON ai1.appointment_id = a.appointment_id
    $filterSql
    GROUP BY s1.service_id, s2.service_id
    ORDER BY frequency DESC
    LIMIT 5
";
$comboStmt = $pdo->prepare($comboSQL);
$comboStmt->execute($queryParams);
$topCombos = $comboStmt->fetchAll(PDO::FETCH_ASSOC);

/* -----------------------
   STYLIST REPORTS (Revamped)
------------------------ */
$stylistStmt = $pdo->prepare("
    SELECT 
        st.stylist_id, 
        su.name AS stylist_name, 
        COUNT(DISTINCT a.appointment_id) AS total_appts,
        SUM(ai.service_price) as total_revenue,
        SUM(s.duration_minutes) as booked_minutes
    FROM appointment a
    JOIN stylist st ON a.stylist_id = st.stylist_id
    JOIN user su ON st.user_id = su.user_id
    LEFT JOIN appointmentitem ai ON a.appointment_id = ai.appointment_id
    LEFT JOIN service s ON ai.service_id = s.service_id
    $filterSql
    GROUP BY a.stylist_id
    ORDER BY total_appts DESC
");
$stylistStmt->execute($queryParams);
$stylistReportRaw = $stylistStmt->fetchAll(PDO::FETCH_ASSOC);

// Schedule
$scheduleStmt = $pdo->query("
    SELECT stylist_id, start_time, end_time, day_of_week 
    FROM schedule 
    WHERE schedule_scope = 'weekly' AND is_available = 1
");
$schedules = $scheduleStmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_ASSOC);

// Calculate Period Length (Days)
$startTs = strtotime($startDate ?: date('Y-m-01'));
$endTs = strtotime($endDate ?: date('Y-m-d'));
$daysInPeriod = max(1, round(($endTs - $startTs) / (60 * 60 * 24)) + 1);
$weeksInPeriod = $daysInPeriod / 7;

$totalApptsGlobal = array_sum(array_column($stylistReportRaw, 'total_appts'));

$stylistReport = [];
$topPerformer = ['name' => 'N/A', 'revenue' => 0];
$underPerformer = ['name' => 'N/A', 'appts' => 999999];

foreach ($stylistReportRaw as $row) {
    $sid = $row['stylist_id'];

    // Calculate Capacity
    $weeklyMins = 0;
    if (isset($schedules[$sid])) {
        foreach ($schedules[$sid] as $sch) {
            $start = strtotime($sch['start_time']);
            $end = strtotime($sch['end_time']);
            if ($end > $start) {
                $weeklyMins += ($end - $start) / 60;
            }
        }
    }
    // Default to 40 hours (2400 mins) if no schedule found
    if ($weeklyMins == 0)
        $weeklyMins = 2400;

    $capacityMins = $weeklyMins * $weeksInPeriod;
    $bookedMins = $row['booked_minutes'] ?: 0;

    $utilization = $capacityMins > 0 ? round(($bookedMins / $capacityMins) * 100, 1) : 0;
    $share = $totalApptsGlobal > 0 ? round(($row['total_appts'] / $totalApptsGlobal) * 100, 1) : 0;
    $avgPerDay = round($row['total_appts'] / $daysInPeriod, 2);

    // Track Best and Worst
    if ($row['total_revenue'] > $topPerformer['revenue']) {
        $topPerformer = ['name' => $row['stylist_name'], 'revenue' => $row['total_revenue']];
    }
    if ($row['total_appts'] < $underPerformer['appts']) {
        $underPerformer = ['name' => $row['stylist_name'], 'appts' => $row['total_appts']];
    }

    $row['utilization'] = $utilization;
    $row['share'] = $share;
    $row['avg_per_day'] = $avgPerDay;
    $stylistReport[] = $row;
}
if ($underPerformer['appts'] == 999999)
    $underPerformer['appts'] = 0;


/* -----------------------
   SERVICE REPORTS (Revamped)
------------------------ */

// Total & Growth Logic
$svcCountParams = $queryParams;
$svcCountSQL = "
    SELECT COUNT(*) 
    FROM appointmentitem ai
    JOIN appointment a ON ai.appointment_id = a.appointment_id
    $filterSql
";
$stmt = $pdo->prepare($svcCountSQL);
$stmt->execute($svcCountParams);
$totalServiceBookings = $stmt->fetchColumn();

// Previous Period (for Growth)
$startTs = strtotime($startDate ?: date('Y-m-01'));
$endTs = strtotime($endDate ?: date('Y-m-d'));
$duration = $endTs - $startTs;
$prevStart = date('Y-m-d', $startTs - $duration - 86400);
$prevEnd = date('Y-m-d', $startTs - 86400);

$prevParams = [];
$prevFilter = " WHERE 1=1 ";
if ($startDate !== '') {
    $prevFilter .= " AND a.appointment_date >= :pstart AND a.appointment_date <= :pend";
    $prevParams[':pstart'] = $prevStart;
    $prevParams[':pend'] = $prevEnd;
}

$prevSvcSQL = "
    SELECT COUNT(*) 
    FROM appointmentitem ai
    JOIN appointment a ON ai.appointment_id = a.appointment_id
    $prevFilter
";
$stmt = $pdo->prepare($prevSvcSQL);
$stmt->execute($prevParams);
$prevServiceBookings = $stmt->fetchColumn();

$svcGrowth = 0;
if ($prevServiceBookings > 0) {
    $svcGrowth = (($totalServiceBookings - $prevServiceBookings) / $prevServiceBookings) * 100;
} else {
    $svcGrowth = $totalServiceBookings > 0 ? 100 : 0;
}

// Service Performance & Stylist Affinity

$affinitySQL = "
    SELECT s.service_id, s.service_name, st.user_id, su.name as stylist_name, COUNT(*) as count
    FROM appointmentitem ai
    JOIN appointment a ON ai.appointment_id = a.appointment_id
    JOIN service s ON ai.service_id = s.service_id
    JOIN stylist st ON a.stylist_id = st.stylist_id
    JOIN user su ON st.user_id = su.user_id
    $filterSql
    GROUP BY s.service_id, st.stylist_id
    ORDER BY s.service_id, count DESC
";
$stmt = $pdo->prepare($affinitySQL);
$stmt->execute($queryParams);
$affinityRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Map Service -> Top Stylist
$serviceExperts = [];
foreach ($affinityRaw as $row) {
    if (!isset($serviceExperts[$row['service_name']])) {
        $serviceExperts[$row['service_name']] = $row['stylist_name']; // First one is max due to Sort
    }
}

// Main Service List
$serviceListSQL = "
    SELECT s.service_name, COUNT(*) as count, SUM(ai.service_price) as revenue
    FROM appointmentitem ai
    JOIN appointment a ON ai.appointment_id = a.appointment_id
    JOIN service s ON ai.service_id = s.service_id
    $filterSql
    GROUP BY s.service_id
    ORDER BY count DESC
";
$serviceListStmt = $pdo->prepare($serviceListSQL);
$serviceListStmt->execute($queryParams);
$allServiceStats = $serviceListStmt->fetchAll(PDO::FETCH_ASSOC);

// Top 5 & Bottom 5 from sorted list
$top5Services = array_slice($allServiceStats, 0, 5);
$bottom5Services = array_slice($allServiceStats, -5);

if (count($allServiceStats) < 5)
    $bottom5Services = [];


$serviceChartData = array_slice($allServiceStats, 0, 10); // Top 10 for chart
$mostBooked = $allServiceStats[0] ?? ['service_name' => 'N/A', 'count' => 0];
$leastBooked = end($allServiceStats) ?: ['service_name' => 'N/A', 'count' => 0];


foreach ($top5Services as &$svc) {
    $svc['share'] = $totalServiceBookings > 0 ? round(($svc['count'] / $totalServiceBookings) * 100, 1) : 0;
    $svc['expert'] = $serviceExperts[$svc['service_name']] ?? 'N/A';
}
foreach ($bottom5Services as &$svc) {
    $svc['share'] = $totalServiceBookings > 0 ? round(($svc['count'] / $totalServiceBookings) * 100, 1) : 0;
    $svc['expert'] = $serviceExperts[$svc['service_name']] ?? 'N/A';
}
unset($svc);

// Service Combo Analysis
$comboSQL = "
    SELECT 
        CONCAT(s1.service_name, ' + ', s2.service_name) as combo_name,
        COUNT(*) as frequency
    FROM appointmentitem ai1
    JOIN appointmentitem ai2 ON ai1.appointment_id = ai2.appointment_id AND ai1.service_id < ai2.service_id
    JOIN service s1 ON ai1.service_id = s1.service_id
    JOIN service s2 ON ai2.service_id = s2.service_id
    JOIN appointment a ON ai1.appointment_id = a.appointment_id
    $filterSql
    GROUP BY s1.service_id, s2.service_id
    ORDER BY frequency DESC
    LIMIT 5
";
$comboStmt = $pdo->prepare($comboSQL);
$comboStmt->execute($queryParams);
$topCombos = $comboStmt->fetchAll(PDO::FETCH_ASSOC);

// Previous Period (for Growth)
$startTs = strtotime($startDate ?: date('Y-m-01'));
$endTs = strtotime($endDate ?: date('Y-m-d'));
$duration = $endTs - $startTs;
$prevStart = date('Y-m-d', $startTs - $duration - 86400);
$prevEnd = date('Y-m-d', $startTs - 86400);

$prevParams = [];
$prevFilter = " WHERE 1=1 ";
if ($startDate !== '') {
    $prevFilter .= " AND a.appointment_date >= :pstart AND a.appointment_date <= :pend";
    $prevParams[':pstart'] = $prevStart;
    $prevParams[':pend'] = $prevEnd;
}

$prevSvcSQL = "
    SELECT COUNT(*) 
    FROM appointmentitem ai
    JOIN appointment a ON ai.appointment_id = a.appointment_id
    $prevFilter
";
$stmt = $pdo->prepare($prevSvcSQL);
$stmt->execute($prevParams);
$prevServiceBookings = $stmt->fetchColumn();

$svcGrowth = 0;
if ($prevServiceBookings > 0) {
    $svcGrowth = (($totalServiceBookings - $prevServiceBookings) / $prevServiceBookings) * 100;
} else {
    $svcGrowth = $totalServiceBookings > 0 ? 100 : 0;
}

// Service Performance & Stylist Affinity
$affinitySQL = "
    SELECT s.service_id, s.service_name, st.user_id, su.name as stylist_name, COUNT(*) as count
    FROM appointmentitem ai
    JOIN appointment a ON ai.appointment_id = a.appointment_id
    JOIN service s ON ai.service_id = s.service_id
    JOIN stylist st ON a.stylist_id = st.stylist_id
    JOIN user su ON st.user_id = su.user_id
    $filterSql
    GROUP BY s.service_id, st.stylist_id
    ORDER BY s.service_id, count DESC
";
$stmt = $pdo->prepare($affinitySQL);
$stmt->execute($queryParams);
$affinityRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Map Service -> Top Stylist
$serviceExperts = [];
foreach ($affinityRaw as $row) {
    if (!isset($serviceExperts[$row['service_name']])) {
        $serviceExperts[$row['service_name']] = $row['stylist_name']; // First one is max due to Sort
    }
}

// Main Service List
$serviceListSQL = "
    SELECT s.service_name, COUNT(*) as count, SUM(ai.service_price) as revenue
    FROM appointmentitem ai
    JOIN appointment a ON ai.appointment_id = a.appointment_id
    JOIN service s ON ai.service_id = s.service_id
    $filterSql
    GROUP BY s.service_id
    ORDER BY count DESC
";
$serviceListStmt = $pdo->prepare($serviceListSQL);
$serviceListStmt->execute($queryParams);
$allServiceStats = $serviceListStmt->fetchAll(PDO::FETCH_ASSOC);

// Top 5 & Bottom 5 from sorted list
$top5Services = array_slice($allServiceStats, 0, 5);
$bottom5Services = array_slice($allServiceStats, -5);
// If list is small, bottom 5 might overlap top 5, but that's acceptable for display
if (count($allServiceStats) < 5)
    $bottom5Services = [];

// Data for Chart
$serviceChartData = array_slice($allServiceStats, 0, 10); // Top 10 for chart
$mostBooked = $allServiceStats[0] ?? ['service_name' => 'N/A', 'count' => 0];
$leastBooked = end($allServiceStats) ?: ['service_name' => 'N/A', 'count' => 0];

// Calculate Share %
foreach ($top5Services as &$svc) {
    $svc['share'] = $totalServiceBookings > 0 ? round(($svc['count'] / $totalServiceBookings) * 100, 1) : 0;
    $svc['expert'] = $serviceExperts[$svc['service_name']] ?? 'N/A';
}
foreach ($bottom5Services as &$svc) {
    $svc['share'] = $totalServiceBookings > 0 ? round(($svc['count'] / $totalServiceBookings) * 100, 1) : 0;
    $svc['expert'] = $serviceExperts[$svc['service_name']] ?? 'N/A';
}
unset($svc);

// Service Combo Analysis
$comboSQL = "
    SELECT 
        CONCAT(s1.service_name, ' + ', s2.service_name) as combo_name,
        COUNT(*) as frequency
    FROM appointmentitem ai1
    JOIN appointmentitem ai2 ON ai1.appointment_id = ai2.appointment_id AND ai1.service_id < ai2.service_id
    JOIN service s1 ON ai1.service_id = s1.service_id
    JOIN service s2 ON ai2.service_id = s2.service_id
    JOIN appointment a ON ai1.appointment_id = a.appointment_id
    $filterSql
    GROUP BY s1.service_id, s2.service_id
    ORDER BY frequency DESC
    LIMIT 5
";
$comboStmt = $pdo->prepare($comboSQL);
$comboStmt->execute($queryParams);
$topCombos = $comboStmt->fetchAll(PDO::FETCH_ASSOC);


/* -----------------------
   SUMMARY STATS
------------------------ */
$statsSQL = "SELECT
    COUNT(*) as total
FROM appointment a
$filterSql";

$statsStmt = $pdo->prepare($statsSQL);
foreach ($queryParams as $key => $value) {
    $statsStmt->bindValue($key, $value);
}
$statsStmt->execute();
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

/* -----------------------
   MOST FREQUENT CUSTOMER
------------------------ */
$topCustomerSQL = "
    SELECT u.name, COUNT(*) AS visit_count
    FROM appointment a
    INNER JOIN user u ON a.user_id = u.user_id
    $filterSql
    GROUP BY a.user_id
    ORDER BY visit_count DESC
    LIMIT 1
";
$topCustomerStmt = $pdo->prepare($topCustomerSQL);
foreach ($queryParams as $key => $value) {
    $topCustomerStmt->bindValue($key, $value);
}
$topCustomerStmt->execute();
$topCustomer = $topCustomerStmt->fetch(PDO::FETCH_ASSOC);

/* -----------------------
   MOST BOUGHT SERVICE
------------------------ */
$topServiceSQL = "
    SELECT s.service_name, COUNT(*) AS times_bought
    FROM appointmentitem ai
    INNER JOIN service s ON ai.service_id = s.service_id
    INNER JOIN appointment a ON a.appointment_id = ai.appointment_id
    $filterSql
    GROUP BY ai.service_id
    ORDER BY times_bought DESC
    LIMIT 1
";
$topServiceStmt = $pdo->prepare($topServiceSQL);
foreach ($queryParams as $key => $value) {
    $topServiceStmt->bindValue($key, $value);
}
$topServiceStmt->execute();
$topService = $topServiceStmt->fetch(PDO::FETCH_ASSOC);

/* -----------------------
   MOST HIRED STYLIST
------------------------ */
$topStylistSQL = "
    SELECT su.name AS stylist_name, COUNT(*) AS hired_count
    FROM appointment a
    INNER JOIN stylist st ON a.stylist_id = st.stylist_id
    INNER JOIN user su ON st.user_id = su.user_id
    $filterSql
    GROUP BY a.stylist_id
    ORDER BY hired_count DESC
    LIMIT 1
";
$topStylistStmt = $pdo->prepare($topStylistSQL);
foreach ($queryParams as $key => $value) {
    $topStylistStmt->bindValue($key, $value);
}
$topStylistStmt->execute();
$topStylist = $topStylistStmt->fetch(PDO::FETCH_ASSOC);

/* -----------------------
   PIE CHART DATA
------------------------ */
$serviceChartSQL = "
    SELECT s.service_name, COUNT(*) AS count
    FROM appointmentitem ai
    INNER JOIN service s ON ai.service_id = s.service_id
    INNER JOIN appointment a ON a.appointment_id = ai.appointment_id
    $filterSql
    GROUP BY ai.service_id
";
$serviceChartStmt = $pdo->prepare($serviceChartSQL);
foreach ($queryParams as $key => $value) {
    $serviceChartStmt->bindValue($key, $value);
}
$serviceChartStmt->execute();
$serviceChartData = $serviceChartStmt->fetchAll(PDO::FETCH_ASSOC);

/* -----------------------
   LEADERBOARD DATA (Top 10)
------------------------ */
$leaderCustomerSQL = "
    SELECT u.name, COUNT(*) AS visit_count
    FROM appointment a
    INNER JOIN user u ON a.user_id = u.user_id
    $filterSql
    GROUP BY a.user_id
    ORDER BY visit_count DESC
    LIMIT 10
";
$leaderCustomerStmt = $pdo->prepare($leaderCustomerSQL);
foreach ($queryParams as $key => $value) {
    $leaderCustomerStmt->bindValue($key, $value);
}
$leaderCustomerStmt->execute();
$leaderCustomer = $leaderCustomerStmt->fetchAll(PDO::FETCH_ASSOC);

$leaderServiceSQL = "
    SELECT s.service_name, COUNT(*) AS times_bought
    FROM appointmentitem ai
    INNER JOIN service s ON ai.service_id = s.service_id
    INNER JOIN appointment a ON a.appointment_id = ai.appointment_id
    $filterSql
    GROUP BY ai.service_id
    ORDER BY times_bought DESC
    LIMIT 10
";
$leaderServiceStmt = $pdo->prepare($leaderServiceSQL);
foreach ($queryParams as $key => $value) {
    $leaderServiceStmt->bindValue($key, $value);
}
$leaderServiceStmt->execute();
$leaderService = $leaderServiceStmt->fetchAll(PDO::FETCH_ASSOC);

$leaderStylistSQL = "
    SELECT su.name AS stylist_name, COUNT(*) AS hired_count
    FROM appointment a
    INNER JOIN stylist st ON a.stylist_id = st.stylist_id
    INNER JOIN user su ON st.user_id = su.user_id
    $filterSql
    GROUP BY a.stylist_id
    ORDER BY hired_count DESC
    LIMIT 10
";
$leaderStylistStmt = $pdo->prepare($leaderStylistSQL);
foreach ($queryParams as $key => $value) {
    $leaderStylistStmt->bindValue($key, $value);
}
$leaderStylistStmt->execute();
$leaderStylist = $leaderStylistStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
    .tab-content {
        display: none;
    }

    .tab-content.active {
        display: block;
        animation: fadeIn 0.5s;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
        }

        to {
            opacity: 1;
        }
    }

    .report-tabs {
        display: flex;
        gap: 10px;
        margin-bottom: 20px;
        border-bottom: 2px solid #e5e7eb;
        padding-bottom: 10px;
    }

    .tab-btn {
        padding: 10px 20px;
        border: none;
        background: transparent;
        cursor: pointer;
        font-size: 16px;
        font-weight: 600;
        color: #6b7280;
        border-radius: 5px;
        transition: all 0.3s;
    }

    .tab-btn:hover {
        background: #f3f4f6;
        color: #7c3aed;
    }

    .tab-btn.active {
        background: #7c3aed;
        color: white;
    }

    #serviceChart {
        max-width: 600px;
        margin: 20px auto;
    }

    #appointmentTrendChart {
        width: 100%;
        margin-top: 20px;
    }
</style>

<div class="main-content">

    <h2>Business Intelligence Dashboard</h2>

    <div class="report-tabs">
        <button type="button" class="tab-btn active" data-tab="appointments">Appointments</button>
        <button type="button" class="tab-btn" data-tab="customers">Customers</button>
        <button type="button" class="tab-btn" data-tab="stylists">Stylists</button>
        <button type="button" class="tab-btn" data-tab="services">Services</button>
    </div>

    <div class="export-buttons" style="margin-bottom: 20px; gap: 10px; display: flex;">
        <button type="button" id="exportPDF" class="btn-primary" style="background: #ef4444;"><i
                class="fas fa-file-pdf"></i> PDF</button>
        <button type="button" id="exportExcel" class="btn-primary" style="background: #10b981;"><i
                class="fas fa-file-excel"></i> Excel</button>
        <button type="button" id="exportCSV" class="btn-primary" style="background: #f59e0b;"><i
                class="fas fa-file-csv"></i> CSV (Active Tab)</button>
    </div>

    <!-- FILTER FORM -->
    <form method="GET" class="report-filter">
        <label>Start Date:</label>
        <input type="date" name="start" value="<?= htmlspecialchars($startDate ?? '') ?>">

        <label>End Date:</label>
        <input type="date" name="end" value="<?= htmlspecialchars($endDate ?? '') ?>">

        <label>Status:</label>
        <select name="status">
            <?php
            $statuses = ['all', 'Confirmed', 'Cancelled'];
            foreach ($statuses as $s):
                $sel = ($status ?? '') == $s ? 'selected' : '';
                ?>
                <option value="<?= $s ?>" <?= $sel ?>><?= $s ?></option>
            <?php endforeach; ?>
        </select>

        <label>Period:</label>
        <select name="period">
            <?php
            $periods = ['daily', 'weekly', 'monthly'];
            foreach ($periods as $p):
                $sel = ($period ?? 'daily') == $p ? 'selected' : '';
                ?>
                <option value="<?= $p ?>" <?= $sel ?>><?= ucfirst($p) ?></option>
            <?php endforeach; ?>
        </select>

        <button type="submit">Generate</button>
        <input type="hidden" name="tab" id="activeTabInput"
            value="<?= htmlspecialchars($_GET['tab'] ?? 'appointments') ?>">
    </form>

    <!-- TAB CONTENTS -->
    <div id="appointments" class="tab-content active">

        <!-- NEW INSIGHTS SECTION -->
        <div id="appointmentInsights" style="margin-bottom: 30px;">
            <h3>Key Highlights</h3>
            <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
                <div class="stat-card" style="border-left: 4px solid #8b5cf6;">
                    Peak Hour<br>
                    <b style="font-size: 1.5em; color: #8b5cf6;"><?= $peakHourDisplay ?></b>
                </div>
                <div class="stat-card" style="border-left: 4px solid #10b981;">
                    Busiest <?= ucfirst($period) ?><br>
                    <span style="font-size: 0.9em; color: #555;"><?= $busiestPeriod['label'] ?></span><br>
                    <b><?= $busiestPeriod['count'] ?> Appts</b>
                </div>
                <div class="stat-card" style="border-left: 4px solid #ef4444;">
                    Most Cancelled<br>
                    <span style="font-size: 0.9em; color: #555;"><?= $mostCancelledPeriod['label'] ?></span><br>
                    <b><?= $mostCancelledPeriod['count'] ?> Cancels</b>
                </div>
            </div>
        </div>

        <h3>Appointment Summary</h3>
        <div class="stats-grid" id="appointmentStats">
            <div class="stat-card">
                Total<br><b><?= $appointmentStats['total'] ?? 0 ?></b>
            </div>
            <div class="stat-card">
                Confirmed<br><b><?= $appointmentStats['confirmed'] ?? 0 ?></b>
                <div style="font-size: 0.8em; color: #3b82f6; margin-top: 5px;"><?= $confirmedRate ?>% Rate</div>
            </div>
            <div class="stat-card">
                Cancelled<br><b><?= $appointmentStats['cancelled'] ?? 0 ?></b>
                <div style="font-size: 0.8em; color: #ef4444; margin-top: 5px;"><?= $cancellationRate ?>% Rate</div>
            </div>

        </div>


        <!-- Compact Charts Grid -->
        <div
            style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px; margin-bottom: 20px;">

            <!-- Status Distribution -->
            <div style="background: #f9fafb; padding: 10px; border-radius: 8px;">
                <h4 style="margin: 0 0 10px 0; font-size: 1rem;">Status Dist.</h4>
                <canvas id="apptStatusChart" data-cancelled="<?= $appointmentStats['cancelled'] ?? 0 ?>"
                    data-confirmed="<?= $appointmentStats['confirmed'] ?? 0 ?>">
                </canvas>
            </div>



            <!-- Cancellation Trend -->
            <div style="background: #f9fafb; padding: 10px; border-radius: 8px;">
                <h4 style="margin: 0 0 10px 0; font-size: 1rem;">Cancel Trend</h4>
                <canvas id="cancellationTrendChart"
                    data-labels='<?= json_encode(array_column($appointmentTrend ?? [], "period_label")) ?>'
                    data-values='<?= json_encode(array_column($appointmentTrend ?? [], "cancelled")) ?>'>
                </canvas>
            </div>

            <!-- Duration Utilization -->
            <div style="background: #f9fafb; padding: 10px; border-radius: 8px;">
                <h4 style="margin: 0 0 10px 0; font-size: 1rem;">Booked Hours</h4>
                <canvas id="durationChart"
                    data-labels='<?= json_encode(array_column($durationData ?? [], "period_label")) ?>'
                    data-values='<?= json_encode(array_column($durationData ?? [], "total_minutes")) ?>'>
                </canvas>
            </div>
        </div>

        <h3>Appointment Trends</h3>
        <p> </p>

        <canvas id="appointmentTrendChart"
            data-labels='<?= json_encode(array_column($appointmentTrend ?? [], "period_label")) ?>'
            data-cancelled='<?= json_encode(array_column($appointmentTrend ?? [], "cancelled")) ?>'
            data-confirmed='<?= json_encode(array_column($appointmentTrend ?? [], "confirmed")) ?>'>
        </canvas>

    </div>

    <div id="customers" class="tab-content">
        <h3>Customer Insights</h3>

        <!-- NEW METRICS -->
        <div class="stats-grid" id="customerStats">
            <div class="stat-card">
                Total Customers<br><b><?= $totalCustomers ?? 0 ?></b>
            </div>
            <div class="stat-card">
                Retention Rate<br><b><?= $retentionRate ?>%</b>
                <div style="font-size: 0.8em; color: #666; margin-top: 5px;">Returned >1 time</div>
            </div>
            <div class="stat-card">
                Dormant (Churn Risk)<br><b><?= $dormant ?></b>
                <div style="font-size: 0.8em; color: #ef4444; margin-top: 5px;"><?= $churnRate ?>% Inactive >90d
                </div>
            </div>
            <div class="stat-card">
                High Value<br><b><?= $highValue ?></b>
                <div style="font-size: 0.8em; color: #8b5cf6; margin-top: 5px;">Freq. Visitors (>5)</div>
            </div>
        </div>

        <div style="display: flex; flex-wrap: wrap; gap: 30px; margin-top: 30px;">
            <!-- GROWTH CHART -->
            <div style="flex: 2; min-width: 300px;">
                <h4>Acquisition Growth (New Customers)</h4>
                <canvas id="customerGrowthChart"
                    data-labels='<?= json_encode(array_column($customerGrowth ?? [], "period_label")) ?>'
                    data-values='<?= json_encode(array_column($customerGrowth ?? [], "new_count")) ?>'>
                </canvas>
            </div>

            <!-- SEGMENT CHART -->
            <div style="flex: 1; min-width: 300px;">
                <h4>Customer Segments</h4>
                <canvas id="customerSegmentChart" data-high='<?= $highValue ?>' data-regular='<?= $regular ?>'
                    data-onetime='<?= $oneTime ?>' data-dormant='<?= $dormant ?>'>
                </canvas>
            </div>
        </div>

        <h4 style="margin-top: 30px;">Top Leaderboard</h4>
        <table class="leaderboard-table" id="topCustomers">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Visits</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($leaderCustomer)): ?>
                    <?php foreach ($leaderCustomer as $i => $c): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><?= htmlspecialchars($c['name']) ?></td>
                            <td><?= $c['visit_count'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3">No data</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div id="stylists" class="tab-content">
        <h3>Stylist Performance</h3>

        <div class="stats-grid" id="stylistStats">
            <div class="stat-card">
                Top Performer<br>
                <b style="color: #10b981;"><?= htmlspecialchars($topPerformer['name']) ?></b>
                <div style="font-size: 0.8em; color: #666; margin-top: 5px;">RM
                    <?= number_format($topPerformer['revenue'], 2) ?> Revenue
                </div>
            </div>
            <div class="stat-card">
                Needs Attention<br>
                <b style="color: #ef4444;"><?= htmlspecialchars($underPerformer['name']) ?></b>
                <div style="font-size: 0.8em; color: #666; margin-top: 5px;"><?= $underPerformer['appts'] ?> Appts
                </div>
            </div>
            <div class="stat-card">
                Avg Daily <br><b><?= round($totalApptsGlobal / $daysInPeriod, 1) ?></b>
                <div style="font-size: 0.8em; color: #666; margin-top: 5px;">Appointments</div>
            </div>
        </div>

        <div style="display: flex; flex-wrap: wrap; gap: 30px; margin-top: 30px;">
            <!-- WORKLOAD CHART -->
            <div style="flex: 2; min-width: 300px;">
                <h4>Workload Distribution</h4>
                <canvas id="stylistBarChart"
                    data-labels='<?= json_encode(array_column($stylistReport, "stylist_name")) ?>'
                    data-values='<?= json_encode(array_column($stylistReport, "total_appts")) ?>'>
                </canvas>
            </div>

            <!-- SHARE CHART -->
            <div style="flex: 1; min-width: 300px;">
                <h4>Market Share</h4>
                <canvas id="stylistPieChart"
                    data-labels='<?= json_encode(array_column($stylistReport, "stylist_name")) ?>'
                    data-values='<?= json_encode(array_column($stylistReport, "share")) ?>'>
                </canvas>
            </div>
        </div>

        <h4 style="margin-top: 30px;">Detailed Performance</h4>
        <table class="leaderboard-table" id="stylistTable">
            <thead>
                <tr>
                    <th>Stylist</th>
                    <th>Appts</th>
                    <th>Share</th>
                    <th>Utilization</th>
                    <th>Avg/Day</th>
                    <th>Service Revenue</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($stylistReport as $s): ?>
                    <tr>
                        <td><?= htmlspecialchars($s['stylist_name']) ?></td>
                        <td><?= $s['total_appts'] ?></td>
                        <td>
                            <div
                                style="background: #e5e7eb; border-radius: 4px; overflow: hidden; height: 8px; width: 80px; display: inline-block; margin-right: 5px;">
                                <div style="width: <?= $s['share'] ?>%; background: #7c3aed; height: 100%;"></div>
                            </div>
                            <?= $s['share'] ?>%
                        </td>
                        <td>
                            <span
                                style="color: <?= $s['utilization'] > 80 ? '#ef4444' : ($s['utilization'] < 30 ? '#f59e0b' : '#10b981') ?>">
                                <?= $s['utilization'] ?>%
                            </span>
                        </td>
                        <td><?= $s['avg_per_day'] ?></td>
                        <td>RM <?= number_format($s['total_revenue'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div id="services" class="tab-content">
        <h3>Service Intelligence</h3>

        <!-- CARDS -->
        <div class="stats-grid" id="serviceStats">
            <div class="stat-card">
                Total Bookings<br><b><?= $totalServiceBookings ?></b>
                <div style="font-size: 0.8em; color: #666; margin-top: 5px;">Across all categories</div>
            </div>
            <div class="stat-card">
                Growth Rate<br>
                <b style="color: <?= $svcGrowth >= 0 ? '#10b981' : '#ef4444' ?>">
                    <?= $svcGrowth > 0 ? '+' : '' ?><?= number_format($svcGrowth, 1) ?>%
                </b>
                <div style="font-size: 0.8em; color: #666; margin-top: 5px;">vs Previous Period</div>
            </div>
            <div class="stat-card">
                Top Combo<br>
                <b style="font-size: 1em; color: #7c3aed;"><?= $topCombos[0]['combo_name'] ?? 'None' ?></b>
                <div style="font-size: 0.8em; color: #666; margin-top: 5px;"><?= $topCombos[0]['frequency'] ?? 0 ?>
                    times</div>
            </div>
        </div>

        <div style="display: flex; flex-wrap: wrap; gap: 30px; margin-top: 30px;">
            <!-- TOP 5 TABLE -->
            <div style="flex: 1; min-width: 300px;">
                <h4 style="color: #10b981;">Top 5 Performing Services</h4>
                <table class="leaderboard-table" id="topServicesTable">
                    <thead>
                        <tr>
                            <th>Service</th>
                            <th>Bookings</th>
                            <th>Share</th>
                            <th>Top Stylist</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($top5Services as $s): ?>
                            <tr>
                                <td><?= htmlspecialchars($s['service_name']) ?></td>
                                <td><?= $s['count'] ?></td>
                                <td><?= $s['share'] ?>%</td>
                                <td><?= htmlspecialchars($s['expert']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($top5Services)): ?>
                            <tr>
                                <td colspan="4">No data</td>
                            </tr><?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- PIE CHART -->
            <div style="flex: 1; min-width: 300px; max-width: 400px; margin: 0 auto;">
                <h4 style="text-align: center;">Popularity Distribution</h4>
                <canvas id="serviceChart"
                    data-labels='<?= json_encode(array_column($serviceChartData ?? [], "service_name")) ?>'
                    data-values='<?= json_encode(array_column($serviceChartData ?? [], "count")) ?>'>
                </canvas>
            </div>
        </div>

        <div style="display: flex; flex-wrap: wrap; gap: 30px; margin-top: 30px;">
            <!-- BOTTOM 5 TABLE -->
            <div style="flex: 1; min-width: 300px;">
                <h4 style="color: #f59e0b;">Growth Opportunities (Bottom 5)</h4>
                <table class="leaderboard-table" id="bottomServicesTable">
                    <thead>
                        <tr>
                            <th>Service</th>
                            <th>Bookings</th>
                            <th>Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bottom5Services as $s): ?>
                            <tr>
                                <td><?= htmlspecialchars($s['service_name']) ?></td>
                                <td><?= $s['count'] ?></td>
                                <td>RM <?= number_format($s['revenue'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($bottom5Services)): ?>
                            <tr>
                                <td colspan="3">No data</td>
                            </tr><?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- COMBOS TABLE -->
            <div style="flex: 1; min-width: 300px;">
                <h4 style="color: #6366f1;">Popular Combinations</h4>
                <table class="leaderboard-table" id="comboTable">
                    <thead>
                        <tr>
                            <th>Service Pair</th>
                            <th>Frequency</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topCombos as $c): ?>
                            <tr>
                                <td><?= htmlspecialchars($c['combo_name']) ?></td>
                                <td><b><?= $c['frequency'] ?></b></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($topCombos)): ?>
                            <tr>
                                <td colspan="2">No pairs found</td>
                            </tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

</div>


<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>
<script src="/salonsystem/js/report.js?v=<?= time() ?>"></script>