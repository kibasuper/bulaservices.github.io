
<?php
require_once __DIR__ . '/server/gym_functions.php';
require_once __DIR__ . '/db.php';

header("Content-Type: application/json");

$action = $_GET['action'] ?? '';

if ($action === 'get_available_slots') {
    $date = $_GET['date'] ?? '';
    echo json_encode(getAvailableSlots($date));
}
elseif ($action === 'make_reservation') {
    $data = json_decode(file_get_contents("php://input"), true);
    echo json_encode(makeReservation($data));
}
else {
    echo json_encode(["success" => false, "message" => "Invalid action"]);
}
