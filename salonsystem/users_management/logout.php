<?php
session_start();
require_once '../config.php';

// Log out (before the session is destroyed)
if (isset($_SESSION['user_id'])) {
    try {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $stmt = $pdo->prepare("
            INSERT INTO auditlog (user_id, action, category, description, ip_address) 
            VALUES (?, 'Logged out', 'logout', 'User logged out from the system', ?)
        ");
        $stmt->execute([$_SESSION['user_id'], $ip_address]);
    } catch (PDOException $e) {
        error_log("Logout log error: " . $e->getMessage());
    }
}

// Unset all session variables
$_SESSION = array();

// Destroy the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Destroy the session
session_destroy();

// Redirect to login page with success message
header('Location: login.php?logout=success');
exit();
?>