<?php
session_start();
header('Content-Type: application/json');

require_once '../config.php';
require_once '../users_management/auth_check.php';
requireLogin(['Admin']);

try {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    if (!$data || !isset($data['hours'])) {
        throw new Exception('Invalid data received');
    }

    // Only treat a real boolean true as "force" to avoid accidental truthy values from the client.
    $force = isset($data['force']) && $data['force'] === true;
    $affectedBookings = [];

    // 检查所有受影响的 bookings (不只是关门,也包括时间改变)
    if (is_array($data['hours'])) {
        $weekdayMap = [
            'Monday' => 0, 'Tuesday' => 1, 'Wednesday' => 2, 'Thursday' => 3,
            'Friday' => 4, 'Saturday' => 5, 'Sunday' => 6,
        ];

        // 获取现有的营业时间
        $currentHoursStmt = $pdo->prepare("SELECT day_of_week, opening_time, closing_time, is_closed FROM businesshours");
        $currentHoursStmt->execute();
        $currentHours = [];
        foreach ($currentHoursStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $currentHours[$row['day_of_week']] = $row;
        }

        // 检查每一天的改变
        foreach ($data['hours'] as $newDayData) {
            $dayName = $newDayData['day'] ?? '';
            if (!$dayName || !isset($weekdayMap[$dayName])) continue;

            $dow = $weekdayMap[$dayName];
            $currentDay = $currentHours[$dayName] ?? null;
            
            // 检查是否有实质性改变
            $willClose = !empty($newDayData['is_closed']);
            $wasOpen = $currentDay && !$currentDay['is_closed'];
            $timeChanged = false;
            
            if ($currentDay && !$willClose && $wasOpen) {
                $newOpen = $newDayData['opening_time'];
                $newClose = $newDayData['closing_time'];
                $oldOpen = substr($currentDay['opening_time'], 0, 8);
                $oldClose = substr($currentDay['closing_time'], 0, 8);
                
                $timeChanged = ($newOpen != $oldOpen || $newClose != $oldClose);
            }

            
            if ($willClose || $timeChanged) {
                $checkStmt = $pdo->prepare("
                    SELECT 
                        a.appointment_id,
                        a.appointment_date,
                        a.appointment_time,
                        a.user_id,
                        u.name as customer_name,
                        u.email as customer_email,
                        GROUP_CONCAT(DISTINCT s.service_name ORDER BY s.service_name SEPARATOR ', ') as service_name,
                        su.name as stylist_name,
                        COALESCE(SUM(s.duration_minutes), 0) as total_duration
                    FROM appointment a
                    JOIN user u ON a.user_id = u.user_id
                    LEFT JOIN stylist st ON a.stylist_id = st.stylist_id
                    LEFT JOIN user su ON st.user_id = su.user_id
                    LEFT JOIN appointmentitem ai ON a.appointment_id = ai.appointment_id
                    LEFT JOIN service s ON ai.service_id = s.service_id
                    WHERE a.appointment_date >= CURDATE()
                      AND WEEKDAY(a.appointment_date) = :dow
                      AND a.status NOT IN ('Cancelled', 'Completed')
                    GROUP BY a.appointment_id, a.appointment_date, a.appointment_time, a.user_id, u.name, u.email, su.name
                ");
                
                $checkStmt->execute([':dow' => $dow]);
                $bookings = $checkStmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($bookings as $booking) {
                    $bookingTime = $booking['appointment_time'];
                    $bookingEndTime = $bookingTime;
                    $durationMinutes = (int)($booking['total_duration'] ?? 0);
                    if ($durationMinutes > 0) {
                        $startTs = strtotime($bookingTime);
                        if ($startTs !== false) {
                            $bookingEndTime = date('H:i:s', $startTs + ($durationMinutes * 60));
                        }
                    }
                    $affected = false;
                    
                    if ($willClose) {
                        $affected = true;
                        $reason = "Salon will be closed on {$dayName}s";
                    } elseif ($timeChanged) {
                        // 检查 booking 时间是否在新营业时间之外
                        $newOpen = $newDayData['opening_time'];
                        $newClose = $newDayData['closing_time'];
                        
                        $startsBeforeOpen = ($bookingTime < $newOpen);
                        $startsAfterClose = ($bookingTime >= $newClose);
                        $endsAfterClose = ($bookingEndTime > $newClose);

                        if ($startsBeforeOpen || $startsAfterClose || $endsAfterClose) {
                            $affected = true;
                            if ($endsAfterClose && !$startsBeforeOpen && !$startsAfterClose) {
                                $reason = "New hours: {$newOpen}-{$newClose}, your booking ends at {$bookingEndTime} (duration {$durationMinutes} min)";
                            } else {
                                $reason = "New hours: {$newOpen}-{$newClose}, your booking {$bookingTime}-{$bookingEndTime} is outside";
                            }
                        }
                    }
                    
                    if ($affected) {
                        $affectedBookings[] = [
                            'appointment_id' => $booking['appointment_id'],
                            'day' => $dayName,
                            'date' => $booking['appointment_date'],
                            'time' => $bookingTime,
                            'customer_name' => $booking['customer_name'],
                            'customer_email' => $booking['customer_email'],
                            'service' => $booking['service_name'],
                            'stylist' => $booking['stylist_name'],
                            'reason' => $reason
                        ];
                    }
                }
            }
        }

        // 如果有受影响的 bookings,返回确认信息
        if (!$force && !empty($affectedBookings)) {
            echo json_encode([
                'success' => false,
                'needs_confirm' => true,
                'affected_bookings' => $affectedBookings,
                'message' => count($affectedBookings) . ' booking(s) will be affected. Customers will be notified by email. Continue?'
            ]);
            exit;
        }
    }
    
    // 开始更新
    $pdo->beginTransaction();
    
    // 更新营业时间
    $stmt = $pdo->prepare("
        UPDATE businesshours 
        SET opening_time = :opening_time, 
            closing_time = :closing_time, 
            is_closed = :is_closed,
            updated_at = NOW()
        WHERE day_of_week = :day_of_week
    ");
    
    foreach ($data['hours'] as $dayData) {
        $stmt->bindValue(':day_of_week', $dayData['day']);
        $stmt->bindValue(':opening_time', $dayData['is_closed'] ? '00:00:00' : $dayData['opening_time']);
        $stmt->bindValue(':closing_time', $dayData['is_closed'] ? '00:00:00' : $dayData['closing_time']);
        $stmt->bindValue(':is_closed', $dayData['is_closed'] ? 1 : 0, PDO::PARAM_INT);
        
        $stmt->execute();
    }
    
    // 如果是 force 更新且有受影响的 bookings,发送通知邮件
    $pdo->commit();
    
    $message = 'Business hours updated successfully!';
    if ($force && !empty($affectedBookings)) {
        $emailStats = sendAffectedBookingEmails($pdo, $affectedBookings);
        $attempted = (int)($emailStats['attempted'] ?? 0);
        $sent = (int)($emailStats['sent'] ?? 0);

        if ($attempted > 0 && $sent === $attempted) {
            $message .= ' Email notifications sent to ' . $sent . ' customer(s).';
        } elseif ($attempted > 0) {
            $message .= " Attempted to email {$attempted} customer(s); successfully sent to {$sent}.";
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => $message
    ]);
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

/**
 * 发送邮件通知受影响的客户
 */
function sendAffectedBookingEmails($pdo, $affectedBookings) {
    require_once __DIR__ . '/../appointment/email_utility.php';
    
    $groupedByEmail = [];
    foreach ($affectedBookings as $booking) {
        $email = $booking['customer_email'];
        if (!$email) {
            continue;
        }
        if (!isset($groupedByEmail[$email])) {
            $groupedByEmail[$email] = [
                'name' => $booking['customer_name'],
                'bookings' => []
            ];
        }
        $groupedByEmail[$email]['bookings'][] = $booking;
    }
    
    $attempted = 0;
    $sent = 0;
    foreach ($groupedByEmail as $email => $data) {
        $attempted++;
        if (sendHoursChangeNotification($email, $data['name'], $data['bookings'])) {
            $sent++;
        }
    }

    return [
        'attempted' => $attempted,
        'sent' => $sent
    ];
}

/**
 * 发送单个邮件 - 使用现有的 email template
 */
function sendHoursChangeNotification($email, $customerName, $bookings) {
    $bookingList = '';
    foreach ($bookings as $b) {
        $formattedDate = date('l, F j, Y', strtotime($b['date']));
        $bookingList .= '
        <div class="detail-row">
            <span class="detail-label">Date & Time:</span>
            <span class="detail-value">' . htmlspecialchars($formattedDate) . ' at ' . htmlspecialchars($b['time']) . '</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Service:</span>
            <span class="detail-value">' . htmlspecialchars($b['service'] ?: 'N/A') . '</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Stylist:</span>
            <span class="detail-value">' . htmlspecialchars($b['stylist'] ?: 'N/A') . '</span>
        </div>
        <div class="detail-row" style="border-bottom: 2px solid #e9d5ff; padding-bottom: 15px; margin-bottom: 15px;">
            <span class="detail-label">Reason:</span>
            <span class="detail-value" style="color: #dc2626;">' . htmlspecialchars($b['reason']) . '</span>
        </div>';
    }
    
    // Get the base URL for the reschedule link
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $baseUrl = $protocol . '://' . $host;

    // Project root is one level above /schedule_service
    $scriptDir = isset($_SERVER['SCRIPT_NAME']) ? dirname($_SERVER['SCRIPT_NAME']) : '';
    $rootPath = rtrim(dirname($scriptDir), '/\\');
    if ($rootPath === '.' || $rootPath === DIRECTORY_SEPARATOR) {
        $rootPath = '';
    }
    $rescheduleUrl = $baseUrl . $rootPath . '/appointment/my_bookings.php';
    
    $content = '
        <div style="text-align: center; font-size: 50px; margin: 20px 0;">⚠️</div>
        <h2 style="text-align: center; color: #dc2626;">Business Hours Changed</h2>
        <p>Dear <strong>' . htmlspecialchars($customerName) . '</strong>,</p>
        <p>We have updated our business hours. Unfortunately, this change affects your upcoming appointment(s).</p>
        
        <div class="info-box">
            <h3>Affected Appointment(s)</h3>
            ' . $bookingList . '
        </div>
        
        <div class="warning-box">
            <h3>⚠️ Action Required</h3>
            <p style="margin: 10px 0 0 0;">Please reschedule or cancel your appointment(s) as soon as possible to avoid any inconvenience.</p>
        </div>
        
        <div style="text-align: center; margin: 30px 0;">
            <a href="' . htmlspecialchars($rescheduleUrl) . '" class="btn">Manage My Appointments</a>
        </div>
        
        <p>We sincerely apologize for any inconvenience this may cause. If you have any questions or need assistance, please don\'t hesitate to contact us at ' . SALON_PHONE . '.</p>
        
        <p>Thank you for your understanding.</p>
        
        <p style="margin-top: 30px;">
            Warm regards,<br>
            <strong style="color: #9333ea;">The ' . SALON_NAME . ' Team</strong>
        </p>';
    
    return sendEmail($email, '⚠️ Important: Business Hours Changed - Action Required', $content, $customerName);
}
?>
