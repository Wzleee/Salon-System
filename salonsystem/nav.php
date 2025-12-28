<?php
//  Make sure logged in
if (!isset($_SESSION['user_role'])) {
    header('Location: ../users_management/login.php');
    exit();
}

$userRole = $_SESSION['user_role'];
?>

<!-- Navigation Tabs -->
<div class="nav-tabs">
    
    
    <?php if ($userRole === 'Admin'): ?>
        <a href="../users_management/users.php" class="nav-tab <?php echo ($currentPage == 'users') ? 'active' : ''; ?>">
            <i class="fas fa-users"></i>
            Users
        </a>
        <a href="../users_management/stylist.php" class="nav-tab <?php echo ($currentPage == 'stylist') ? 'active' : ''; ?>">
            <i class="fas fa-user-tie"></i>
            Stylists
        </a>
        <a href="../schedule_service/services.php" class="nav-tab <?php echo ($currentPage == 'services') ? 'active' : ''; ?>">
            <i class="fas fa-briefcase"></i>
            Services
        </a>
    <?php endif; ?>
    
    <?php if ($userRole === 'Admin' || $userRole === 'Staff'): ?>
        <a href="../schedule_service/schedule.php" class="nav-tab <?php echo ($currentPage == 'schedule') ? 'active' : ''; ?>">
            <i class="far fa-clock"></i>
            Schedule
        </a>
    <?php endif; ?>

    <?php if ($userRole === 'Admin'): ?>
        <a href="../schedule_service/bookings.php" class="nav-tab <?php echo ($currentPage == 'bookings') ? 'active' : ''; ?>">
            <i class="far fa-calendar"></i>
            Bookings
        </a>
        <a href="../schedule_service/hours.php" class="nav-tab <?php echo ($currentPage == 'hours') ? 'active' : ''; ?>">
            <i class="far fa-calendar-alt"></i>
            Hours
        </a>
        <a href="../Report/reports.php" class="nav-tab <?php echo ($currentPage == 'reports') ? 'active' : ''; ?>">
            <i class="fas fa-chart-bar"></i>
            Reports
        </a> 
        <a href="../users_management/auth_logs.php" class="nav-tab <?php echo ($currentPage == 'auth_logs') ? 'active' : ''; ?>">
            <i class="far fa-calendar-alt"></i>
            Auth Logs
        </a>
    <?php endif; ?>
    
    <?php if ($userRole === 'Customer'): ?>
        <a href="../appointment/appointment.php" class="nav-tab <?php echo ($currentPage == 'appointment') ? 'active' : ''; ?>">
            <i class="far fa-calendar-plus"></i>
            Book Appointment
        </a>
        <a href="../appointment/my_bookings.php" class="nav-tab <?php echo ($currentPage == 'my_bookings') ? 'active' : ''; ?>">
            <i class="far fa-calendar-check"></i>
            My Bookings
        </a>
    <?php endif; ?>
</div>
