<?php
// gym_functions.php
require_once __DIR__ . '/db.php'; // loads $pdo
// session used to get user id if set
if (session_status() === PHP_SESSION_NONE) session_start();

/**
 * Get available slots for a date.
 * Returns ['status'=>'success','slots'=>[ {start_time,end_time,max_capacity,available}, ... ]]
 */
function getAvailableSlotsForDate(string $date): array {
    global $pdo;

    // 1) Check blocked dates table (optional)
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM gym_blocked_dates WHERE date = ?");
        $stmt->execute([$date]);
        if ((int)$stmt->fetchColumn() > 0) {
            return ['status' => 'error', 'message' => 'This date is blocked for reservations'];
        }
    } catch (Exception $e) {
        // ignore if table doesn't exist; log
        error_log("Blocked dates check error: " . $e->getMessage());
    }

    // 2) Determine facility open/close and capacity using gym_facilities (single-row expected)
    $openTime = '06:00:00';
    $closeTime = '24:00:00';
    $defaultCapacity = 20;

    try {
        $stmt = $pdo->query("SELECT start_time, end_time, max_capacity FROM gym_facilities LIMIT 1");
        $facility = $stmt->fetch();
        if ($facility) {
            $openTime = $facility['start_time'] ?? $openTime;
            $closeTime = $facility['end_time'] ?? $closeTime;
            $defaultCapacity = $facility['max_capacity'] ?? $defaultCapacity;
        }
    } catch (Exception $e) {
        // If gym_facilities doesn't exist, use defaults
        error_log("gym_facilities read error: " . $e->getMessage());
    }

    // 3) Build hourly slots between open and close
    $slots = [];
    $startEpoch = strtotime("2000-01-01 $openTime");
    $endEpoch = strtotime("2000-01-01 $closeTime");
    if ($endEpoch <= $startEpoch) {
        // If close is '24:00:00' or similar, set to midnight + 24
        $endEpoch = strtotime("2000-01-02 $closeTime");
    }
    for ($t = $startEpoch; $t < $endEpoch; $t += 3600) {
        $s = date('H:i:s', $t);
        $e = date('H:i:s', $t + 3600);
        $slots[] = [
            'start_time'   => $s,
            'end_time'     => $e,
            'max_capacity' => (int)$defaultCapacity,
            'available'    => true // default — we'll flip if booked
        ];
    }

    // 4) Fetch booked slots for the date (pending or approved)
    $stmt = $pdo->prepare("SELECT start_time, end_time FROM gym_reservations WHERE reservation_date = ? AND status IN ('pending','approved')");
    $stmt->execute([$date]);
    $booked = $stmt->fetchAll();

    // Mark unavailable slots
    foreach ($slots as &$slot) {
        foreach ($booked as $b) {
            if ($b['start_time'] === $slot['start_time'] && $b['end_time'] === $slot['end_time']) {
                $slot['available'] = false;
                break;
            }
        }
    }
    unset($slot);

    return ['status' => 'success', 'slots' => $slots];
}

/**
 * Create reservation (with conflict checks)
 * Input $data keys:
 *  - date (YYYY-MM-DD)
 *  - slots: array of {start_time, end_time}
 *  - resident_name, contact_number, activity_type, participant_count, notes (optional)
 */
function createReservation(array $data): array {
    global $pdo;
    if (!isset($data['date'], $data['slots']) || !is_array($data['slots']) || count($data['slots']) === 0) {
        return ['status' => 'error', 'message' => 'Invalid input: date and slots are required'];
    }

    $date = $data['date'];
    $slots = $data['slots'];

    // Validate other required fields
    $required = ['resident_name', 'contact_number', 'activity_type', 'participant_count'];
    foreach ($required as $f) {
        if (empty($data[$f]) && $data[$f] !== '0') {
            return ['status' => 'error', 'message' => "Missing required field: $f"];
        }
    }

    try {
        $pdo->beginTransaction();

        // Check each slot for conflicts
        $conflictStmt = $pdo->prepare("SELECT COUNT(*) FROM gym_reservations WHERE reservation_date = ? AND start_time = ? AND end_time = ? AND status IN ('pending','approved')");
        foreach ($slots as $s) {
            if (!isset($s['start_time'], $s['end_time'])) {
                $pdo->rollBack();
                return ['status' => 'error', 'message' => 'Invalid slot format'];
            }
            $conflictStmt->execute([$date, $s['start_time'], $s['end_time']]);
            if ((int)$conflictStmt->fetchColumn() > 0) {
                $pdo->rollBack();
                return ['status' => 'error', 'message' => "Time slot {$s['start_time']} - {$s['end_time']} is already booked"];
            }
        }

        // Generate unique reference number
        $reference = '';
        do {
            $reference = 'GYM-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM gym_reservations WHERE reference_number = ?");
            $stmt->execute([$reference]);
            $exists = (int)$stmt->fetchColumn();
        } while ($exists > 0);

        // Total amount (₱200 per hour)
        $totalAmount = count($slots) * 200;

        // Insert each slot as one row
        $insert = $pdo->prepare("INSERT INTO gym_reservations
            (reference_number, user_id, reservation_date, start_time, end_time, resident_name, contact_number, activity_type, participant_count, notes, total_amount, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");

        $userId = $_SESSION['user_id'] ?? 0;

        foreach ($slots as $s) {
            $insert->execute([
                $reference,
                $userId,
                $date,
                $s['start_time'],
                $s['end_time'],
                $data['resident_name'],
                $data['contact_number'],
                $data['activity_type'],
                (int)$data['participant_count'],
                $data['notes'] ?? '',
                $totalAmount
            ]);
        }

        $pdo->commit();
        return ['status' => 'success', 'reference_number' => $reference, 'total_amount' => $totalAmount];
    } catch (Exception $e) {
        error_log("Create reservation error: " . $e->getMessage());
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['status' => 'error', 'message' => 'Internal server error'];
    }
}
