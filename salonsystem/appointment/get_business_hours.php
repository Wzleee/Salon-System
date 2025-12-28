<?php
require_once '../config.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$stylist_id = $data['stylist_id'] ?? null;

if (!$stylist_id) {
    echo json_encode(['error' => 'Stylist ID required']);
    exit;
}

// added: Get business hours from businesshours table
$bizStmt = $pdo->query("SELECT day_of_week, opening_time, closing_time, is_closed FROM businesshours");
$bizHours = [];
while ($row = $bizStmt->fetch(PDO::FETCH_ASSOC)) {
    $bizHours[$row['day_of_week']] = $row;
}

// Holidays (one-off and recurring)
$holidayStmt = $pdo->query("SELECT holiday_id, holiday_name, holiday_date, is_recurring FROM holiday");
$holidays = [];
while ($row = $holidayStmt->fetch(PDO::FETCH_ASSOC)) {
    $holidays[] = [
        'holiday_id' => (int)$row['holiday_id'],
        'holiday_name' => $row['holiday_name'],
        'date' => $row['holiday_date'],
        'is_recurring' => (bool)$row['is_recurring'],
        'month_day' => date('m-d', strtotime($row['holiday_date']))
    ];
}

// Get stylist's schedule for all days of the week
$sql = "SELECT day_of_week, start_time, end_time, is_available 
        FROM schedule 
        WHERE stylist_id = :stylist_id";
$stmt = $pdo->prepare($sql);
$stmt->execute([':stylist_id' => $stylist_id]);
$schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

$scheduleByDay = []; //added
foreach ($schedules as $s) {
    $scheduleByDay[$s['day_of_week']] = $s;
}

// Format the response
$businessHours = [];
// added: combine business hours and stylist schedule
$allDays = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
foreach ($allDays as $day) {
    $biz = $bizHours[$day] ?? null;
    if (!$biz || (int)$biz['is_closed'] === 1 || $biz['opening_time'] === $biz['closing_time']) {
        $businessHours[$day] = null;
        continue;
    }
    $sched = $scheduleByDay[$day] ?? null;
    if ($sched) {
        if ((int)$sched['is_available'] !== 1) {
            $businessHours[$day] = null;
            continue;
        }
        $open = max(strtotime($biz['opening_time']), strtotime($sched['start_time']));
        $close = min(strtotime($biz['closing_time']), strtotime($sched['end_time']));
        if ($open === false || $close === false || $open >= $close) {
            $businessHours[$day] = null;
            continue;
        }
        $businessHours[$day] = [
            'open' => date('H:i', $open),
            'close' => date('H:i', $close)
        ];
    } else {
        // added: use business hours if no stylist schedule
        $businessHours[$day] = [
            'open' => substr($biz['opening_time'], 0, 5),
            'close' => substr($biz['closing_time'], 0, 5)
        ];
    }
}

echo json_encode([
    'business_hours' => $businessHours,
    'holidays' => $holidays // added
]);
?>
