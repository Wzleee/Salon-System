<?php
require_once '../config.php';
header('Content-Type: application/json');

// Set timezone to Malaysia
date_default_timezone_set('Asia/Kuala_Lumpur');

$data = json_decode(file_get_contents('php://input'), true);
$date = $data['date'] ?? null;
$stylist_id = $data['stylist_id'] ?? null;
$duration = isset($data['duration']) ? (int)$data['duration'] : 0;
$serviceIds = isset($data['service_ids']) && is_array($data['service_ids']) ? array_map('intval', $data['service_ids']) : [];
$appointmentId = isset($data['appointment_id']) ? (int)$data['appointment_id'] : null;
$dayOfWeek = $date ? date('l', strtotime($date)) : null;

if (!$date || !$stylist_id) {
    echo json_encode([]);
    exit;
}

// Check if the selected date is today
$today = date('Y-m-d');
$isToday = ($date === $today);

// Holiday check: block bookings on holidays (including recurring).
try {
    $holidayStmt = $pdo->prepare("SELECT 1 FROM holiday WHERE holiday_date = :date OR (is_recurring = 1 AND DATE_FORMAT(holiday_date, '%m-%d') = DATE_FORMAT(:date, '%m-%d')) LIMIT 1");
    $holidayStmt->execute([':date' => $date]);
    if ($holidayStmt->fetchColumn()) {
        echo json_encode([]);
        exit;
    }
} catch (PDOException $e) {
    // Ignore holiday lookup failure to avoid blocking bookings unexpectedly.
}

// Calculate duration from services or existing appointment
try {
    if (!empty($serviceIds)) {
        $placeholders = implode(',', array_fill(0, count($serviceIds), '?'));
        $stmt = $pdo->prepare("SELECT SUM(duration_minutes) AS total_minutes FROM service WHERE service_id IN ($placeholders)");
        $stmt->execute($serviceIds);
        $duration = (int)$stmt->fetchColumn();
    } elseif ($appointmentId) {
        $stmt = $pdo->prepare("
            SELECT SUM(s.duration_minutes) AS total_minutes
            FROM appointmentitem ai
            JOIN service s ON ai.service_id = s.service_id
            WHERE ai.appointment_id = :appointment_id
        ");
        $stmt->execute([':appointment_id' => $appointmentId]);
        $duration = (int)$stmt->fetchColumn();
    }
} catch (PDOException $e) {
    // Ignore duration calculation failure
}

// Default duration if still zero or negative
if ($duration <= 0) {
    $duration = 60;
}

// Get business hours for the day
$businessHoursStmt = $pdo->prepare("
    SELECT opening_time, closing_time, is_closed 
    FROM businesshours 
    WHERE day_of_week = :day
");
$businessHoursStmt->execute([':day' => $dayOfWeek]);
$biz = $businessHoursStmt->fetch(PDO::FETCH_ASSOC);

if (!$biz || (int)$biz['is_closed'] === 1) {
    echo json_encode([]);
    exit;
}

// Get stylist schedule for the day
$sql = "SELECT start_time, end_time, break_start, break_end, is_available 
        FROM schedule 
        WHERE stylist_id = :stylist_id AND day_of_week = :day_of_week";
$stmt = $pdo->prepare($sql);
$stmt->execute([':stylist_id' => $stylist_id, ':day_of_week' => $dayOfWeek]);
$schedule = $stmt->fetch(PDO::FETCH_ASSOC);

// Determine effective working hours
$start = strtotime($biz['opening_time']);
$end = strtotime($biz['closing_time']);
$breakStart = null;
$breakEnd = null;

if ($schedule) {
    if ((int)$schedule['is_available'] !== 1) {
        echo json_encode([]);
        exit;
    }
    $sStart = strtotime($schedule['start_time']);
    $sEnd = strtotime($schedule['end_time']);
    if ($sStart !== false && $sEnd !== false && $sStart < $sEnd) {
        $start = max($start, $sStart);
        $end = min($end, $sEnd);
    }
    $breakStart = $schedule['break_start'] ? strtotime($schedule['break_start']) : null;
    $breakEnd = $schedule['break_end'] ? strtotime($schedule['break_end']) : null;
}

if ($start === false || $end === false || $start >= $end) {
    echo json_encode([]);
    exit;
}

// Get existing appointments for this stylist on this date
$bookedSql = "SELECT appointment_id, appointment_time, 
        (SELECT SUM(s.duration_minutes) 
         FROM appointmentitem ai 
         JOIN service s ON ai.service_id = s.service_id 
         WHERE ai.appointment_id = a.appointment_id) as total_duration
        FROM appointment a
        WHERE stylist_id = :stylist_id 
        AND appointment_date = :date 
        AND status <> 'Cancelled'";
if ($appointmentId) {
    $bookedSql .= " AND appointment_id <> :exclude_id";
}
$stmt = $pdo->prepare($bookedSql);
$stmt->bindValue(':stylist_id', $stylist_id);
$stmt->bindValue(':date', $date);
if ($appointmentId) {
    $stmt->bindValue(':exclude_id', $appointmentId, PDO::PARAM_INT);
}
$stmt->execute();
$bookedSlots = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Generate available slots
$slots = [];
$current = $start;
$currentTime = time(); // Get current timestamp

while ($current < $end) {
    $timeStr = date('H:i', $current);
    $slotEnd = $current + ($duration * 60);
    
    // **NEW: Skip past time slots if booking for today**
    if ($isToday) {
        // Create full datetime for this slot
        $slotDateTime = strtotime($date . ' ' . $timeStr);
        
        // Skip if this time has already passed
        if ($slotDateTime <= $currentTime) {
            $current += 1800;
            continue;
        }
    }
    
  // Check if slot is during break or overlaps with break time
    $isDuringBreak = false;
    if ($breakStart && $breakEnd) {
        
        $isDuringBreak = ($current >= $breakStart && $current < $breakEnd) ||
                        ($slotEnd > $breakStart && $slotEnd <= $breakEnd) ||
                        ($current < $breakStart && $slotEnd > $breakStart);
    }
    
    // Check if slot is booked
    $isBooked = false;
    foreach ($bookedSlots as $booked) {
        $bookedStart = strtotime($booked['appointment_time']);
        $bookedDuration = (int)$booked['total_duration'] ?: 0;
        $bookedEnd = $bookedStart + ($bookedDuration * 60);
        
        if (($current >= $bookedStart && $current < $bookedEnd) ||
            ($slotEnd > $bookedStart && $slotEnd <= $bookedEnd) ||
            ($current <= $bookedStart && $slotEnd >= $bookedEnd)) {
            $isBooked = true;
            break;
        }
    }
    
    if (!$isDuringBreak && !$isBooked && $slotEnd <= $end) {
        $slots[] = [
            'time' => $timeStr,
            'booked' => false
        ];
    } elseif ($isBooked) {
        $slots[] = [
            'time' => $timeStr,
            'booked' => true
        ];
    }
    
    $current += 1800; // 30 minutes
}

echo json_encode($slots);
