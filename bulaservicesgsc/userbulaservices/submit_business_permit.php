<?php
require_once __DIR__ . '/../server/config.php';

// Ensure user is logged in
ensureUserAccess();

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

header('Content-Type: application/json');

try {
    // Get current user ID
    $userId = getCurrentUserId();
    
    // Validate and sanitize input data
    $requiredFields = [
        'business_name', 'business_type', 'business_address', 
        'purpose', 'copy_quantity', 'clearance_method'
    ];
    
    foreach ($requiredFields as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }
    
    // Get user data
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT full_name, contact_number, address, year_of_stay FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $userData = $stmt->fetch();
    
    if (!$userData) {
        throw new Exception('User data not found');
    }
    
    // Generate reference number
    $referenceNumber = generateBusinessPermitReference();
    
    // Calculate total fee
    $copyQuantity = intval($_POST['copy_quantity']);
    $totalFee = $copyQuantity * 80.00;
    
    // Handle file upload if clearance method is upload
    $clearanceFilename = null;
    if ($_POST['clearance_method'] === 'upload' && isset($_FILES['purok_clearance'])) {
        $clearanceFilename = uploadBusinessPermitFile($_FILES['purok_clearance']);
    }
    
    // Insert into database
    $stmt = $pdo->prepare("
        INSERT INTO business_permits (
            reference_number, user_id, full_name, contact_number, address, 
            year_of_stay, business_name, business_type, business_address, 
            purpose, other_purpose, copy_quantity, total_fee, 
            clearance_method, clearance_filename
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $referenceNumber,
        $userId,
        $userData['full_name'],
        $userData['contact_number'],
        $userData['address'],
        $userData['year_of_stay'],
        sanitizeInput($_POST['business_name']),
        sanitizeInput($_POST['business_type']),
        sanitizeInput($_POST['business_address']),
        sanitizeInput($_POST['purpose']),
        !empty($_POST['other_purpose']) ? sanitizeInput($_POST['other_purpose']) : null,
        $copyQuantity,
        $totalFee,
        $_POST['clearance_method'],
        $clearanceFilename
    ]);
    
    // Send success response
    echo json_encode([
        'success' => true,
        'reference_number' => $referenceNumber,
        'message' => 'Business permit application submitted successfully'
    ]);
    
} catch (Exception $e) {
    error_log("Business permit submission error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to submit application: ' . $e->getMessage()
    ]);
}

/**
 * Generate unique reference number for business permit
 */
function generateBusinessPermitReference(): string {
    $date = date('Ymd');
    $random = str_pad(mt_rand(0, 999), 3, '0', STR_PAD_LEFT);
    return "BP-{$date}-{$random}";
}

/**
 * Handle file upload for business permit documents
 */
function uploadBusinessPermitFile(array $file): string {
    // Define upload directory
    $uploadDir = __DIR__ . '/../../uploads/business_permits/';
    
    // Create directory if it doesn't exist
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Validate file
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('File upload error: ' . $file['error']);
    }
    
    // Check file size (max 5MB)
    $maxSize = 5 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        throw new RuntimeException('File size exceeds 5MB limit');
    }
    
    // Check file type
    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'application/pdf' => 'pdf'
    ];
    
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    
    if (!array_key_exists($mime, $allowedTypes)) {
        throw new RuntimeException('Invalid file type. Allowed: JPG, PNG, PDF');
    }
    
    // Generate unique filename
    $extension = $allowedTypes[$mime];
    $filename = uniqid('bp_', true) . '.' . $extension;
    $destination = $uploadDir . $filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException('Failed to move uploaded file');
    }
    
    return 'business_permits/' . $filename;
}