<?php
require_once '../config.php';
require_once '../users_management/auth_check.php';
requireLogin(['Admin', 'Customer']);
require_once 'email_utility.php';
$flashPath = '../flash_message.php';
$pageCSS = '../css/booking_details.css';
$user_id = $_SESSION['user_id'];

// Get appointment details from URL parameters
$appointment_id = $_GET['id'] ?? null;

if (!$appointment_id) {
    header('Location: my_bookings.php');
    exit;
}

// Normalize query-based success flags into flash messages
if (isset($_GET['rescheduled'])) {
    $_SESSION['success_message'] = "Your appointment has been rescheduled successfully.";
    header('Location: booking_details.php?id=' . urlencode($appointment_id));
    exit;
}

if (isset($_GET['cancelled'])) {
    $_SESSION['success_message'] = "Your appointment has been cancelled.";
    header('Location: booking_details.php?id=' . urlencode($appointment_id));
    exit;
}

// Handle cancellation
if (isset($_POST['cancel_booking'])) {
    try {
        // Check if appointment is today or in the past
        $checkSql = "SELECT appointment_date FROM appointment 
                     WHERE appointment_id = :appointment_id 
                     AND user_id = :user_id";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->execute([
            ':appointment_id' => $appointment_id,
            ':user_id' => $user_id
        ]);
        $appointmentDate = $checkStmt->fetchColumn();
        
        if ($appointmentDate) {
            $today = date('Y-m-d');
            
            // Prevent cancellation if appointment is today or in the past
            if (strtotime($appointmentDate) <= strtotime($today)) {
                $error_message = "Cancellations must be made at least 1 day in advance. You cannot cancel an appointment on the day of the appointment or after it has passed.";
                $_SESSION['error_message'] = $error_message;
            } else {
                // Proceed with cancellation
                $sql = "UPDATE appointment SET status = 'Cancelled' WHERE appointment_id = :appointment_id"
                     . " AND user_id = :user_id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':appointment_id' => $appointment_id,
                    ':user_id' => $user_id
                ]);
                
                if ($stmt->rowCount() > 0) {
                    try {
                        $infoStmt = $pdo->prepare("
                            SELECT 
                                a.appointment_id,
                                a.appointment_date,
                                a.appointment_time,
                                a.total_price,
                                u.name as stylist_name,
                                cu.name as customer_name,
                                cu.email as customer_email
                            FROM appointment a
                            JOIN stylist st ON a.stylist_id = st.stylist_id
                            JOIN user u ON st.user_id = u.user_id
                            JOIN user cu ON a.user_id = cu.user_id
                            WHERE a.appointment_id = :appointment_id
                            AND a.user_id = :user_id
                        ");
                        $infoStmt->execute([
                            ':appointment_id' => $appointment_id,
                            ':user_id' => $user_id
                        ]);
                        $info = $infoStmt->fetch(PDO::FETCH_ASSOC);

                        $serviceStmt = $pdo->prepare("
                            SELECT 
                                s.service_id,
                                s.service_name,
                                s.duration_minutes,
                                ai.service_price
                            FROM appointmentitem ai
                            JOIN service s ON ai.service_id = s.service_id
                            WHERE ai.appointment_id = :appointment_id
                        ");
                        $serviceStmt->execute([':appointment_id' => $appointment_id]);
                        $servicesForEmail = $serviceStmt->fetchAll(PDO::FETCH_ASSOC);

                        if ($info) {
                            $bookingDetails = [
                                'appointment_id' => $info['appointment_id'],
                                'appointment_date' => $info['appointment_date'],
                                'appointment_time' => $info['appointment_time'],
                                'stylist_name' => $info['stylist_name'],
                                'total_price' => $info['total_price'],
                                'services' => $servicesForEmail
                            ];
                            sendCancellationConfirmation($info['customer_email'], $info['customer_name'], $bookingDetails);
                        }
                    } catch (Exception $e) {
                        error_log("Cancellation email error: " . $e->getMessage());
                    }

                    $_SESSION['success_message'] = "Your appointment has been cancelled.";
                    header('Location: booking_details.php?id=' . urlencode($appointment_id));
                    exit;
                } else {
                    $error_message = "Unable to cancel appointment. Please contact support.";
                    $_SESSION['error_message'] = $error_message;
                }
            }
        } else {
            $error_message = "Appointment not found.";
            $_SESSION['error_message'] = $error_message;
        }
    } catch (PDOException $e) {
        $error_message = "Error cancelling appointment: " . $e->getMessage();
        $_SESSION['error_message'] = $error_message;
    }
}

// Fetch appointment details
try {
    // Get main appointment info
    $sql = "SELECT 
                a.appointment_id,
                a.appointment_date,
                a.appointment_time,
                a.total_price,
                a.status,
                st.stylist_id,
                u.name as stylist_name,
                st.specialization,
                cu.name as customer_name,
                cu.email as customer_email,
                cu.phone as customer_phone
            FROM appointment a
            JOIN stylist st ON a.stylist_id = st.stylist_id
            JOIN user u ON st.user_id = u.user_id
            JOIN user cu ON a.user_id = cu.user_id
            WHERE a.appointment_id = :appointment_id
            AND a.user_id = :user_id";

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

    // Get all services for this appointment
    $sql = "SELECT 
                s.service_id,
                s.service_name,
                s.duration_minutes,
                ai.service_price
            FROM appointmentitem ai
            JOIN service s ON ai.service_id = s.service_id
            WHERE ai.appointment_id = :appointment_id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':appointment_id' => $appointment_id]);
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate total duration
    $total_duration = array_sum(array_column($services, 'duration_minutes'));
    
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

