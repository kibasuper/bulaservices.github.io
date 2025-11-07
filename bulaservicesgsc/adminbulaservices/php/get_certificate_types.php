<?php
declare(strict_types=1);
require_once __DIR__ . '/../server/config.php';

// Only allow logged-in admins
if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

try {
    $db = getDBConnection();
    
    // Fetch available certificate types with pricing
    $stmt = $db->prepare("
        SELECT type_code, certificate_name, description, price 
        FROM certificate_pricing 
        WHERE active = 1 
        ORDER BY certificate_name
    ");
    $stmt->execute();
    $certificates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // If no certificates in pricing table, provide defaults
    if (empty($certificates)) {
        $certificates = [
            [
                'type_code' => 'bc',
                'certificate_name' => 'Barangay Clearance',
                'description' => 'For employment, business, and other personal requirements',
                'price' => 80.00
            ],
            [
                'type_code' => 'br',
                'certificate_name' => 'Barangay Residency',
                'description' => 'Proof of residency in the barangay',
                'price' => 50.00
            ],
            [
                'type_code' => 'bi',
                'certificate_name' => 'Barangay Indigency',
                'description' => 'For social welfare and assistance programs',
                'price' => 30.00
            ]
        ];
    }
    
    echo json_encode([
        'success' => true,
        'certificates' => $certificates
    ]);
    
} catch (Exception $e) {
    error_log("Get certificate types error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to load certificate types'
    ]);
}
?>