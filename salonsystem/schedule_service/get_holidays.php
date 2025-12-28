<?php
session_start();
header('Content-Type: application/json');

require_once '../config.php';
require_once '../users_management/auth_check.php';
requireLogin(['Admin']);

try {
    $sql = "SELECT holiday_id, holiday_name, holiday_date, is_recurring
            FROM holiday
            ORDER BY holiday_date ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $holidays = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'holidays' => $holidays,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage(),
    ]);
}
?>