// Format date and time
$date = date('l, F j, Y', strtotime($booking['appointment_date']));
$time = date('g:i A', strtotime($booking['appointment_time']));

// Updated cancellation logic - cannot cancel on the day of appointment or after
$today = date('Y-m-d');
$displayStatus = ($booking['status'] === 'Cancelled') ? 'Cancelled' : 'Confirmed';
$appointmentDateTime = strtotime($booking['appointment_date'] . ' ' . $booking['appointment_time']);
$isPastAppointment = $appointmentDateTime < time();
$statusClass = strtolower($displayStatus);
$statusStateClass = ($displayStatus === 'Confirmed')
    ? ($isPastAppointment ? 'status-past' : 'status-upcoming')
    : '';
$canCancel = $displayStatus === 'Confirmed' && strtotime($booking['appointment_date']) > strtotime($today);

// Can reschedule if confirmed and appointment is in the future
$canReschedule = $displayStatus === 'Confirmed' && strtotime($booking['appointment_date']) >= strtotime($today);

// Create service list for calendar
$service_names = implode(', ', array_column($services, 'service_name'));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Details - Cosmos Salon</title>
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

<div class="details-container">
    <a href="my_bookings.php" class="back-btn">
        <i class="fas fa-arrow-left"></i>
        Back to My Bookings
    </a>
    <?php if (file_exists($flashPath)) { include $flashPath; } ?>

    <!-- Page Header -->
    <div class="page-header">
        <div class="header-info">
            <h1>Booking Details</h1>
            <p class="booking-id">Booking ID: <strong>#<?php echo str_pad($booking['appointment_id'], 6, '0', STR_PAD_LEFT); ?></strong></p>
        </div>
        <span class="status-badge status-<?php echo $statusClass; ?> <?php echo $statusStateClass; ?>">
            <?php echo $displayStatus; ?>
        </span>
    </div>

    <!-- Info Box for Confirmed Bookings -->
    <?php if ($displayStatus === 'Confirmed'): ?>
        <div class="info-box">
            <p>
                <i class="fas fa-info-circle"></i>
                <strong>Reminder:</strong> Please arrive 10 minutes before your scheduled appointment time. 
                Cancellations must be made at least <strong>1 day in advance</strong> (before the day of the appointment).
            </p>
        </div>
    <?php endif; ?>

   <!-- Action Buttons -->
 <?php if ($displayStatus === 'Confirmed'): ?>
     <div class="action-buttons">
         <?php if ($canReschedule): ?>
             <a href="reschedule.php?id=<?php echo $booking['appointment_id']; ?>" class="btn btn-reschedule">
                 <i class="fas fa-calendar-alt"></i>
                 Reschedule
             </a>
         <?php endif; ?>
         
         <button class="btn btn-calendar" onclick="addToCalendar()">
             <i class="fas fa-calendar-plus"></i>
             Add to Calendar
         </button>

         <?php if ($canCancel): ?>
             <button class="btn btn-cancel" onclick="openCancelModal()">
                 <i class="fas fa-times-circle"></i>
                 Cancel Booking
             </button>
         <?php else: ?>
             <!-- Show disabled cancel button with tooltip if appointment is today -->
             <?php if (strtotime($booking['appointment_date']) == strtotime($today)): ?>
                 <button class="btn btn-cancel" disabled style="opacity: 0.5; cursor: not-allowed;" 
                         title="Cannot cancel on the day of appointment">
                     <i class="fas fa-times-circle"></i>
                     Cancel Booking
                 </button>
             <?php endif; ?>
         <?php endif; ?>
     </div>
 <?php elseif ($displayStatus === 'Cancelled'): ?>
     <div class="action-buttons">
         <a href="appointment.php" class="btn btn-reschedule">
             <i class="fas fa-redo"></i>
             Book New Appointment
         </a>
     </div>
 <?php endif; ?>

    <!-- Booking Details - 2 Column Grid -->
    <div class="details-grid">
        <!-- Service Details -->
        <div class="detail-card">
            <div class="card-header">
                <div class="card-icon">
                    <i class="fas fa-cut"></i>
                </div>
                <h2>Service<?php echo count($services) > 1 ? 's' : ''; ?> Details</h2>
            </div>
            
            <div class="services-list">
                <?php foreach ($services as $service): ?>
                    <div class="service-item">
                        <div class="service-info">
                            <h4><?php echo htmlspecialchars($service['service_name']); ?></h4>
                            <div class="service-meta">
                                <i class="far fa-clock"></i>
                                <?php echo $service['duration_minutes']; ?> min
                            </div>
                        </div>
                        <div class="service-price">
                            RM <?php echo number_format($service['service_price'], 2); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="total-summary">
                <div>
                    <div class="label">Total</div>
                    <div class="duration"><?php echo $total_duration; ?> min total</div>
                </div>
                <div class="value">
                    RM <?php echo number_format($booking['total_price'], 2); ?>
                </div>
            </div>
        </div>

        <!-- Appointment Details -->
        <div class="detail-card">
            <div class="card-header">
                <div class="card-icon">
                    <i class="far fa-calendar"></i>
                </div>
                <h2>Appointment Details</h2>
            </div>
            <div class="detail-row">
                <span class="detail-label">Date</span>
                <span class="detail-value"><?php echo $date; ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Time</span>
                <span class="detail-value"><?php echo $time; ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Duration</span>
                <span class="detail-value"><?php echo $total_duration; ?> minutes</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Stylist</span>
                <div>
                    <div class="detail-value"><?php echo htmlspecialchars($booking['stylist_name']); ?></div>
                    <div class="detail-sub"><?php echo htmlspecialchars($booking['specialization']); ?></div>
                </div>
            </div>
        </div>

        <!-- Customer Details -->
        <div class="detail-card">
            <div class="card-header">
                <div class="card-icon">
                    <i class="fas fa-user"></i>
                </div>
                <h2>Customer Details</h2>
            </div>
            <div class="detail-row">
                <span class="detail-label">Name</span>
                <span class="detail-value"><?php echo htmlspecialchars($booking['customer_name']); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Email</span>
                <span class="detail-value"><?php echo htmlspecialchars($booking['customer_email']); ?></span>
            </div>
            <?php if ($booking['customer_phone']): ?>
                <div class="detail-row">
                    <span class="detail-label">Phone</span>
                    <span class="detail-value"><?php echo htmlspecialchars($booking['customer_phone']); ?></span>
                </div>
            <?php endif; ?>
        </div>

        <!-- Location -->
        <div class="detail-card">
            <div class="card-header">
                <div class="card-icon">
                    <i class="fas fa-map-marker-alt"></i>
                </div>
                <h2>Location</h2>
            </div>
            <div class="detail-row">
                <span class="detail-label">Address</span>
                <div>
                    <div class="detail-value">Cosmos Salon</div>
                    <div class="detail-sub">Setapak Central Mall
