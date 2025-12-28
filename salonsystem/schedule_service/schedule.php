<?php
require_once '../config.php'; 
require_once '../users_management/auth_check.php';
requireLogin(['Admin', 'Staff']);

$currentUser = getCurrentUser();
$isAdmin = isAdmin();
$isStaff = isStaff() && !$isAdmin;

$staffStylistId = null;
if ($isStaff) {
    try {
        $stmt = $pdo->prepare("SELECT stylist_id FROM stylist WHERE user_id = ?");
        $stmt->execute([$currentUser['id']]);
        $staffStylistId = $stmt->fetchColumn();
        
        if (!$staffStylistId) {
            die("Error: Your account is not linked to a stylist profile. Please contact admin.");
        }
    } catch (PDOException $e) {
        error_log("Error fetching staff stylist_id: " . $e->getMessage());
        die("Database error occurred.");
    }
}

$currentDate = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$currentView = isset($_GET['view']) ? $_GET['view'] : 'list';
$dayOfWeekName = date('l', strtotime($currentDate)); 


if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $currentDate) || !strtotime($currentDate)) {
    $currentDate = date('Y-m-d');
}


if (!in_array($currentView, ['list', 'calendar'])) {
    $currentView = 'list';
}

$timestamp = strtotime($currentDate);
$prevDate = date('Y-m-d', strtotime('-1 day', $timestamp));
$nextDate = date('Y-m-d', strtotime('+1 day', $timestamp));
$displayDate = date('l, F j, Y', $timestamp);


$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

if ($month < 1 || $month > 12) {
    $month = (int)date('m');
}
if ($year < 2000 || $year > 2100) {
    $year = (int)date('Y');
}

$stylists = [];
$appointmentsRaw = [];
$appointments = [];
$occupiedSlots = [];
$stylistSchedules = []; // save stylist schedules for the day
$businessHours = null;  // save business hours for the day
$allSchedules = [];     // all schedules for reference
$weeklySchedules = [];
$dateOverrides = [];
$hasOverrideToday = [];
$isClosedDay = false;
$closedReason = '';

try {
    if ($isStaff) {
        // Staff (View self)
        $stylistSql = "SELECT s.stylist_id, u.name, s.photo 
                    FROM stylist s 
                    JOIN user u ON s.user_id = u.user_id 
                    WHERE s.stylist_id = ?
                    ORDER BY u.name";
        $stmt = $pdo->prepare($stylistSql);  
        $stmt->execute([$staffStylistId]);
    } else {
        // Admin (View All)
        $stylistSql = "SELECT s.stylist_id, u.name, s.photo 
                       FROM stylist s 
                       JOIN user u ON s.user_id = u.user_id 
                       ORDER BY u.name";
        $stmt = $pdo->query($stylistSql);
    }
    
    $stylists = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($stylists)) {
        $errorMessage = "No stylists found.";
    }
} catch (PDOException $e) {
    error_log("Error fetching stylists: " . $e->getMessage());
    $errorMessage = "Error loading stylist information.";
}

// Holiday check for selected date (supports recurring).
$isHoliday = false;
$holidayName = "";
$holidayIsRecurring = false;
try {
    $holidayStmt = $pdo->prepare("
        SELECT holiday_name, is_recurring 
        FROM holiday 
        WHERE holiday_date = :date 
           OR (is_recurring = 1 AND DATE_FORMAT(holiday_date, '%m-%d') = DATE_FORMAT(:date, '%m-%d'))
        LIMIT 1
    ");
    $holidayStmt->execute([':date' => $currentDate]);
    $holidayRow = $holidayStmt->fetch(PDO::FETCH_ASSOC);
    if ($holidayRow) {
        $isHoliday = true;
        $holidayName = $holidayRow['holiday_name'] ?? 'Holiday';
        $holidayIsRecurring = (bool)$holidayRow['is_recurring'];
    }
} catch (PDOException $e) {
    error_log("Error checking holiday: " . $e->getMessage());
}

// Business hours for the day
try {
    $bizStmt = $pdo->prepare("SELECT opening_time, closing_time, is_closed FROM businesshours WHERE day_of_week = :dow");
    $bizStmt->execute([':dow' => $dayOfWeekName]);
    $businessHours = $bizStmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching business hours: " . $e->getMessage());
    $businessHours = null;
}

$bizOpenTime = null;
$bizCloseTime = null;
if ($isHoliday) {
    $isClosedDay = true;
    $closedReason = 'Closed for ' . $holidayName . ($holidayIsRecurring ? ' (recurring)' : '');
}
if ($businessHours && (int)$businessHours['is_closed'] === 1 && !$isClosedDay) {
    $isClosedDay = true;
    $closedReason = 'Closed (business hours)';
}
if ($isClosedDay) {
    $businessHours = ['opening_time' => '00:00:00', 'closing_time' => '00:00:00', 'is_closed' => 1];
}
if ($businessHours && (int)$businessHours['is_closed'] === 0 && $businessHours['opening_time'] !== $businessHours['closing_time']) {
    $bizOpenTime = $businessHours['opening_time'];
    $bizCloseTime = $businessHours['closing_time'];
}



foreach ($stylists as $sty) {
    if ($bizOpenTime && $bizCloseTime) {
        $stylistSchedules[$sty['stylist_id']] = [
            'stylist_id' => $sty['stylist_id'],
            'start_time' => $bizOpenTime,
            'end_time' => $bizCloseTime,
            'break_start' => null,
            'break_end' => null,
            'is_available' => 1
        ];
    } else {
        $stylistSchedules[$sty['stylist_id']] = [
            'stylist_id' => $sty['stylist_id'],
            'start_time' => null,
            'end_time' => null,
            'break_start' => null,
            'break_end' => null,
            'is_available' => 0
        ];
    }
}

try {
    $allSchedStmt = $pdo->query("SELECT stylist_id, day_of_week, override_date, start_time, end_time, break_start, break_end, is_available, schedule_scope FROM schedule");
    $rows = $allSchedStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        $sid = $row['stylist_id'];
        $scope = $row['schedule_scope'] ?: 'weekly';
        $dow = $row['day_of_week'];
        $ovDate = $row['override_date'];

        if ($scope === 'date' && $ovDate) {
            $dateOverrides[$sid][$ovDate] = $row;
            if ($ovDate === $currentDate) {
                $stylistSchedules[$sid] = [
                    'stylist_id' => $sid,
                    'start_time' => $row['start_time'],
                    'end_time' => $row['end_time'],
                    'break_start' => $row['break_start'],
                    'break_end' => $row['break_end'],
                    'is_available' => (int)$row['is_available'],
                ];
                $hasOverrideToday[$sid] = true;
            }
        } else {
            $weeklySchedules[$sid][$dow] = $row;
            if (!isset($hasOverrideToday[$sid]) && $dow === $dayOfWeekName) {
                $stylistSchedules[$sid] = [
                    'stylist_id' => $sid,
                    'start_time' => $row['start_time'],
                    'end_time' => $row['end_time'],
                    'break_start' => $row['break_start'],
                    'break_end' => $row['break_end'],
                    'is_available' => (int)$row['is_available'],
                ];
            }
        }
    }
} catch (PDOException $e) {
    error_log("Error fetching all schedules: " . $e->getMessage());
    $weeklySchedules = [];
    $dateOverrides = [];
}

