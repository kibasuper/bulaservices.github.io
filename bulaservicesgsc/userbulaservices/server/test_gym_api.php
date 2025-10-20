<?php
require_once __DIR__ . '/gym_functions.php';

// Pick a test date (change as needed)
$testDate = date('Y-m-d', strtotime('+1 day')); // tomorrow

header('Content-Type: application/json');

$result = getAvailableTimeSlots($testDate);

echo json_encode([
    'test_date' => $testDate,
    'result' => $result
], JSON_PRETTY_PRINT);