Jalan Taman Ibu Kota, Taman Danau Kota
53300 Kuala Lumpur, Malaysia</div>
                </div>
            </div>
            <div class="detail-row">
                <span class="detail-label">Phone</span>
                <span class="detail-value">+60 12-345 6789</span>
            </div>
        </div>
    </div>
</div>

<!-- Cancel Confirmation Modal -->
<div class="modal" id="cancelModal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <h3>Cancel Booking?</h3>
        </div>
        <div class="modal-body">
            <p>Are you sure you want to cancel this appointment?</p>
            <p><strong>Services:</strong></p>
            <ul style="margin: 0.5rem 0; padding-left: 1.5rem;">
                <?php foreach ($services as $service): ?>
                    <li><?php echo htmlspecialchars($service['service_name']); ?></li>
                <?php endforeach; ?>
            </ul>
            <p><strong>Date:</strong> <?php echo $date; ?> at <?php echo $time; ?></p>
            <p style="color: #ef4444; font-weight: 600; margin-top: 1rem;">This action cannot be undone.</p>
        </div>
        <form method="POST" class="modal-actions">
            <button type="submit" name="cancel_booking" class="btn btn-confirm-cancel">
                Yes, Cancel Booking
            </button>
            <button type="button" class="btn btn-keep" onclick="closeCancelModal()">
                Keep Booking
            </button>
        </form>
    </div>
</div>

<script>
    function openCancelModal() {
        document.getElementById('cancelModal').classList.add('active');
    }

    function closeCancelModal() {
        document.getElementById('cancelModal').classList.remove('active');
    }

    // Close modal when clicking outside
    document.getElementById('cancelModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeCancelModal();
        }
    });

    function addToCalendar() {
        const title = "<?php echo addslashes($service_names); ?> at Cosmos Salon";
        const details = "Stylist: <?php echo addslashes($booking['stylist_name']); ?>\nServices: <?php echo addslashes($service_names); ?>";
        const location = "Cosmos Salon, Setapak Central Mall, Kuala Lumpur";
        const startDate = "<?php echo date('Ymd', strtotime($booking['appointment_date'])); ?>T<?php echo date('His', strtotime($booking['appointment_time'])); ?>";
        const endDate = "<?php echo date('Ymd', strtotime($booking['appointment_date'])); ?>T<?php echo date('His', strtotime($booking['appointment_time'] . ' + ' . $total_duration . ' minutes')); ?>";
        
        const googleCalendarUrl = `https://calendar.google.com/calendar/render?action=TEMPLATE&text=${encodeURIComponent(title)}&details=${encodeURIComponent(details)}&location=${encodeURIComponent(location)}&dates=${startDate}/${endDate}`;
        
        window.open(googleCalendarUrl, '_blank');
    }
</script>

</body>
</html>