try {
    $aptSql = "SELECT 
                a.appointment_id, 
                a.appointment_time, 
                a.total_price,
                a.status,
                a.stylist_id,
                u.name as customer_name,
                GROUP_CONCAT(s.service_name ORDER BY s.service_name SEPARATOR ', ') as services,
                SUM(s.duration_minutes) as total_duration
               FROM appointment a
               JOIN user u ON a.user_id = u.user_id
               LEFT JOIN appointmentitem ai ON a.appointment_id = ai.appointment_id
               LEFT JOIN service s ON ai.service_id = s.service_id
               WHERE a.appointment_date = :date";
    
    if ($isStaff) {
        $aptSql .= " AND a.stylist_id = :stylist_id";
    }
    
    $aptSql .= " GROUP BY a.appointment_id, a.appointment_time, a.total_price, 
                        a.status, a.stylist_id, u.name
               ORDER BY a.appointment_time";
    
    $stmt = $pdo->prepare($aptSql);
     
    $params = [':date' => $currentDate];
    if ($isStaff && $staffStylistId) {
        $params[':stylist_id'] = $staffStylistId;
    }

    $stmt->execute($params);
    $appointmentsRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Rebuild appointments into overlap groups per stylist so overlapping items share one slot
    // Only two statuses are used in this system: Confirmed and Cancelled.
    // Any non-cancelled booking is treated as Confirmed.
    $statusPriority = ['Confirmed' => 1, 'Cancelled' => 2];
    $appointments = [];
    $occupiedSlots = [];

    // Group by stylist first
    $byStylist = [];
    foreach ($appointmentsRaw as $apt) {
        if (empty($apt['appointment_time'])) continue;
        $byStylist[$apt['stylist_id']][] = $apt;
    }

    foreach ($byStylist as $sId => $list) {
        // Sort by start time, then id for stability
        usort($list, function($a, $b) {
            $sa = strtotime($a['appointment_time']);
            $sb = strtotime($b['appointment_time']);
            if ($sa === $sb) {
                return ($a['appointment_id'] ?? 0) <=> ($b['appointment_id'] ?? 0);
            }
            return $sa <=> $sb;
        });

        // Build overlap groups (contiguous overlapping intervals)
        $groups = [];
        foreach ($list as $apt) {
            $start = strtotime($apt['appointment_time']);
            if ($start === false) continue;
            $duration = (int)($apt['total_duration'] ?? 30);
            $end = $start + ($duration * 60);

            $placed = false;
            $lastIdx = count($groups) - 1;
            if ($lastIdx >= 0 && $start < $groups[$lastIdx]['max_end']) {
                $groups[$lastIdx]['members'][] = $apt;
                $groups[$lastIdx]['max_end'] = max($groups[$lastIdx]['max_end'], $end);
                $placed = true;
            }

            if (!$placed) {
                $groups[] = [
                    'start' => $start,
                    'max_end' => $end,
                    'members' => [$apt]
                ];
            }
        }

        foreach ($groups as $g) {
            $members = $g['members'];

            // Pick primary: prefer better status, then earlier start, then newer id
            usort($members, function($a, $b) use ($statusPriority) {
                $aStatus = ($a['status'] === 'Cancelled') ? 'Cancelled' : 'Confirmed';
                $bStatus = ($b['status'] === 'Cancelled') ? 'Cancelled' : 'Confirmed';
                $pa = $statusPriority[$aStatus] ?? 4;
                $pb = $statusPriority[$bStatus] ?? 4;
                if ($pa === $pb) {
                    $sa = strtotime($a['appointment_time']);
                    $sb = strtotime($b['appointment_time']);
                    if ($sa === $sb) {
                        return ($b['appointment_id'] ?? 0) <=> ($a['appointment_id'] ?? 0);
                    }
                    return $sa <=> $sb;
                }
                return $pa <=> $pb;
            });

            $primary = array_shift($members);
            if (!$primary || empty($primary['appointment_time'])) {
                continue;
            }

            $timeKey = substr($primary['appointment_time'], 0, 5);
            $appointments[$timeKey][$sId] = [
                'primary' => $primary,
                'others' => $members
            ];

            // Occupied slots only from the primary card
            $duration = (int)($primary['total_duration'] ?? 30);
            $spanMinutes = ($primary['status'] === 'Cancelled') ? min(30, $duration) : $duration;
            $slots = max(1, ceil($spanMinutes / 30));
            $startTs = strtotime($primary['appointment_time']);
            for ($i = 1; $i < $slots; $i++) {
                $slotTime = date('H:i', strtotime("+".($i * 30)." minutes", $startTs));
                $occupiedSlots[$sId][$slotTime] = $timeKey;
            }
        }
    }
} catch (PDOException $e) {
    error_log("Error fetching appointments: " . $e->getMessage());
    $errorMessage = "Error loading appointments.";
}


