<?php
require_once '../config.php';
require_once '../users_management/auth_check.php';
requireLogin(['Admin', 'Customer']);
$pageCSS = '../css/my_bookings.css';
$user_id = $_SESSION['user_id']; 
include '../head.php';

// Fetch all appointments
try {
    $sql = "SELECT a.*, s.service_name, s.price, u.name as stylist_name
            FROM appointment a
            JOIN appointmentitem ai ON a.appointment_id = ai.appointment_id
            JOIN service s ON ai.service_id = s.service_id
            JOIN stylist st ON a.stylist_id = st.stylist_id
            JOIN user u ON st.user_id = u.user_id
            WHERE a.user_id = :user_id
            ORDER BY a.appointment_date DESC, a.appointment_time DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':user_id' => $user_id]);
    $all_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Categorize bookings
    $upcoming = [];
    $past = [];
    $cancelled = [];
    $today = date('Y-m-d');
    
    foreach ($all_bookings as $booking) {
        if ($booking['status'] === 'Cancelled') {
            $cancelled[] = $booking;
        } elseif ($booking['appointment_date'] >= $today) {
            // System uses only Confirmed/Cancelled; treat any non-cancelled booking as Confirmed.
            $upcoming[] = $booking;
        } else {
            $past[] = $booking;
        }
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings - Cosmos Salon</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <?php if(isset($pageCSS)): ?>
    <link rel="stylesheet" href="<?php echo $pageCSS; ?>">
    <?php endif; ?>
</head>
<body>

<div class="bookings-container">
    <!-- Page Header with Add Booking Button -->
    <div class="page-header">
        <div class="header-flex">
            <div>
                <h1>My Bookings</h1>
                <p>View and manage your salon appointments</p>
            </div>
            <a href="appointment.php" class="btn btn-add-booking">
                <i class="fas fa-plus"></i>
                Add Booking
            </a>
        </div>
    </div>

    <!-- Tabs -->
    <div class="tabs">
        <button class="tab active" onclick="switchTab('upcoming')">
            Upcoming
        </button>
        <button class="tab" onclick="switchTab('past')">
            Past
        </button>
        <button class="tab" onclick="switchTab('cancelled')">
            Cancelled
        </button>
    </div>

    <!-- Upcoming Bookings -->
    <div id="upcoming-bookings" class="bookings-grid">
        <?php if (empty($upcoming)): ?>
            <div class="empty-state">
                <i class="far fa-calendar-times"></i>
                <h3>No Upcoming Bookings</h3>
                <p>You don't have any upcoming appointments</p>
                <a href="appointment.php" class="btn btn-book">Book Now</a>
            </div>
        <?php else: ?>
            <?php foreach ($upcoming as $booking): 
                $date = new DateTime($booking['appointment_date']);
            ?>
            <div class="booking-card">
                <div class="booking-date">
                    <div class="day"><?php echo $date->format('d'); ?></div>
                    <div class="month"><?php echo strtoupper($date->format('M')); ?></div>
                </div>
                
                <div class="booking-info">
                    <div class="booking-service"><?php echo htmlspecialchars($booking['service_name']); ?></div>
                    <div class="booking-details">
                        <div class="detail-item">
                            <i class="far fa-clock"></i>
                            <span><?php echo date('g:i A', strtotime($booking['appointment_time'])); ?></span>
                        </div>
                        <div class="detail-item">
                            <i class="fas fa-user-tie"></i>
                            <span><?php echo htmlspecialchars($booking['stylist_name']); ?></span>
                        </div>
                        <div class="detail-item">
                            <i class="fas fa-money-bill-wave"></i>
                            <span>RM <?php echo number_format($booking['total_price'], 2); ?></span>
                        </div>
                    </div>
                    <span class="booking-status status-confirmed">Confirmed</span>
                </div>
                
                <div class="booking-actions">
                    <a href="booking_details.php?id=<?php echo $booking['appointment_id']; ?>" class="btn btn-view">View Details</a>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Past Bookings (Hidden by default) -->
    <div id="past-bookings" class="bookings-grid" style="display: none;">
        <?php if (empty($past)): ?>
            <div class="empty-state">
                <i class="far fa-calendar-check"></i>
                <h3>No Past Bookings</h3>
                <p>You don't have any past appointments</p>
            </div>
        <?php else: ?>
            <?php foreach ($past as $booking): 
                $date = new DateTime($booking['appointment_date']);
            ?>
            <div class="booking-card">
                <div class="booking-date">
                    <div class="day"><?php echo $date->format('d'); ?></div>
                    <div class="month"><?php echo strtoupper($date->format('M')); ?></div>
                </div>
                
                <div class="booking-info">
                    <div class="booking-service"><?php echo htmlspecialchars($booking['service_name']); ?></div>
                    <div class="booking-details">
                        <div class="detail-item">
                            <i class="far fa-clock"></i>
                            <span><?php echo date('g:i A', strtotime($booking['appointment_time'])); ?></span>
                        </div>
                        <div class="detail-item">
                            <i class="fas fa-user-tie"></i>
                            <span><?php echo htmlspecialchars($booking['stylist_name']); ?></span>
                        </div>
                        <div class="detail-item">
                            <i class="fas fa-money-bill-wave"></i>
                            <span>RM <?php echo number_format($booking['total_price'], 2); ?></span>
                        </div>
                    </div>
                    <span class="booking-status status-confirmed">Confirmed</span>
                </div>
                
                <div class="booking-actions">
                    <a href="booking_details.php?id=<?php echo $booking['appointment_id']; ?>" class="btn btn-view">View Details</a>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Cancelled Bookings (Hidden by default) -->
    <div id="cancelled-bookings" class="bookings-grid" style="display: none;">
        <?php if (empty($cancelled)): ?>
            <div class="empty-state">
                <i class="far fa-calendar-times"></i>
                <h3>No Cancelled Bookings</h3>
                <p>You don't have any cancelled appointments</p>
            </div>
        <?php else: ?>
            <?php foreach ($cancelled as $booking): 
                $date = new DateTime($booking['appointment_date']);
            ?>
            <div class="booking-card">
                <div class="booking-date">
                    <div class="day"><?php echo $date->format('d'); ?></div>
                    <div class="month"><?php echo strtoupper($date->format('M')); ?></div>
                </div>
                
                <div class="booking-info">
                    <div class="booking-service"><?php echo htmlspecialchars($booking['service_name']); ?></div>
                    <div class="booking-details">
                        <div class="detail-item">
                            <i class="far fa-clock"></i>
                            <span><?php echo date('g:i A', strtotime($booking['appointment_time'])); ?></span>
                        </div>
                        <div class="detail-item">
                            <i class="fas fa-user-tie"></i>
                            <span><?php echo htmlspecialchars($booking['stylist_name']); ?></span>
                        </div>
                        <div class="detail-item">
                            <i class="fas fa-money-bill-wave"></i>
                            <span>RM <?php echo number_format($booking['total_price'], 2); ?></span>
                        </div>
                    </div>
                    <span class="booking-status status-cancelled">Cancelled</span>
                </div>
                
                <div class="booking-actions">
                    <a href="booking_details.php?id=<?php echo $booking['appointment_id']; ?>" class="btn btn-view">View Details</a>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
    function switchTab(tabName) {
        // Update tab buttons
        document.querySelectorAll('.tab').forEach(tab => {
            tab.classList.remove('active');
        });
        event.target.classList.add('active');

        // Hide all booking sections
        document.getElementById('upcoming-bookings').style.display = 'none';
        document.getElementById('past-bookings').style.display = 'none';
        document.getElementById('cancelled-bookings').style.display = 'none';

        // Show selected section
        document.getElementById(tabName + '-bookings').style.display = 'grid';
    }
</script>

</body>
</html>
