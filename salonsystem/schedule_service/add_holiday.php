<?php
session_start();
header('Content-Type: application/json');

require_once '../config.php';
require_once '../users_management/auth_check.php';
requireLogin(['Admin']);

function respond($success, $message, $extra = []) {
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    respond(false, 'Method not allowed');
}

$input = json_decode(file_get_contents('php://input'), true);
$holidayName = isset($input['holiday_name']) ? trim($input['holiday_name']) : '';
$holidayDate = isset($input['holiday_date']) ? $input['holiday_date'] : '';
$isRecurring = isset($input['is_recurring']) && $input['is_recurring'] ? 1 : 0;

if ($holidayName === '' || $holidayDate === '') {
    respond(false, 'Holiday name and date are required.');
}

function normalizeDate($value) {
    $formats = ['Y-m-d', 'm/d/Y', 'd/m/Y'];
    foreach ($formats as $fmt) {
        $dt = DateTime::createFromFormat($fmt, $value);
        $errors = DateTime::getLastErrors();
        $errorCount = is_array($errors) ? ($errors['error_count'] ?? 0) : 0;
        $warningCount = is_array($errors) ? ($errors['warning_count'] ?? 0) : 0;
        if ($dt && $errorCount === 0 && $warningCount === 0) {
            return $dt->format('Y-m-d');
        }
    }
    return null;
}

$normalizedDate = normalizeDate($holidayDate);
if (!$normalizedDate) {
    respond(false, 'Invalid date format. Use YYYY-MM-DD or MM/DD/YYYY.');
}

$today = (new DateTime('today'))->format('Y-m-d');
if ($normalizedDate < $today) {
    respond(false, 'Holiday date cannot be in the past.');
}

try {
    // check duplicate for same date/recurring
    $dupStmt = $pdo->prepare("SELECT 1 FROM holiday WHERE holiday_date = :date AND is_recurring = :rec LIMIT 1");
    $dupStmt->execute([':date' => $normalizedDate, ':rec' => $isRecurring]);
    if ($dupStmt->fetchColumn()) {
        respond(false, 'Holiday already exists for this date');
    }

    $stmt = $pdo->prepare("INSERT INTO holiday (holiday_name, holiday_date, is_recurring, created_at, updated_at)
                           VALUES (:name, :date, :recurring, NOW(), NOW())");
    $stmt->execute([
        ':name' => $holidayName,
        ':date' => $normalizedDate,
        ':recurring' => $isRecurring,
    ]);

    $newId = $pdo->lastInsertId();

    respond(true, 'Holiday added.', [
        'holiday' => [
            'holiday_id' => $newId,
            'holiday_name' => $holidayName,
            'holiday_date' => $normalizedDate,
            'is_recurring' => $isRecurring,
        ],
    ]);
} catch (Exception $e) {
    http_response_code(500);
    respond(false, 'Database error: ' . $e->getMessage());
}
?>
