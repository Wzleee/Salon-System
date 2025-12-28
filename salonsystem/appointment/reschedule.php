<?php
require_once '../config.php';
require_once '../users_management/auth_check.php';
requireLogin(['Admin', 'Customer']);
require_once 'email_utility.php';
$pageCSS = '../css/reschedule.css';
$user_id = $_SESSION['user_id'];

// Get appointment ID from URL
$appointment_id = $_GET['id'] ?? null;

if (!$appointment_id) {
    header('Location: my_bookings.php');
    exit;
}

// Fetch existing appointment details
try {
    $sql = "SELECT 
                a.appointment_id,
                a.appointment_date,
                a.appointment_time,
                a.total_price,
                a.status,
                st.stylist_id,
                u.name as stylist_name,
                st.specialization,
                GROUP_CONCAT(s.service_name SEPARATOR ', ') as services,
                SUM(s.duration_minutes) as total_duration
            FROM appointment a
            JOIN stylist st ON a.stylist_id = st.stylist_id
            JOIN user u ON st.user_id = u.user_id
            JOIN appointmentitem ai ON a.appointment_id = ai.appointment_id
            JOIN service s ON ai.service_id = s.service_id
            WHERE a.appointment_id = :appointment_id
            AND a.user_id = :user_id
            AND a.status <> 'Cancelled'
            GROUP BY a.appointment_id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':appointment_id' => $appointment_id,
        ':user_id' => $user_id
    ]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$booking) {
        header('Location: my_bookings.php');
        exit;
    }
    
    // Check if appointment is in the past
    if (strtotime($booking['appointment_date']) < strtotime(date('Y-m-d'))) {
        header('Location: booking_details.php?id=' . $appointment_id);
        exit;
    }
    
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

// Handle reschedule submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reschedule'])) {
    $new_date = $_POST['new_date'];
    $new_time = $_POST['new_time'];
    $old_date = $booking['appointment_date'];
    $old_time = $booking['appointment_time'];

    $isHolidayDate = false;//added
    if ($new_date) {
        try { //added 
            $holidayCheck = $pdo->prepare(" 
                SELECT 1 FROM holiday 
                WHERE holiday_date = :date 
                   OR (is_recurring = 1 AND DATE_FORMAT(holiday_date, '%m-%d') = DATE_FORMAT(:date, '%m-%d'))
                LIMIT 1
            ");
            $holidayCheck->execute([':date' => $new_date]);
            $isHolidayDate = (bool)$holidayCheck->fetchColumn();
        } catch (PDOException $e) {
            error_log('Holiday check failed: ' . $e->getMessage());
        }
    }
    
    if ($isHolidayDate) { //added
        $error_message = "Cannot reschedule to a holiday. Please pick another date.";
    } else {
        try {
            $sql = "UPDATE appointment 
                    SET appointment_date = :new_date, 
                        appointment_time = :new_time 
                    WHERE appointment_id = :appointment_id
                    AND user_id = :user_id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':new_date' => $new_date,
                ':new_time' => $new_time,
                ':appointment_id' => $appointment_id,
                ':user_id' => $user_id
            ]);
            
            if ($stmt->rowCount() > 0) {
                try {
                    $customerStmt = $pdo->prepare("SELECT name, email FROM user WHERE user_id = :user_id");
                    $customerStmt->execute([':user_id' => $user_id]);
                    $customer = $customerStmt->fetch(PDO::FETCH_ASSOC);

                    $serviceStmt = $pdo->prepare("
                        SELECT s.service_name, s.duration_minutes, ai.service_price
                        FROM appointmentitem ai
                        JOIN service s ON ai.service_id = s.service_id
                        WHERE ai.appointment_id = :appointment_id
                    ");
                    $serviceStmt->execute([':appointment_id' => $appointment_id]);
                    $services = $serviceStmt->fetchAll(PDO::FETCH_ASSOC);

                    if ($customer) {
                        $bookingDetails = [
                            'appointment_id' => $appointment_id,
                            'appointment_date' => $new_date,
                            'appointment_time' => $new_time,
                            'stylist_name' => $booking['stylist_name'],
                            'total_price' => $booking['total_price'],
                            'services' => $services
                        ];
                        sendRescheduleConfirmation($customer['email'], $customer['name'], $bookingDetails, $old_date, $old_time);
                    }
                } catch (Exception $e) {
                    error_log("Reschedule email error: " . $e->getMessage());
                }

                header('Location: booking_details.php?id=' . $appointment_id . '&rescheduled=1');
                exit;
            } else {
                $error_message = "Unable to reschedule appointment. Please contact support.";
            }
        } catch (PDOException $e) {
            $error_message = "Error rescheduling appointment: " . $e->getMessage();
        }
    }
}

