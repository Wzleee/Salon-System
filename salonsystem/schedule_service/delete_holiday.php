<?php
session_start();
header('Content-Type: application/json');

require_once '../config.php';
require_once '../users_management/auth_check.php';
requireLogin(['Admin']);

function respond($success, $message) {
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    respond(false, 'Method not allowed');
}

$input = json_decode(file_get_contents('php://input'), true);
$holidayId = isset($input['holiday_id']) ? (int)$input['holiday_id'] : 0;

if ($holidayId <= 0) {
    respond(false, 'Invalid holiday id.');
}

try {
    $stmt = $pdo->prepare("DELETE FROM holiday WHERE holiday_id = :id");
    $stmt->execute([':id' => $holidayId]);

    if ($stmt->rowCount() === 0) {
        respond(false, 'Holiday not found.');
    }

    respond(true, 'Holiday deleted.');
} catch (Exception $e) {
    http_response_code(500);
    respond(false, 'Database error: ' . $e->getMessage());
}
?>
