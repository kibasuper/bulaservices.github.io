<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/business_permit_errors.log');

// Include required files with proper path handling
try {
    require_once __DIR__ . '/../../server/db_connection.php';
    require_once __DIR__ . '/../../server/auth_functions.php';
} catch (Exception $e) {
    error_log("Failed to include required files: " . $e->getMessage());
    throw new Exception("System configuration error. Please contact administrator.");
}

/**
 * Business Permit Request Functions
 */
class BusinessPermitRequest {
    private $db;
    private $userId;
    
    public function __construct() {
        $this->db = getDBConnection();
        $this->userId = $_SESSION['user_id'] ?? null;
        
        if (!$this->userId) {
            error_log("BusinessPermitRequest initialized without user ID in session");
        }
    }
    
    /**
     * Get user information for pre-filling the form
     */
    public function getUserInfo(): array {
        if (!$this->userId) {
            error_log("getUserInfo() called without user ID");
            return [];
        }
        
        try {
            $stmt = $this->db->prepare("
                SELECT u.id, u.first_name, u.last_name, u.middle_name, u.contact_number, 
                       u.address, u.purok, u.year_started_residing, u.email
                FROM users u 
                WHERE u.id = ?
            ");
            $stmt->execute([$this->userId]);
            $user = $stmt->fetch();
            
            if ($user) {
                return [
                    'fullName' => trim($user['first_name'] . ' ' . 
                                     ($user['middle_name'] ? $user['middle_name'] . ' ' : '') . 
                                     $user['last_name']),
                    'contactNumber' => $user['contact_number'] ?? '',
                    'address' => ($user['address'] ?? '') . 
                                ($user['purok'] ? ', Purok ' . $user['purok'] : ''),
                    'yearOfStay' => $user['year_started_residing'] ?? '',
                    'email' => $user['email'] ?? ''
                ];
            }
            
            error_log("User not found in database for ID: " . $this->userId);
            return [];
            
        } catch (PDOException $e) {
            error_log("Get User Info Error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Generate unique reference number for business permit
     */
    private function generateReferenceNumber(): string {
        $prefix = 'BP';
        $date = date('Ymd');
        $random = mt_rand(1000, 9999);
        return $prefix . '-' . $date . '-' . $random;
    }
    
    /**
     * Submit business permit request
     */
    public function submitRequest(array $data): array {
        if (!$this->userId) {
            error_log("submitRequest() called without user ID");
            return ['success' => false, 'message' => 'User not authenticated'];
        }
        
        try {
            // Validate required fields
            $required = ['business_name', 'business_type', 'business_address', 'purpose', 'copies', 'clearance_method'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    error_log("Missing required field: $field");
                    return ['success' => false, 'message' => "Missing required field: $field"];
                }
            }
            
            // Handle file upload if applicable
            $clearancePath = null;
            if ($data['clearance_method'] === 'upload' && !empty($_FILES['purok_clearance'])) {
                $clearancePath = $this->handleFileUpload($_FILES['purok_clearance']);
                if (!$clearancePath) {
                    error_log("File upload failed for user: " . $this->userId);
                    return ['success' => false, 'message' => 'File upload failed. Please try again or choose another method.'];
                }
            }
            
            // Generate reference number
            $referenceNumber = $this->generateReferenceNumber();
            
            // Calculate amount
            $amount = $this->calculateFee($data['copies']);
            
            // Insert request
            $stmt = $this->db->prepare("
                INSERT INTO business_permit_requests 
                (user_id, reference_number, business_name, business_type, business_address, 
                 purpose, purpose_details, copies, amount, clearance_method, clearance_path)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $success = $stmt->execute([
                $this->userId,
                $referenceNumber,
                sanitizeInput($data['business_name']),
                sanitizeInput($data['business_type']),
                sanitizeInput($data['business_address']),
                sanitizeInput($data['purpose']),
                !empty($data['purpose_details']) ? sanitizeInput($data['purpose_details']) : null,
                $data['copies'],
                $amount,
                $data['clearance_method'],
                $clearancePath
            ]);
            
            if (!$success) {
                error_log("Failed to insert business permit request for user: " . $this->userId);
                return ['success' => false, 'message' => 'Failed to submit request. Please try again.'];
            }
            
            // Log successful submission
            error_log("Business permit request submitted successfully for user: " . $this->userId . 
                     ", Reference: " . $referenceNumber);
            
            return [
                'success' => true,
                'reference_number' => $referenceNumber,
                'amount' => $amount,
                'message' => 'Business permit application submitted successfully!'
            ];
            
        } catch (PDOException $e) {
            error_log("Submit Request Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error. Please try again later.'];
        }
    }
    
    /**
     * Handle file upload for purok clearance
     */
    private function handleFileUpload(array $file): ?string {
        $uploadDir = __DIR__ . '/../../uploads/business_permits/';
        $maxSize = 5 * 1024 * 1024; // 5MB
        $allowedTypes = ['image/jpeg', 'image/png', 'application/pdf'];
        
        // Create directory if it doesn't exist
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                error_log("Failed to create upload directory: " . $uploadDir);
                return null;
            }
        }
        
        // Validate file
        if ($file['error'] !== UPLOAD_ERR_OK) {
            error_log("File upload error: " . $file['error']);
            return null;
        }
        
        if ($file['size'] > $maxSize) {
            error_log("File too large: " . $file['size'] . " bytes");
            return null;
        }
        
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        
        if (!in_array($mime, $allowedTypes)) {
            error_log("Invalid file type: " . $mime);
            return null;
        }
        
        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid('business_permit_', true) . '.' . $extension;
        $destination = $uploadDir . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $destination)) {
            return 'business_permits/' . $filename;
        }
        
        error_log("Failed to move uploaded file to: " . $destination);
        return null;
    }
    
    /**
     * Calculate fee based on number of copies
     */
    private function calculateFee(int $copies): float {
        $pricePerCopy = 80.00;
        return $copies * $pricePerCopy;
    }
    
    /**
     * Get user's business permit requests
     */
    public function getUserRequests(): array {
        if (!$this->userId) {
            return [];
        }
        
        try {
            $stmt = $this->db->prepare("
                SELECT id, reference_number, business_name, status, request_date, 
                       copies, amount, clearance_method
                FROM business_permit_requests 
                WHERE user_id = ? 
                ORDER BY request_date DESC
            ");
            $stmt->execute([$this->userId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Get User Requests Error: " . $e->getMessage());
            return [];
        }
    }
}

/**
 * API endpoint handler for business permits
 */
function handleBusinessPermitRequest() {
    // Set JSON header
    header('Content-Type: application/json');
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        exit;
    }
    
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Not authenticated']);
        exit;
    }
    
    try {
        $businessPermit = new BusinessPermitRequest();
        $data = [
            'business_name' => $_POST['business_name'] ?? '',
            'business_type' => $_POST['business_type'] ?? '',
            'business_address' => $_POST['business_address'] ?? '',
            'purpose' => $_POST['purpose'] ?? '',
            'purpose_details' => $_POST['purpose_details'] ?? '',
            'copies' => intval($_POST['copies'] ?? 1),
            'clearance_method' => $_POST['clearance_method'] ?? ''
        ];
        
        $result = $businessPermit->submitRequest($data);
        echo json_encode($result);
        
    } catch (Exception $e) {
        error_log("Business permit request handler error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Internal server error']);
    }
    
    exit;
}

// Handle AJAX requests
if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'submit_request':
            handleBusinessPermitRequest();
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            exit;
    }
}