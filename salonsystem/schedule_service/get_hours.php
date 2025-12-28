<?php
session_start();
header('Content-Type: application/json');

require_once '../config.php';
require_once '../users_management/auth_check.php';
requireLogin(['Admin']);


try {
    
    $sql = "SELECT day_of_week, opening_time, closing_time, is_closed
            FROM businesshours
            ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')";
            
    $stmt = $pdo->prepare($sql); 
    
    $stmt->execute();
    $hours = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    //  HH:MM
    foreach ($hours as &$hour) {
        $hour['opening_time'] = substr($hour['opening_time'], 0, 5);
        $hour['closing_time'] = substr($hour['closing_time'], 0, 5);
        $hour['is_closed'] = (bool)$hour['is_closed'];
    }
    unset($hour);
    
    echo json_encode([
        'success' => true,
        'hours' => $hours
    ]);
    
} catch (Exception $e) {
    http_response_code(500); 
    echo json_encode([
        'success' => false,
        'message' => 'Database Error: ' . $e->getMessage()
    ]);
}
?>