$firstDayOfMonth = mktime(0, 0, 0, $month, 1, $year);
$numberDays = (int)date('t', $firstDayOfMonth);
$dateComponents = getdate($firstDayOfMonth);
$calendarDayOfWeek = $dateComponents['wday'];
$monthName = $dateComponents['month'];

$prevMonthDate = strtotime("-1 month", $firstDayOfMonth);
$nextMonthDate = strtotime("+1 month", $firstDayOfMonth);

$pageTitle = 'Schedule Management - Cosmos Salon';
$pageCSS = '../css/schedule.css';
$currentPage = 'schedule';
include '../head.php';
include '../nav.php';
include '../flash_message.php'; // unified flash renderer (server-side)
?>


    <div class="container">
        <?php if (isset($errorMessage)): ?>
            <div class="alert alert-error" style="background: #fee; border-left: 4px solid #f00; padding: 15px; margin-bottom: 20px; border-radius: 4px;">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($errorMessage); ?>
            </div>
        <?php endif; ?>

        <?php if ($isAdmin): ?>
        <!-- IMPROVED: Removed redundant Day of Week field, improved Apply to UX -->
        <div class="edit-schedule-card" style="background:#fff; border:1px solid #e5e7eb; border-radius:10px; padding:16px; margin-bottom:18px; display:flex; flex-wrap:wrap; gap:12px; align-items:flex-end;">
            <div style="min-width:200px;">
                <label style="font-weight:600; font-size:0.9rem;">Stylist</label>
                <select id="editStylist" style="width:100%; padding:8px; border:1px solid #d1d5db; border-radius:6px;">
                    <?php foreach ($stylists as $sty): ?>
                        <option value="<?php echo $sty['stylist_id']; ?>"><?php echo htmlspecialchars($sty['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="min-width:140px;">
                <label style="font-weight:600; font-size:0.9rem;">Start</label>
                <input id="editStart" type="time" style="width:100%; padding:8px; border:1px solid #d1d5db; border-radius:6px;">
            </div>
            <div style="min-width:140px;">
                <label style="font-weight:600; font-size:0.9rem;">End</label>
                <input id="editEnd" type="time" style="width:100%; padding:8px; border:1px solid #d1d5db; border-radius:6px;">
            </div>
            <div style="min-width:140px;">
                <label style="font-weight:600; font-size:0.9rem;">Break Start</label>
                <input id="editBreakStart" type="time" style="width:100%; padding:8px; border:1px solid #d1d5db; border-radius:6px;">
            </div>
            <div style="min-width:140px;">
                <label style="font-weight:600; font-size:0.9rem;">Break End</label>
                <input id="editBreakEnd" type="time" style="width:100%; padding:8px; border:1px solid #d1d5db; border-radius:6px;">
            </div>
            
            <div style="min-width:240px;">
                <label style="font-weight:600; font-size:0.9rem;">Apply to</label>
                <select id="applyScope" style="width:100%; padding:8px; border:1px solid #d1d5db; border-radius:6px;">
                    <option value="date">This date only (<?php echo date('M j, Y', strtotime($currentDate)); ?>)</option>
                    <option value="weekly">Every <?php echo $dayOfWeekName; ?> (recurring)</option>
                </select>
            </div>
            <div style="display:flex; align-items:center; gap:6px; padding-top:22px;">
                <input id="editOff" type="checkbox">
                <label for="editOff" style="font-weight:600;">Off day</label>
            </div>
            <div style="display:flex; gap:8px; padding-top:18px;">
                <button id="loadScheduleBtn" style="padding:9px 12px; border:1px solid #d1d5db; background:#f9fafb; border-radius:6px; cursor:pointer;">Load</button>
                <button id="saveScheduleBtn" style="padding:9px 14px; border:none; background:#7c3aed; color:white; border-radius:6px; cursor:pointer;">Save</button>
            </div>
            <div id="editScheduleMsg" style="flex:1; font-size:0.9rem; color:#374151;"></div>
        </div>
        <?php else: ?>
        <!-- Staff can view only -->
        <div style="background:#e0e7ff; border:1px solid #818cf8; border-radius:10px; padding:16px; margin-bottom:18px;">
            <i class="fas fa-info-circle" style="color:#4f46e5;"></i>
            <strong>View Only Mode</strong> - You are viewing your own schedule. Contact admin to make changes.
        </div>
        <?php endif; ?>
        <div class="section-header">
            <h2>Booking Schedule</h2>
            <div class="view-toggle">
                <button class="view-btn <?php echo $currentView === 'list' ? 'active' : ''; ?>" id="listViewBtn">
                    <i class="far fa-calendar-alt"></i>
                    List View
                </button>
                <button class="view-btn <?php echo $currentView === 'calendar' ? 'active' : ''; ?>" id="calendarGridBtn">
                    <i class="fas fa-th"></i>
                    Calendar Grid
                </button>
            </div>
        </div>

        <!-- LIST VIEW -->
        <div class="schedule-view" id="listView" style="<?php echo $currentView === 'calendar' ? 'display:none;' : ''; ?>">
            <div class="date-navigation">
                <a href="?date=<?php echo urlencode($prevDate); ?>&view=list" class="date-nav-btn" title="Previous day">
                    <i class="fas fa-chevron-left"></i>
                </a>
                <a href="?date=<?php echo date('Y-m-d'); ?>&view=list" class="today-btn">Today</a>
                <a href="?date=<?php echo urlencode($nextDate); ?>&view=list" class="date-nav-btn" title="Next day">
                    <i class="fas fa-chevron-right"></i>
                </a>
                
                <button class="current-date date-picker-trigger" id="datePickerTrigger">
                    <i class="far fa-calendar"></i>
                    <?php echo htmlspecialchars($displayDate); ?>
                    <i class="fas fa-chevron-down" style="font-size: 0.75rem; margin-left: 8px;"></i>
                </button>
            </div>

            <div class="date-picker-overlay" id="datePickerOverlay">
                <div class="date-picker-popup" id="datePickerPopup">
                    <div class="date-picker-header">
                        <button class="date-picker-nav" id="prevMonth">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <div class="date-picker-title" id="pickerMonthYear"></div>
                        <button class="date-picker-nav" id="nextMonth">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                    <div class="date-picker-calendar" id="pickerCalendar">
                    </div>
                </div>
            </div>

            <?php if ($isClosedDay): ?>
            <div class="empty-state" style="text-align: center; padding: 60px 20px; color: #9ca3af;">
                <i class="far fa-calendar-times" style="font-size: 3rem; margin-bottom: 15px;"></i>
                <p><?php echo htmlspecialchars($closedReason); ?></p>
                <p style="color:#6b7280;"><?php echo htmlspecialchars($displayDate); ?></p>
            </div>
            <?php elseif (!empty($stylists)): ?>
            <div class="schedule-grid-container">
                <div class="schedule-legend">
                    <span class="legend-title">Legend</span>
                    <div class="legend-group">
                        <div class="legend-chip">
                            <span class="legend-swatch legend-swatch-appointment"></span>
                            <span>Booked (left bar shows status)</span>
                        </div>
                        <div class="legend-chip">
                            <span class="legend-swatch legend-swatch-break"></span>
                            <span>Break</span>
                        </div>
                        <div class="legend-chip">
                            <span class="legend-swatch legend-swatch-unavailable"></span>
                            <span>Off / outside hours</span>
                        </div>
                        <div class="legend-chip">
                            <span class="legend-swatch legend-swatch-available"></span>
                            <span>Available</span>
                        </div>
                    </div>
                </div>
                <?php
$colWidth = 180;
$colCount = count($stylists);
$gridMinWidth = 80 + ($colWidth * $colCount);
?>
<div class="schedule-grid"
     style="--col-width: <?php echo $colWidth; ?>px;
            min-width: <?php echo $gridMinWidth; ?>px;
            grid-template-columns: 80px repeat(<?php echo $colCount; ?>, minmax(var(--col-width), 1fr));">
                    
                    <!-- Header Row -->
                    <div class="schedule-header time-cell">Time</div>
                    
                    <?php foreach ($stylists as $stylist): ?>
                        <div class="schedule-header">
                            <?php if(!empty($stylist['photo'])): ?>
                                <img src="<?php echo htmlspecialchars($stylist['photo']); ?>" 
                                     alt="<?php echo htmlspecialchars($stylist['name']); ?>"
                                     class="stylist-avatar" 
                                     style="width:50px;height:50px;border-radius:50%;object-fit:cover;"
                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                                <div class="stylist-avatar-placeholder" style="display:none; width:50px;height:50px;border-radius:50%;background:#e5e7eb;"></div>
                           <?php else: ?>
                                <div class="stylist-avatar-placeholder" style="width:50px;height:50px;border-radius:50%;background:#e5e7eb;"></div>
                            <?php endif; ?>
                            
                            <div class="stylist-info">
                                <span class="stylist-name"><?php echo htmlspecialchars($stylist['name']); ?></span>
                                <?php
                                    
                                    $isStylistAvailable = isset($stylistSchedules[$stylist['stylist_id']]) && (int)$stylistSchedules[$stylist['stylist_id']]['is_available'] === 1;
                                    $statusLabel = $isStylistAvailable ? 'Available' : 'Off today';
                                ?>
                                <span class="stylist-status"><?php echo $statusLabel; ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <?php
                    
                    
                    $defaultStart = strtotime('09:00');
                    $defaultEnd = strtotime('18:00');
                    $bizOpen = $bizOpenTime ? strtotime($bizOpenTime) : null;
                    $bizClose = $bizCloseTime ? strtotime($bizCloseTime) : null;
                    
                    $earliestStart = $bizOpen ?: $defaultStart;
                    $latestEnd = $bizClose ?: $defaultEnd;

                   
                    foreach ($stylists as $stylist) {
                        $sId = $stylist['stylist_id'];
                        if (!isset($stylistSchedules[$sId])) {
                            continue;
                        }
                        $sch = $stylistSchedules[$sId];
                        if ((int)$sch['is_available'] !== 1) continue;
                        $sStart = strtotime($sch['start_time']);
                        $sEnd = strtotime($sch['end_time']);
                        if ($sStart === false || $sEnd === false || $sStart >= $sEnd) continue;
                        if ($bizOpen) $sStart = max($sStart, $bizOpen);
                        if ($bizClose) $sEnd = min($sEnd, $bizClose);
                        if ($sStart >= $sEnd) continue;
                        $earliestStart = min($earliestStart, $sStart);
                        $latestEnd = max($latestEnd, $sEnd);
                    }

                   
                    $minGrid = strtotime('09:00');
                    $maxGrid = strtotime('18:00');
                    $startTime = max($earliestStart, $minGrid);
                    $endTime = min($latestEnd, $maxGrid);
                    if ($startTime >= $endTime) { 
                        $startTime = $minGrid;
                        $endTime = $maxGrid;
                    }

                    
                    while ($startTime <= $maxGrid) {
                        $currentTimeStr = date('H:i', $startTime);
                        ?>
                        
                        <!-- Time Cell -->
                        <div class="schedule-cell time-cell"><?php echo $currentTimeStr; ?></div>

                        <?php foreach ($stylists as $stylist): ?>
                            <?php 
                            $sId = $stylist['stylist_id'];
                            
                            $scheduleInfo = $stylistSchedules[$sId] ?? null;
                            $isAvailable = $scheduleInfo && (int)$scheduleInfo['is_available'] === 1;
                            $styStart = $isAvailable ? strtotime($scheduleInfo['start_time']) : null;
                            $styEnd = $isAvailable ? strtotime($scheduleInfo['end_time']) : null;
                            $styBreakStart = ($isAvailable && $scheduleInfo['break_start']) ? strtotime($scheduleInfo['break_start']) : null;
                            $styBreakEnd = ($isAvailable && $scheduleInfo['break_end']) ? strtotime($scheduleInfo['break_end']) : null;

                           
                            if ($isAvailable) {
                                if ($bizOpen) $styStart = max($styStart, $bizOpen);
                                if ($bizClose) $styEnd = min($styEnd, $bizClose);
                                if ($styStart === false || $styEnd === false || $styStart >= $styEnd) {
                                    $isAvailable = false;
                                }
                            }

                            $currentTs = $startTime;
                            $inBreak = $isAvailable && $styBreakStart && $styBreakEnd && $currentTs >= $styBreakStart && $currentTs < $styBreakEnd;
                            $outsideHours = !$isAvailable || $currentTs < $styStart || $currentTs >= $styEnd;

                            if ($outsideHours) {
                                echo '<div class="schedule-cell unavailable-slot" style="background:#f3f4f6; opacity:0.9;" title="Not on schedule"></div>';
                                continue;
                            }

                            
                            if ($inBreak) {
                                $breakLabel = '';
                                if ($scheduleInfo && $scheduleInfo['break_start'] && $scheduleInfo['break_end']) {
                                    $breakLabel = htmlspecialchars(substr($scheduleInfo['break_start'], 0, 5) . ' - ' . substr($scheduleInfo['break_end'], 0, 5));
                                }
                                echo '<div class="schedule-cell break-slot" title="Break'.(($breakLabel) ? ' ' . $breakLabel : '').'">
                                </div>';
                                continue;
                            }
                            
                        

if (isset($appointments[$currentTimeStr][$sId])) {
    $group = $appointments[$currentTimeStr][$sId];
    $primaryApt = $group['primary'] ?? null;
    $otherApts = $group['others'] ?? [];

    if ($primaryApt) {
        $otherCount = count($otherApts);

        $duration = (int)($primaryApt['total_duration'] ?? 30);
        $spanDuration = ($primaryApt['status'] === 'Cancelled') ? min(30, $duration) : $duration;
        $rowSpan = max(1, ceil($spanDuration / 30));

         $statusColors = [
              'Confirmed' => '#4f46e5',
              'Cancelled' => '#ef4444',
          ];
         $displayStatus = ($primaryApt['status'] === 'Cancelled') ? 'Cancelled' : 'Confirmed';
         $statusColor = $statusColors[$displayStatus] ?? '#6b7280';

        $startTimeObj = strtotime($primaryApt['appointment_time']);
        $displayStart = $startTimeObj !== false ? date('H:i', $startTimeObj) : $currentTimeStr;
        $endTimeStr = $startTimeObj !== false ? date('H:i', strtotime("+{$duration} minutes", $startTimeObj)) : '';
        ?>
        
        <div class="schedule-cell appointment-cell" 
             style="grid-row: span <?php echo $rowSpan; ?>; position: relative; padding: 4px;">
            
            <div class="appointment-card overlap-container"
                style="
                    background: linear-gradient(135deg, #e0e7ff 0%, #f3e8ff 100%);
                    padding: 10px;
                    border-radius: 8px;
                    border-left: 4px solid <?php echo $statusColor; ?>;
                    font-size: 0.85rem;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                    height: 100%;
                    position: relative;
                ">

                <?php if ($otherCount > 0): ?>
                    <div class="overlap-toggle">+<?php echo $otherCount; ?></div>
                    <div class="overlap-list">
                        <?php foreach ($otherApts as $o): 
                            $oDuration = (int)($o['total_duration'] ?? 30);
                            $oStartObj = strtotime($o['appointment_time']);
                            $oEndStr = $oStartObj !== false ? date('H:i', strtotime("+{$oDuration} minutes", $oStartObj)) : '';
                            $oDisplayStatus = ($o['status'] === 'Cancelled') ? 'Cancelled' : 'Confirmed';
                            $oStatusColor = $statusColors[$oDisplayStatus] ?? '#6b7280';
                        ?>
                            <div style="border-left:3px solid <?php echo $oStatusColor; ?>; padding:6px 8px; margin-bottom:6px; background:#f9fafb; border-radius:6px;">
                                <div style="display:flex; justify-content:space-between; align-items:center;">
                                    <strong><?php echo htmlspecialchars($o['customer_name']); ?></strong>
                                    <span style="background: <?php echo $oStatusColor; ?>; color:white; padding:2px 8px; border-radius:10px; font-size:0.65rem;"><?php echo htmlspecialchars($oDisplayStatus); ?></span>
                                </div>
                                <div style="font-size:0.8rem; color:#6b7280; margin-top:2px;">
                                    <?php echo substr($o['appointment_time'], 0, 5); ?><?php echo $oEndStr ? ' - ' . $oEndStr : ''; ?> (<?php echo $oDuration; ?> min)
                                </div>
                                <div style="font-size:0.8rem; color:#4b5563; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                                    <?php echo htmlspecialchars($o['services'] ?? 'N/A'); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <div style="font-weight: 600; color: #1f2937; margin-bottom: 4px; overflow: hidden; text-overflow: ellipsis;">
                    <?php echo htmlspecialchars($primaryApt['customer_name']); ?>
                </div>
                
                <div style="color: #6b7280; font-size: 0.8rem; margin-bottom: 6px;">
                    <i class="far fa-clock"></i> 
                    <?php echo $displayStart; ?><?php echo $endTimeStr ? ' - ' . $endTimeStr : ''; ?>
                    <span style="margin-left: 4px;">(<?php echo $duration; ?> min)</span>
                </div>
                
                <div style="color: #4b5563; font-size: 0.8rem; margin-bottom: 4px; overflow: hidden; text-overflow: ellipsis;">
                    <i class="fas fa-cut"></i> 
                    <?php echo htmlspecialchars($primaryApt['services'] ?? 'N/A'); ?>
                </div>
                
                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 6px;">
                    <span style="font-weight: 600; color: #7c3aed;">
                        RM<?php echo number_format($primaryApt['total_price'], 2); ?>
                    </span>
                    <span class="status-badge" 
                          style="background: <?php echo $statusColor; ?>;
                                 color: white;
                                 padding: 2px 8px;
                                 border-radius: 12px;
                                 font-size: 0.7rem;
                                 font-weight: 500;">
                        <?php echo htmlspecialchars($displayStatus); ?>
                    </span>
                </div>
            </div>
        </div>
        
    <?php 
    } else {
        echo '<div class="schedule-cell empty-slot"></div>';
    }
} elseif (isset($occupiedSlots[$sId][$currentTimeStr])) {
   
    echo '<div class="schedule-cell occupied-slot" style="display:none;"></div>';
} else {
    
    echo '<div class="schedule-cell empty-slot"></div>';
}
?>
                        <?php endforeach; ?>

                        <?php
                        $startTime = strtotime('+30 minutes', $startTime);
                    }
                    ?>

                </div>
            </div>
            <?php else: ?>
                <div class="empty-state" style="text-align: center; padding: 60px 20px; color: #9ca3af;">
                    <i class="fas fa-users-slash" style="font-size: 3rem; margin-bottom: 15px;"></i>
                    <p>No stylists available.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- CALENDAR VIEW -->
        <div class="calendar-view" id="calendarView" style="<?php echo $currentView === 'list' ? 'display:none;' : 'display:flex;'; ?>">
            <div class="calendar-widget">
                <div class="calendar-header">
                    <h3><?php echo htmlspecialchars("$monthName $year"); ?></h3>
                    <div class="calendar-nav">
                        <a href="?month=<?php echo date('m', $prevMonthDate); ?>&year=<?php echo date('Y', $prevMonthDate); ?>&view=calendar&date=<?php echo date('Y-m-01', $prevMonthDate); ?>" 
                           title="Previous month">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                        <a href="?month=<?php echo date('m', $nextMonthDate); ?>&year=<?php echo date('Y', $nextMonthDate); ?>&view=calendar&date=<?php echo date('Y-m-01', $nextMonthDate); ?>"
                           title="Next month">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </div>
                </div>
                <div class="calendar-grid">
                    <div class="calendar-day-header">Su</div>
                    <div class="calendar-day-header">Mo</div>
                    <div class="calendar-day-header">Tu</div>
                    <div class="calendar-day-header">We</div>
                    <div class="calendar-day-header">Th</div>
                    <div class="calendar-day-header">Fr</div>
                    <div class="calendar-day-header">Sa</div>

                    <?php
                    
                    for ($i = 0; $i < $calendarDayOfWeek; $i++) {
                        echo '<div class="calendar-day other-month"></div>';
                    }

                    
                    for ($day = 1; $day <= $numberDays; $day++) {
                        $currentDayStr = sprintf('%04d-%02d-%02d', $year, $month, $day);
                        $isToday = ($currentDayStr == date('Y-m-d')) ? 'today' : '';
                        $isSelected = ($currentDayStr == $currentDate) ? 'selected' : '';
                        
                        
                        $classes = ['calendar-day'];
                        if ($isSelected) {
                            $classes[] = 'selected';
                        } elseif ($isToday) {
                            $classes[] = 'today';
                        }
                        
                        echo "<div class='" . implode(' ', $classes) . "'>";
                        echo "<a href='?date=" . urlencode($currentDayStr) . "&view=calendar&month=$month&year=$year' ";
                        echo "style='text-decoration:none; color:inherit; display:block; width:100%; height:100%; padding: 8px;' ";
                        echo "title='View appointments for $currentDayStr'>$day</a>";
                        echo "</div>";
                    }

                    
                    $totalCells = $calendarDayOfWeek + $numberDays;
                    $remainingCells = (ceil($totalCells / 7) * 7) - $totalCells;
                    for ($i = 0; $i < $remainingCells; $i++) {
                        echo '<div class="calendar-day other-month"></div>';
                    }
                    ?>
                </div>

                <div class="calendar-legend">
                    <h4>Legend</h4>
                    <div class="legend-item">
                        <div class="legend-color" style="background: #fbbf24;"></div>
                        <span>Today</span>
                    </div>
                </div>
            </div>

            <div class="appointments-panel">
                <div class="appointments-header">
                    <span class="current-date"><?php echo htmlspecialchars($displayDate); ?></span>
                </div>
                <?php if (count($appointmentsRaw) > 0): ?>
                    <div class="appointments-list">
                        <?php foreach ($appointmentsRaw as $apt): 
                            $duration = (int)($apt['total_duration'] ?? 30);
                            $startTimeObj = strtotime($apt['appointment_time']);
                            
                            if ($startTimeObj === false) {
                                continue;
                            }
                            
                            $endTimeStr = date('H:i', strtotime("+{$duration} minutes", $startTimeObj));
                            
                            $statusColors = [
                                    'Confirmed' => '#4f46e5',
                                    'Cancelled' => '#ef4444',
                            ];
                            $displayStatus = ($apt['status'] === 'Cancelled') ? 'Cancelled' : 'Confirmed';
                            $statusColor = $statusColors[$displayStatus] ?? '#6b7280';
                        ?>
                            <div class="appointment-item" 
                                 style="border-left: 3px solid <?php echo $statusColor; ?>; 
                                        padding: 15px; 
                                        margin-bottom: 10px; 
                                        background: #f9fafb; 
                                        border-radius: 8px;">
                                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 8px;">
                                    <div>
                                        <i class="far fa-clock" style="color: #6b7280;"></i>
                                        <strong style="font-size: 1rem;">
                                            <?php echo substr($apt['appointment_time'], 0, 5); ?> - <?php echo $endTimeStr; ?>
                                        </strong>
                                        <span class="badge" 
                                              style="background: <?php echo $statusColor; ?>; 
                                                     color: white; 
                                                     padding: 2px 8px; 
                                                     border-radius: 12px; 
                                                     font-size: 0.75rem; 
                                                     margin-left: 8px;">
                                            <?php echo htmlspecialchars($displayStatus); ?>
                                        </span>
                                    </div>
                                    <strong style="color: #7c3aed; font-size: 1.1rem;">
                                        RM<?php echo number_format($apt['total_price'], 2); ?>
                                    </strong>
                                </div>
                                
                                <div style="margin-left: 20px;">
                                    <p style="margin: 4px 0;">
                                        <i class="far fa-user"></i> 
                                        <strong><?php echo htmlspecialchars($apt['customer_name']); ?></strong>
                                    </p>
                                    <p style="margin: 4px 0; color: #6b7280;">
                                        <i class="fas fa-cut"></i> 
                                        <?php echo htmlspecialchars($apt['services'] ?? 'No services listed'); ?>
                                    </p>
                                    <p style="margin: 4px 0; color: #6b7280; font-size: 0.9rem;">
                                        <i class="far fa-clock"></i> 
                                        <?php echo $duration; ?> minutes
                                    </p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state" style="text-align: center; padding: 40px; color: #9ca3af;">
                        <i class="far fa-calendar-check" style="font-size: 3rem; margin-bottom: 15px;"></i>
                        <p>No appointments on this day</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        window.scheduleConfig = <?php echo json_encode([
            'currentDate' => $currentDate,
            'currentView' => $currentView,
            'month'       => $month,
            'year'        => $year,
            'prevDate'    => $prevDate,
            'nextDate'    => $nextDate,
	    'isAdmin'     => $isAdmin,
            'isStaff'     => $isStaff,
            'dayOfWeek'   => $dayOfWeekName  // Added for JavaScript access
        ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
        
        window.weeklySchedules = <?php echo json_encode($weeklySchedules ?: $allSchedules, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
        window.dateOverrides = <?php echo json_encode($dateOverrides, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
        
        window.allSchedules = window.weeklySchedules;
    </script>
    <script src="../js/schedule.js"></script>

    <?php if ($isAdmin): ?>
    <script>
        // IMPROVED: Removed editDay handling, simplified logic
        const daysOrder = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
        const msgEl = document.getElementById('editScheduleMsg');
        const defaultBreakStart = '12:00';
        const defaultBreakEnd = '13:00';

        function getDefaultBreak(startTime, endTime) {
            if (!startTime || !endTime) return { start: '', end: '' };
            const toMin = t => {
                const parts = t.split(':');
                if (parts.length < 2) return null;
                return Number(parts[0]) * 60 + Number(parts[1]);
            };
            const startMin = toMin(startTime);
            const endMin = toMin(endTime);
            if (startMin === null || endMin === null) return { start: '', end: '' };
            if (startMin <= 12 * 60 && endMin >= 13 * 60) {
                return { start: defaultBreakStart, end: defaultBreakEnd };
            }
            return { start: '', end: '' };
        }

        // Unified flash-style message (matches flash_message.php + common.css)
        function showFlashMessage(text, type = 'info') {
            const existing = document.getElementById('flashMessage');
            if (existing) {
                existing.remove();
            }

            const durationMs = window.FLASH_MESSAGE_DURATION_MS || 10000;
            const iconMap = {
                success: 'fas fa-check-circle',
                error: 'fas fa-exclamation-circle',
                info: 'fas fa-info-circle',
                warning: 'fas fa-exclamation-triangle'
            };

            const flash = document.createElement('div');
            flash.className = `flash-message flash-${type}`;
            flash.id = 'flashMessage';

            const icon = document.createElement('i');
            icon.className = iconMap[type] || iconMap.info;

            const messageSpan = document.createElement('span');
            messageSpan.textContent = text;

            const countdownSpan = document.createElement('span');
            countdownSpan.className = 'flash-countdown';
            countdownSpan.setAttribute('aria-hidden', 'true');

            flash.appendChild(icon);
            flash.appendChild(messageSpan);
            flash.appendChild(countdownSpan);
            document.body.appendChild(flash);

            const dismiss = () => {
                flash.style.animation = 'slideOutRight 0.3s ease-out';
                setTimeout(() => flash.remove(), 300);
            };

            const endTime = Date.now() + durationMs;
            const updateCountdown = () => {
                const remainingMs = endTime - Date.now();
                const remainingSec = Math.max(0, Math.ceil(remainingMs / 1000));
                countdownSpan.textContent = `${remainingSec}s`;
            };

            updateCountdown();
            const intervalId = setInterval(updateCountdown, 1000);
            const timeoutId = setTimeout(() => {
                clearInterval(intervalId);
                dismiss();
            }, durationMs);

            flash.addEventListener('click', () => {
                clearInterval(intervalId);
                clearTimeout(timeoutId);
                dismiss();
            });
        }

        function showMsg(text, type = 'info') {
            if (msgEl) {
                msgEl.textContent = '';
            }
            showFlashMessage(text, type);
        }

        const weeklySchedules = window.weeklySchedules || {};
        const dateOverrides = window.dateOverrides || {};
        const currentDate = (window.scheduleConfig && window.scheduleConfig.currentDate) || '';
        const dayOfWeek = (window.scheduleConfig && window.scheduleConfig.dayOfWeek) || '';

        function getEffectiveSchedule(sId, day) {
            const dateData = dateOverrides[sId] && dateOverrides[sId][currentDate] ? { ...(dateOverrides[sId][currentDate]), scope: 'date' } : null;
            if (dateData) return dateData;
            const weeklyData = weeklySchedules[sId] && weeklySchedules[sId][day] ? { ...(weeklySchedules[sId][day]), scope: 'weekly' } : null;
            return weeklyData;
        }

        function populateForm() {
            const sId = document.getElementById('editStylist').value;
            const data = getEffectiveSchedule(sId, dayOfWeek);

            if (data && Number(data.is_available) === 1) {
                document.getElementById('editOff').checked = false;
                const startVal = data.start_time ? data.start_time.substring(0,5) : '';
                const endVal = data.end_time ? data.end_time.substring(0,5) : '';
                let breakStartVal = data.break_start ? data.break_start.substring(0,5) : '';
                let breakEndVal = data.break_end ? data.break_end.substring(0,5) : '';
                if (!breakStartVal && !breakEndVal) {
                    const defaults = getDefaultBreak(startVal, endVal);
                    breakStartVal = defaults.start;
                    breakEndVal = defaults.end;
                }
                document.getElementById('editStart').value = startVal;
                document.getElementById('editEnd').value = endVal;
                document.getElementById('editBreakStart').value = breakStartVal;
                document.getElementById('editBreakEnd').value = breakEndVal;
            } else {
                document.getElementById('editOff').checked = true;
                document.getElementById('editStart').value = '';
                document.getElementById('editEnd').value = '';
                document.getElementById('editBreakStart').value = '';
                document.getElementById('editBreakEnd').value = '';
            }
        }

        document.getElementById('loadScheduleBtn').addEventListener('click', function(e){
            e.preventDefault();
            const sId = document.getElementById('editStylist').value;
            const data = getEffectiveSchedule(sId, dayOfWeek);
            populateForm();

            if (data) {
                const scopeLabel = data.scope === 'weekly' ? 'weekly schedule' : 'date override';
                showMsg(`Loaded ${scopeLabel}.`, 'info');
            } else {
                showMsg('No existing schedule found to load.', 'warning');
            }
        });

        document.getElementById('saveScheduleBtn').addEventListener('click', async function(e){
            e.preventDefault();
            const startVal = document.getElementById('editStart').value;
            const endVal = document.getElementById('editEnd').value;
            const isOff = document.getElementById('editOff').checked;
            let breakStartVal = document.getElementById('editBreakStart').value;
            let breakEndVal = document.getElementById('editBreakEnd').value;

            if (!isOff && !breakStartVal && !breakEndVal) {
                const defaults = getDefaultBreak(startVal, endVal);
                breakStartVal = defaults.start;
                breakEndVal = defaults.end;
                if (defaults.start && defaults.end) {
                    document.getElementById('editBreakStart').value = defaults.start;
                    document.getElementById('editBreakEnd').value = defaults.end;
                }
            }

            const payload = {
                stylist_id: document.getElementById('editStylist').value,
                day_of_week: dayOfWeek, // Always use current viewing day
                is_available: isOff ? 0 : 1,
                start_time: startVal,
                end_time: endVal,
                break_start: isOff ? '' : breakStartVal,
                break_end: isOff ? '' : breakEndVal,
                apply_scope: document.getElementById('applyScope') ? document.getElementById('applyScope').value : 'date',
                override_date: currentDate
            };

            try {
                const res = await fetch('save_schedule.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                if (!data.success) {
                    showMsg(data.message || 'Save failed', 'error');
                    return;
                }
                
                if (payload.apply_scope === 'date') {
                    if (!window.dateOverrides[payload.stylist_id]) window.dateOverrides[payload.stylist_id] = {};
                    window.dateOverrides[payload.stylist_id][payload.override_date] = {
                        stylist_id: payload.stylist_id,
                        day_of_week: payload.day_of_week,
                        override_date: payload.override_date,
                        start_time: payload.is_available ? payload.start_time + ':00' : null,
                        end_time: payload.is_available ? payload.end_time + ':00' : null,
                        break_start: payload.is_available && payload.break_start ? payload.break_start + ':00' : null,
                        break_end: payload.is_available && payload.break_end ? payload.break_end + ':00' : null,
                        is_available: payload.is_available,
                        schedule_scope: 'date'
                    };
                } else {
                    if (!window.weeklySchedules[payload.stylist_id]) window.weeklySchedules[payload.stylist_id] = {};
                    window.weeklySchedules[payload.stylist_id][payload.day_of_week] = {
                        stylist_id: payload.stylist_id,
                        day_of_week: payload.day_of_week,
                        start_time: payload.is_available ? payload.start_time + ':00' : null,
                        end_time: payload.is_available ? payload.end_time + ':00' : null,
                        break_start: payload.is_available && payload.break_start ? payload.break_start + ':00' : null,
                        break_end: payload.is_available && payload.break_end ? payload.break_end + ':00' : null,
                        is_available: payload.is_available,
                        schedule_scope: 'weekly'
                    };
                    window.allSchedules = window.weeklySchedules;
                }
                showMsg('Schedule saved. Refresh to see updated grid.', 'success');
            } catch (err) {
                console.error(err);
                showMsg('Network error', 'error');
            }
        });
        
        populateForm();
        
        // Auto-update stylist selection
        document.getElementById('editStylist').addEventListener('change', populateForm);
    </script>
    <?php endif; ?>
</body>
</html>