$date = date('l, F j, Y', strtotime($booking['appointment_date']));
$time = date('g:i A', strtotime($booking['appointment_time']));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reschedule Appointment - Cosmos Salon</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <?php if(isset($pageCSS)): ?>
    <link rel="stylesheet" href="<?php echo $pageCSS; ?>">
    <?php endif; ?>
</head>
<body>

<!-- Header -->
<header class="site-header">
    <div class="header-content">
        <a href="../index.php" style="text-decoration: none; color: inherit;">
            <div class="logo">
                <i class="fas fa-scissors"></i>
                <span>COSMOS SALON</span>
            </div>
        </a>
    </div>
</header>

<div class="appointment-container">
    <a href="booking_details.php?id=<?php echo $appointment_id; ?>" class="back-to-home">
        <i class="fas fa-arrow-left"></i>
        Back to Booking Details
    </a>

    <div class="appointment-header">
        <h1>Reschedule Appointment</h1>
        <p class="booking-id">Booking ID: <strong>#<?php echo str_pad($booking['appointment_id'], 6, '0', STR_PAD_LEFT); ?></strong></p>
    </div>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo $error_message; ?>
        </div>
    <?php endif; ?>

    <form method="POST" id="rescheduleForm">
        <!-- Current Booking Info Card -->
        <div class="info-card">
            <div class="step-title">
                <h2><i class="fas fa-calendar-check"></i> Current Booking Details</h2>
            </div>
            <div class="booking-info-grid">
                <div class="info-item">
                    <span class="info-label"><i class="far fa-calendar"></i> Date</span>
                    <span class="info-value"><?php echo $date; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label"><i class="far fa-clock"></i> Time</span>
                    <span class="info-value"><?php echo $time; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label"><i class="fas fa-user-tie"></i> Stylist</span>
                    <span class="info-value"><?php echo htmlspecialchars($booking['stylist_name']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label"><i class="fas fa-cut"></i> Services</span>
                    <span class="info-value"><?php echo htmlspecialchars($booking['services']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label"><i class="fas fa-hourglass-half"></i> Duration</span>
                    <span class="info-value"><?php echo $booking['total_duration']; ?> minutes</span>
                </div>
                <div class="info-item">
                    <span class="info-label"><i class="fas fa-tag"></i> Total Price</span>
                    <span class="info-value">RM <?php echo number_format($booking['total_price'], 2); ?></span>
                </div>
            </div>
        </div>

        <!-- Date & Time Selection -->
        <div class="form-step">
            <div class="step-title">
                <h2><i class="fas fa-calendar-alt"></i> Select New Date & Time</h2>
                <p style="color: #6b7280; font-size: 0.95rem; margin-top: 0.5rem;">Choose a new date and time for your appointment</p>
            </div>

            <div class="datetime-container">
                <!-- Calendar -->
                <div class="calendar-section">
                    <div class="calendar-header">
                        <button type="button" class="btn-nav" onclick="changeMonth(-1)">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <h3 id="currentMonth"></h3>
                        <button type="button" class="btn-nav" onclick="changeMonth(1)">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                    <div class="calendar-grid" id="calendarGrid"></div>
                </div>

                <!-- Time Slots -->
                <div class="timeslots-section">
                    <h3 id="selectedDateDisplay">Select a date</h3>
                    <div id="timeSlotsContainer" class="timeslots-grid">
                        <p class="empty-state">Please select a date to view available time slots</p>
                    </div>
                </div>
            </div>

            <!-- Hidden inputs -->
            <input type="hidden" id="new_date" name="new_date" required>
            <input type="hidden" id="new_time" name="new_time" required>
        </div>

        <!-- Info Box -->
        <div class="info-box">
            <i class="fas fa-info-circle"></i>
            <p>Your appointment will be rescheduled with the same stylist and services. The same total price applies.</p>
        </div>

        <!-- Form Actions -->
        <div class="form-navigation">
            <a href="booking_details.php?id=<?php echo $appointment_id; ?>" class="btn btn-secondary">
                <i class="fas fa-times"></i>
                Cancel
            </a>
            <button type="submit" name="reschedule" class="btn btn-success" id="submitBtn" disabled>
                <i class="fas fa-check"></i>
                Confirm Reschedule
            </button>
        </div>
    </form>
</div>

<script>
    const stylistId = <?php echo $booking['stylist_id']; ?>;
    const duration = <?php echo $booking['total_duration']; ?>;
    const currentAppointmentId = <?php echo $appointment_id; ?>;
    let selectedTime = null;
    let businessHours = {};
    let holidays = []; //added
    let currentDate = new Date();
    let selectedDate = null;

    // Fetch business hours on page load
    async function fetchBusinessHours() {
        try {
            const response = await fetch('get_business_hours.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ stylist_id: stylistId })
            });
            const data = await response.json();
            businessHours = data.business_hours || data; //added
            holidays = data.holidays || []; //added
            renderCalendar();
        } catch (error) {
            console.error('Error fetching business hours:', error);
            // Fallback business hours
            businessHours = {
            };
            renderCalendar();
        }
    }

    function formatDateLocal(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    function isHolidayDate(date) {
        const dateStr = formatDateLocal(date);
        const monthDay = dateStr.slice(5);
        return holidays.some(h => (h.is_recurring && h.month_day === monthDay) || (!h.is_recurring && h.date === dateStr));
    }

    function renderCalendar() {
        const year = currentDate.getFullYear();
        const month = currentDate.getMonth();
        
        document.getElementById('currentMonth').textContent = 
            currentDate.toLocaleString('default', { month: 'long', year: 'numeric' });
        
        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0);
        const startingDayOfWeek = firstDay.getDay();
        
        const calendarGrid = document.getElementById('calendarGrid');
        calendarGrid.innerHTML = '';
        
        const dayHeaders = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        dayHeaders.forEach(day => {
            const header = document.createElement('div');
            header.className = 'calendar-day header';
            header.textContent = day;
            calendarGrid.appendChild(header);
        });
        
        for (let i = 0; i < startingDayOfWeek; i++) {
            const empty = document.createElement('div');
            empty.className = 'calendar-day disabled';
            calendarGrid.appendChild(empty);
        }
        
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        
        for (let day = 1; day <= lastDay.getDate(); day++) {
            const date = new Date(year, month, day);
            const dayOfWeek = date.toLocaleString('en-US', { weekday: 'long' });
            const holiday = isHolidayDate(date);
            
            // Check if today and past closing time
            const isPastClosingTime = isDatePastClosingTime(date, dayOfWeek);
            
            const isDisabled = date < today || !businessHours[dayOfWeek] || holiday || isPastClosingTime;
            
            const dayCell = document.createElement('div');
            dayCell.className = 'calendar-day';
            dayCell.textContent = day;
            
            if (isDisabled) {
                dayCell.classList.add('disabled');
                if (holiday) dayCell.classList.add('holiday');
            } else {
                dayCell.classList.add('available');
                dayCell.onclick = () => selectDate(date);
                
                if (selectedDate && 
                    date.getDate() === selectedDate.getDate() && 
                    date.getMonth() === selectedDate.getMonth() && 
                    date.getFullYear() === selectedDate.getFullYear()) {
                    dayCell.classList.add('selected');
                }
            }
            
            calendarGrid.appendChild(dayCell);
        }
    }

  // New function to check if date is past closing time
    function isDatePastClosingTime(date, dayOfWeek) {
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const checkDate = new Date(date);
        checkDate.setHours(0, 0, 0, 0);
        
        // Only check if it's today
        if (checkDate.getTime() !== today.getTime()) {
            return false;
        }
        
        // Check if business hours exist for this day
        if (!businessHours[dayOfWeek]) {
            return true;
        }
        
        // Get closing time
        const closingTime = businessHours[dayOfWeek].close;
        if (!closingTime) {
            return true;
        }
        
        // Create closing datetime for today
        const now = new Date();
        const todayDateStr = formatDateLocal(now);
        const closingDateTime = new Date(todayDateStr + ' ' + closingTime);
        
        // If current time is past closing time, disable the date
        return now >= closingDateTime;
    }
    
    function changeMonth(delta) {
        currentDate.setMonth(currentDate.getMonth() + delta);
        renderCalendar();
    }

    async function selectDate(date) {
        selectedDate = date;
        renderCalendar();
        
        const dateStr = date.toLocaleDateString('en-US', { 
            weekday: 'long', 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric' 
        });
        document.getElementById('selectedDateDisplay').textContent = dateStr;
        document.getElementById('new_date').value = formatDateLocal(date);
        
        await loadTimeSlots(date);
    }

    async function loadTimeSlots(date) {
        const container = document.getElementById('timeSlotsContainer');
        container.innerHTML = '<p class="empty-state">Loading available times...</p>';
        
        if (isHolidayDate(date)) {
            container.innerHTML = '<p class="empty-state">Salon is closed for a holiday on this date.</p>';
            document.getElementById('new_date').value = '';
            return;
        }

        try {
            const response = await fetch('get_available_slots.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    date: formatDateLocal(date),
                    stylist_id: stylistId,
                    duration: duration,
                    appointment_id: currentAppointmentId // added
                })
            });
            
            const slots = await response.json();
            
            if (slots.length === 0) {
                container.innerHTML = '<p class="empty-state">No available time slots for this date</p>';
                return;
            }
            
            container.innerHTML = '';
            slots.forEach(slot => {
                const slotDiv = document.createElement('div');
                slotDiv.className = 'time-slot';
                if (slot.booked) slotDiv.classList.add('booked');
                slotDiv.textContent = formatTime(slot.time);
                
                if (!slot.booked) {
                    slotDiv.onclick = () => selectTimeSlot(slot.time, slotDiv);
                }
                
                container.appendChild(slotDiv);
            });
        } catch (error) {
            container.innerHTML = '<p class="empty-state">Error loading time slots</p>';
            console.error('Error:', error);
        }
    }

    function selectTimeSlot(time, element) {
        document.querySelectorAll('.time-slot').forEach(el => el.classList.remove('selected'));
        element.classList.add('selected');
        selectedTime = time;
        document.getElementById('new_time').value = time;
        document.getElementById('submitBtn').disabled = false;
    }

    function formatTime(time) {
        const [hours, minutes] = time.split(':');
        const hour = parseInt(hours);
        const ampm = hour >= 12 ? 'PM' : 'AM';
        const displayHour = hour === 0 ? 12 : hour > 12 ? hour - 12 : hour;
        return `${displayHour}:${minutes} ${ampm}`;
    }

    // Form validation
    document.getElementById('rescheduleForm').addEventListener('submit', function(e) {
        if (!selectedTime) {
            e.preventDefault();
            alert('Please select a time slot');
        }
    });

    // Initialize
    fetchBusinessHours();
</script>

</body>
</html>
