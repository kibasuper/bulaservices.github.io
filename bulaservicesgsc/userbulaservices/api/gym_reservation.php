<?php
require_once __DIR__ . '/../../server/config.php';

// Set content type to JSON
header('Content-Type: application/json');

// Enable CORS for development
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Log requests for debugging
error_log("Gym API request: " . $_SERVER['REQUEST_METHOD'] . " " . $_SERVER['REQUEST_URI']);

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required', 'code' => 'not_logged_in']);
    exit;
}

// Get the request method
$method = $_SERVER['REQUEST_METHOD'];

try {
    $pdo = getDBConnection();
    
    // Log the request details
    error_log("Request method: $method");
    if ($method == 'POST' || $method == 'PUT') {
        $input = json_decode(file_get_contents('php://input'), true);
        error_log("Request data: " . print_r($input, true));
    } else {
        error_log("GET params: " . print_r($_GET, true));
    }
    
    switch ($method) {
        case 'GET':
            // Get available time slots for a specific date and facility
            if (isset($_GET['action']) && $_GET['action'] === 'available_slots') {
                $facilityId = filter_input(INPUT_GET, 'facility_id', FILTER_VALIDATE_INT);
                $date = filter_input(INPUT_GET, 'date', FILTER_SANITIZE_STRING);
                
                if (!$facilityId || !$date) {
                    throw new Exception('Facility ID and date are required');
                }
                
                // Validate date format
                if (!DateTime::createFromFormat('Y-m-d', $date)) {
                    throw new Exception('Invalid date format. Use YYYY-MM-DD');
                }
                
                // Get day of week from date
                $dayOfWeek = date('l', strtotime($date));
                
                // Get available time slots for this facility and day
                $stmt = $pdo->prepare("
                    SELECT ts.*, 
                    (ts.max_capacity - IFNULL((
                        SELECT COUNT(*) 
                        FROM gym_reservations gr 
                        WHERE gr.time_slot_id = ts.id 
                        AND gr.reservation_date = :date 
                        AND gr.status IN ('pending', 'confirmed')
                    ), 0)) as available_slots
                    FROM gym_time_slots ts
                    WHERE ts.facility_id = :facility_id 
                    AND ts.day_of_week = :day_of_week
                    AND ts.status = 'available'
                    HAVING available_slots > 0
                    ORDER BY ts.start_time
                ");
                
                $stmt->execute([
                    ':facility_id' => $facilityId,
                    ':day_of_week' => $dayOfWeek,
                    ':date' => $date
                ]);
                
                $slots = $stmt->fetchAll();
                
                error_log("Found " . count($slots) . " available slots for facility $facilityId on $date");
                
                echo json_encode(['success' => true, 'slots' => $slots]);
            }
            // Get user reservations
            else if (isset($_GET['action']) && $_GET['action'] === 'user_reservations') {
                $userId = getCurrentUserId();
                
                $stmt = $pdo->prepare("
                    SELECT gr.*, gf.name as facility_name, 
                    ts.start_time, ts.end_time, ts.day_of_week
                    FROM gym_reservations gr
                    JOIN gym_facilities gf ON gr.facility_id = gf.id
                    JOIN gym_time_slots ts ON gr.time_slot_id = ts.id
                    WHERE gr.user_id = :user_id
                    ORDER BY gr.reservation_date DESC, ts.start_time DESC
                ");
                
                $stmt->execute([':user_id' => $userId]);
                $reservations = $stmt->fetchAll();
                
                echo json_encode(['success' => true, 'reservations' => $reservations]);
            }
            else {
                // Default response for GET requests
                echo json_encode([
                    'success' => true, 
                    'message' => 'Gym reservation API is working',
                    'endpoints' => [
                        'GET available_slots' => '?action=available_slots&facility_id=X&date=Y',
                        'GET user_reservations' => '?action=user_reservations',
                        'POST' => 'Create new reservation',
                        'PUT' => 'Cancel reservation'
                    ]
                ]);
            }
            break;
            
        case 'POST':
            // Create a new reservation
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input) {
                throw new Exception('Invalid JSON data');
            }
            
            if (!isset($input['facility_id'], $input['time_slot_id'], $input['date'])) {
                throw new Exception('Missing required fields: facility_id, time_slot_id, date');
            }
            
            $facilityId = filter_var($input['facility_id'], FILTER_VALIDATE_INT);
            $timeSlotId = filter_var($input['time_slot_id'], FILTER_VALIDATE_INT);
            $date = filter_var($input['date'], FILTER_SANITIZE_STRING);
            $notes = isset($input['notes']) ? filter_var($input['notes'], FILTER_SANITIZE_STRING) : '';
            $activityType = isset($input['activity_type']) ? filter_var($input['activity_type'], FILTER_SANITIZE_STRING) : '';
            $participants = isset($input['participants']) ? filter_var($input['participants'], FILTER_VALIDATE_INT) : 1;
            
            // Validate inputs
            if (!$facilityId || !$timeSlotId || !$date) {
                throw new Exception('Invalid input data');
            }
            
            // Check if date is valid and not in the past
            $reservationDate = DateTime::createFromFormat('Y-m-d', $date);
            $today = new DateTime();
            $today->setTime(0, 0, 0);
            
            if (!$reservationDate) {
                throw new Exception('Invalid date format. Use YYYY-MM-DD');
            }
            
            $reservationDate->setTime(0, 0, 0);
            
            if ($reservationDate < $today) {
                throw new Exception('Cannot reserve for past dates');
            }
            
            // Check if time slot is available
            $stmt = $pdo->prepare("
                SELECT ts.max_capacity, 
                (SELECT COUNT(*) FROM gym_reservations gr 
                 WHERE gr.time_slot_id = ts.id 
                 AND gr.reservation_date = :date 
                 AND gr.status IN ('pending', 'confirmed')) as reserved_count
                FROM gym_time_slots ts
                WHERE ts.id = :time_slot_id
                AND ts.status = 'available'
            ");
            
            $stmt->execute([
                ':time_slot_id' => $timeSlotId,
                ':date' => $date
            ]);
            
            $slotInfo = $stmt->fetch();
            
            if (!$slotInfo) {
                throw new Exception('Time slot not available');
            }
            
            if ($slotInfo['reserved_count'] >= $slotInfo['max_capacity']) {
                throw new Exception('Time slot is fully booked');
            }
            
            // Check if user already has a reservation for this timeslot
            $userId = getCurrentUserId();
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as existing_reservation
                FROM gym_reservations
                WHERE user_id = :user_id
                AND time_slot_id = :time_slot_id
                AND reservation_date = :date
                AND status IN ('pending', 'confirmed')
            ");
            
            $stmt->execute([
                ':user_id' => $userId,
                ':time_slot_id' => $timeSlotId,
                ':date' => $date
            ]);
            
            $existing = $stmt->fetch();
            
            if ($existing['existing_reservation'] > 0) {
                throw new Exception('You already have a reservation for this time slot');
            }
            
            // Create the reservation
            $stmt = $pdo->prepare("
                INSERT INTO gym_reservations 
                (user_id, facility_id, time_slot_id, reservation_date, notes, status, activity_type, participants)
                VALUES (:user_id, :facility_id, :time_slot_id, :date, :notes, 'pending', :activity_type, :participants)
            ");
            
            $notesContent = $notes;
            if ($activityType) {
                $notesContent .= "\nActivity: " . $activityType;
            }
            $notesContent .= "\nParticipants: " . $participants;
            
            $stmt->execute([
                ':user_id' => $userId,
                ':facility_id' => $facilityId,
                ':time_slot_id' => $timeSlotId,
                ':date' => $date,
                ':notes' => $notesContent,
                ':activity_type' => $activityType,
                ':participants' => $participants
            ]);
            
            $reservationId = $pdo->lastInsertId();
            
            error_log("Reservation created successfully: ID $reservationId");
            
            echo json_encode([
                'success' => true, 
                'message' => 'Reservation created successfully',
                'reservation_id' => $reservationId
            ]);
            break;
            
        case 'PUT':
            // Update reservation (cancel)
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['reservation_id'])) {
                throw new Exception('Reservation ID is required');
            }
            
            $reservationId = filter_var($input['reservation_id'], FILTER_VALIDATE_INT);
            $userId = getCurrentUserId();
            
            $stmt = $pdo->prepare("
                UPDATE gym_reservations 
                SET status = 'cancelled'
                WHERE id = :reservation_id 
                AND user_id = :user_id
                AND status IN ('pending', 'confirmed')
            ");
            
            $stmt->execute([
                ':reservation_id' => $reservationId,
                ':user_id' => $userId
            ]);
            
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'Reservation cancelled successfully']);
            } else {
                throw new Exception('Reservation not found or cannot be cancelled');
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            break;
    }
    
} catch (Exception $e) {
    error_log("API error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage(),
        'error_type' => get_class($e)
    ]);
}