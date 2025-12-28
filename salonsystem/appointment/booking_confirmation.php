<?php
require_once '../config.php';
require_once '../users_management/auth_check.php';
requireLogin(['Admin', 'Customer']);
$pageCSS = '../css/booking_confirmation.css';
$user_id = $_SESSION['user_id'];

// Get appointment details from URL parameters
$appointment_id = $_GET['id'] ?? null;

if (!$appointment_id) {
    header('Location: appointment.php');
    exit;
}

// Fetch appointment details with ALL services
try {
    // Get appointment info
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
                cu.email as customer_email
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
        header('Location: appointment.php');
        exit;
    }

    // Get all services for this appointment
    $sql = "SELECT 
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Confirmed - Cosmos Salon</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
   <?php if(isset($pageCSS)): ?>
    <link rel="stylesheet" href="<?php echo $pageCSS; ?>">
    <?php endif; ?>
</head>
<body>

<div class="confirmation-container">
    <div class="success-header">
        <div class="success-icon">
            <i class="fas fa-check"></i>
        </div>
        <h1>Booking Confirmed!</h1>
        <p>Your appointment has been successfully booked.</p>
    </div>

    <div class="booking-details">
        <div class="detail-section">
            <!-- Services Section -->
            <div class="detail-item">
                <div class="detail-icon">
                    <i class="fas fa-cut"></i>
                </div>
                <div class="detail-content">
                    <div class="detail-label">Service<?php echo count($services) > 1 ? 's' : ''; ?></div>
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
                        
                        <!-- Total Row -->
                        <div class="total-row">
                            <div>
                                <div class="label">Total</div>
                                <div class="service-meta"><?php echo $total_duration; ?> min total</div>
                            </div>
                            <div class="value">
                                RM <?php echo number_format($booking['total_price'], 2); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="detail-item">
                <div class="detail-icon">
                    <i class="far fa-calendar"></i>
                </div>
                <div class="detail-content">
                    <div class="detail-label">Date</div>
                    <div class="detail-value"><?php echo $date; ?></div>
                </div>
            </div>

            <div class="detail-item">
                <div class="detail-icon">
                    <i class="far fa-clock"></i>
                </div>
                <div class="detail-content">
                    <div class="detail-label">Time</div>
                    <div class="detail-value"><?php echo $time; ?></div>
                </div>
            </div>

            <div class="detail-item">
                <div class="detail-icon">
                    <i class="fas fa-user-tie"></i>
                </div>
                <div class="detail-content">
                    <div class="detail-label">Stylist</div>
                    <div class="detail-value"><?php echo htmlspecialchars($booking['stylist_name']); ?></div>
                    <div class="detail-sub"><?php echo htmlspecialchars($booking['specialization']); ?></div>
                </div>
            </div>
        </div>

        <div class="info-box">
            <p>
                <i class="fas fa-info-circle"></i>
                A confirmation email has been sent to <strong><?php echo htmlspecialchars($booking['customer_email']); ?></strong>. 
                You'll receive a reminder 24 hours before your appointment.
            </p>
        </div>
    </div>

    <div class="actions">
        <a href="my_bookings.php" class="btn btn-primary">
            <i class="fas fa-calendar-check"></i> View My Bookings
        </a>
        <a href="appointment.php" class="btn btn-secondary">
            <i class="fas fa-plus"></i> Book Another Appointment
        </a>
    </div>
</div>

</body>
</html>