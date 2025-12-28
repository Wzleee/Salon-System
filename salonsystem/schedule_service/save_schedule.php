<?php
require_once '../config.php';
require_once '../users_management/auth_check.php';
requireLogin(['Admin']);
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);


if (!$input || empty($input['stylist_id']) || empty($input['day_of_week'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$stylistId = (int)$input['stylist_id'];
$day = $input['day_of_week'];
$isAvailable = isset($input['is_available']) ? (int)$input['is_available'] : 1;
$start = $input['start_time'] ?? '';
$end = $input['end_time'] ?? '';
$breakStart = $input['break_start'] ?? '';
$breakEnd = $input['break_end'] ?? '';
$applyScope = $input['apply_scope'] ?? 'date'; // 'date' or 'weekly'
$overrideDate = $input['override_date'] ?? null;
$scope = ($applyScope === 'weekly') ? 'weekly' : 'date';

if ($scope === 'date') {
    if (!$overrideDate || !preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $overrideDate) || !strtotime($overrideDate)) {
        echo json_encode(['success' => false, 'message' => 'Override date required for single date updates']);
        exit;
    }
    // derive day of week from override date for consistency
    $day = date('l', strtotime($overrideDate));
}

if ($isAvailable === 1) {
    if (!$start || !$end) {
        echo json_encode(['success' => false, 'message' => 'Start and end time required']);
        exit;
    }
    $startTs = strtotime($start);
    $endTs = strtotime($end);
    if ($startTs === false || $endTs === false) {
        echo json_encode(['success' => false, 'message' => 'Invalid start/end time. Please select a valid time.']);
        exit;
    }
    if ($startTs >= $endTs) {
        echo json_encode(['success' => false, 'message' => 'Invalid time range: Start time must be earlier than End time.']);
        exit;
    }

    
    if ($scope === 'date' && $overrideDate) {
        try {
            $holidayStmt = $pdo->prepare("
                SELECT holiday_name, is_recurring
                FROM holiday
                WHERE holiday_date = :date
                   OR (is_recurring = 1 AND DATE_FORMAT(holiday_date, '%m-%d') = DATE_FORMAT(:date, '%m-%d'))
                LIMIT 1
            ");
            $holidayStmt->execute([':date' => $overrideDate]);
            $holidayRow = $holidayStmt->fetch(PDO::FETCH_ASSOC);
            if ($holidayRow) {
                $holidayName = $holidayRow['holiday_name'] ?? 'Holiday';
                echo json_encode([
                    'success' => false,
                    'message' => "Cannot set working hours: The salon is closed on {$overrideDate} for {$holidayName}. Please mark Off day or remove the holiday."
                ]);
                exit;
            }
        } catch (PDOException $e) {
            // If holiday table/query fails, do not block saving solely due to this check.
        }
    }

    $hoursRow = null;
    $hoursLabel = '';
    $hoursOpen = null;
    $hoursClose = null;

    // Business hours (weekday)
    if (!$hoursRow) {
        try {
            $bizStmt = $pdo->prepare("SELECT opening_time, closing_time, is_closed FROM businesshours WHERE day_of_week = :dow LIMIT 1");
            $bizStmt->execute([':dow' => $day]);
            $hoursRow = $bizStmt->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($hoursRow) {
                $hoursLabel = "business hours for {$day}";
            }
        } catch (PDOException $e) {
            $hoursRow = null;
        }
    }

    if (!$hoursRow) {
        echo json_encode([
            'success' => false,
            'message' => "Business hours are not configured for {$day}. Please set them in the Hours page first."
        ]);
        exit;
    }

    $hoursOpen = isset($hoursRow['opening_time']) ? substr((string)$hoursRow['opening_time'], 0, 5) : null;
    $hoursClose = isset($hoursRow['closing_time']) ? substr((string)$hoursRow['closing_time'], 0, 5) : null;
    $hoursClosed = ((int)($hoursRow['is_closed'] ?? 0) === 1) || ($hoursOpen && $hoursClose && $hoursOpen === $hoursClose);

    if ($hoursClosed) {
        echo json_encode([
            'success' => false,
            'message' => "Cannot set working hours: The salon is marked closed ({$hoursLabel}). Please mark Off day or update the Hours settings."
        ]);
        exit;
    }

    $openTs = $hoursOpen ? strtotime($hoursOpen) : false;
    $closeTs = $hoursClose ? strtotime($hoursClose) : false;
    if ($openTs === false || $closeTs === false || $openTs >= $closeTs) {
        echo json_encode([
            'success' => false,
            'message' => "Cannot validate against {$hoursLabel}: Business hours look invalid. Please update the Hours settings."
        ]);
        exit;
    }

    if ($startTs < $openTs || $endTs > $closeTs) {
        echo json_encode([
            'success' => false,
            'message' => "Invalid time range: Working hours must be within {$hoursLabel} ({$hoursOpen} - {$hoursClose}). You selected {$start} - {$end}."
        ]);
        exit;
    }

    $hasBreakStart = $breakStart !== '';
    $hasBreakEnd = $breakEnd !== '';
    if ($hasBreakStart xor $hasBreakEnd) {
        echo json_encode(['success' => false, 'message' => 'Break time incomplete: Please fill in both Break Start and Break End, or leave both empty.']);
        exit;
    }

    if ($hasBreakStart && $hasBreakEnd) {
        $breakStartTs = strtotime($breakStart);
        $breakEndTs = strtotime($breakEnd);

        if ($breakStartTs === false || $breakEndTs === false) {
            echo json_encode(['success' => false, 'message' => 'Invalid break time. Please select a valid Break Start/End time.']);
            exit;
        }

        if ($breakStartTs >= $breakEndTs) {
            echo json_encode(['success' => false, 'message' => 'Invalid break time: Break End must be later than Break Start.']);
            exit;
        }

        if ($breakStartTs < $startTs) {
            echo json_encode(['success' => false, 'message' => 'Invalid break time: Break Start must be within working hours (after the Start time).']);
            exit;
        }

        if ($breakEndTs > $endTs) {
            echo json_encode(['success' => false, 'message' => 'Invalid break time: Break End must be within working hours (before the End time).']);
            exit;
        }
    }
}


try {
    $pdo->beginTransaction();
    if ($scope === 'date') {
        $existing = $pdo->prepare("SELECT schedule_id FROM schedule WHERE stylist_id = :sid AND schedule_scope = 'date' AND override_date = :ovd LIMIT 1");
        $existing->execute([':sid' => $stylistId, ':ovd' => $overrideDate]);
        $rowId = $existing->fetchColumn();

        if ($rowId) {
            $stmt = $pdo->prepare("
                UPDATE schedule
                   SET day_of_week = :day_of_week,
                       override_date = :override_date,
                       schedule_scope = 'date',
                       start_time = :start_time,
                       end_time = :end_time,
                       break_start = :break_start,
                       break_end = :break_end,
                       is_available = :is_available
                 WHERE schedule_id = :id
            ");
            $stmt->execute([
                ':day_of_week' => $day,
                ':override_date' => $overrideDate,
                ':start_time' => $isAvailable ? $start . ':00' : null,
                ':end_time' => $isAvailable ? $end . ':00' : null,
                ':break_start' => ($isAvailable && $breakStart) ? $breakStart . ':00' : null,
                ':break_end' => ($isAvailable && $breakEnd) ? $breakEnd . ':00' : null,
                ':is_available' => $isAvailable,
                ':id' => $rowId
            ]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO schedule (stylist_id, day_of_week, override_date, schedule_scope, start_time, end_time, break_start, break_end, is_available)
                VALUES (:stylist_id, :day_of_week, :override_date, 'date', :start_time, :end_time, :break_start, :break_end, :is_available)
            ");
            $stmt->execute([
                ':stylist_id' => $stylistId,
                ':day_of_week' => $day,
                ':override_date' => $overrideDate,
                ':start_time' => $isAvailable ? $start . ':00' : null,
                ':end_time' => $isAvailable ? $end . ':00' : null,
                ':break_start' => ($isAvailable && $breakStart) ? $breakStart . ':00' : null,
                ':break_end' => ($isAvailable && $breakEnd) ? $breakEnd . ':00' : null,
                ':is_available' => $isAvailable
            ]);
        }
    } else {
        $existing = $pdo->prepare("SELECT schedule_id FROM schedule WHERE stylist_id = :sid AND day_of_week = :dow AND (schedule_scope = 'weekly' OR schedule_scope IS NULL) LIMIT 1");
        $existing->execute([':sid' => $stylistId, ':dow' => $day]);
        $rowId = $existing->fetchColumn();

        if ($rowId) {
            $stmt = $pdo->prepare("
                UPDATE schedule
                   SET start_time = :start_time,
                       end_time = :end_time,
                       break_start = :break_start,
                       break_end = :break_end,
                       is_available = :is_available,
                       schedule_scope = 'weekly',
                       override_date = NULL
                 WHERE schedule_id = :id
            ");
            $stmt->execute([
                ':start_time' => $isAvailable ? $start . ':00' : null,
                ':end_time' => $isAvailable ? $end . ':00' : null,
                ':break_start' => ($isAvailable && $breakStart) ? $breakStart . ':00' : null,
                ':break_end' => ($isAvailable && $breakEnd) ? $breakEnd . ':00' : null,
                ':is_available' => $isAvailable,
                ':id' => $rowId
            ]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO schedule (stylist_id, day_of_week, schedule_scope, start_time, end_time, break_start, break_end, is_available)
                VALUES (:stylist_id, :day_of_week, 'weekly', :start_time, :end_time, :break_start, :break_end, :is_available)
            ");
            $stmt->execute([
                ':stylist_id' => $stylistId,
                ':day_of_week' => $day,
                ':start_time' => $isAvailable ? $start . ':00' : null,
                ':end_time' => $isAvailable ? $end . ':00' : null,
                ':break_start' => ($isAvailable && $breakStart) ? $breakStart . ':00' : null,
                ':break_end' => ($isAvailable && $breakEnd) ? $breakEnd . ':00' : null,
                ':is_available' => $isAvailable
            ]);
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Schedule updated']);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'DB error: ' . $e->getMessage()]);
}
