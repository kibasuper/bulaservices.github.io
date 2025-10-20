<?php
require_once __DIR__ . './server/config.php';

// Ensure user is logged in
ensureUserAccess();

header('Content-Type: application/json');

try {
    $userId = getCurrentUserId();
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("
        SELECT u.full_name, u.contact_number, u.address, u.year_of_stay, u.email 
        FROM users u 
        WHERE u.id = ?
    ");
    $stmt->execute([$userId]);
    $userData = $stmt->fetch();
    
    if ($userData) {
        echo json_encode([
            'success' => true,
            'data' => $userData
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'User data not found'
        ]);
    }
} catch (Exception $e) {
    error_log("User data retrieval error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to retrieve user data'
    ]);
}