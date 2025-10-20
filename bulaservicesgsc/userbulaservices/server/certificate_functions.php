<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/certificate_errors.log');


// Send errors as JSON instead of blank page
set_exception_handler(function($e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
    error_log("CertificateFunctions Exception: " . $e->getMessage());
});

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => "PHP Error [$errno]: $errstr in $errfile on line $errline"
    ]);
    error_log("CertificateFunctions Error: $errstr in $errfile:$errline");
    return true;
});

// Include required files
try {
    require_once __DIR__ . '/config.php';
} catch (Exception $e) {
    error_log("Failed to include config: " . $e->getMessage());
    throw new Exception("System configuration error. Please contact administrator.");
}

/**
 * Certificate Request Functions
 */
class CertificateRequest {
    private $db;
    private $userId;
    
    public function __construct() {
        $this->db = getDBConnection();
        $this->userId = $_SESSION['user_id'] ?? null;
        
        if (!$this->userId) {
            error_log("CertificateRequest initialized without user ID in session");
        }
    }

    private function generateReferenceNumber(): string {
        return 'BC-' . date('Ymd') . '-' . mt_rand(1000, 9999);
    }

    private function calculateFee(int $copies): float {
        return $copies * 80.00;
    }

    /**
     * ✅ Fetch user info for auto-filling the form
     */
    public function getUserInfo(): array {
        if (!$this->userId) {
            return [];
        }

        try {
            $stmt = $this->db->prepare("
                SELECT 
                    CONCAT_WS(' ', first_name, middle_name, last_name, suffix) AS fullName,
                    contact_number AS contactNumber,
                    address,
                    year_started_staying AS yearOfStay
                FROM users 
                WHERE id = ?
                LIMIT 1
            ");
            $stmt->execute([$this->userId]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            error_log("getUserInfo Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * ✅ Handle file upload securely
     */
    private function handleFileUpload(array $file): ?string {
        if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
            return null;
        }

        $uploadDir = __DIR__ . '/../uploads/purok_clearance/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $allowedTypes = ['image/jpeg', 'image/png', 'application/pdf'];
        if (!in_array($file['type'], $allowedTypes, true)) {
            throw new Exception("Invalid file type. Only JPG, PNG, or PDF allowed.");
        }

        if ($file['size'] > 5 * 1024 * 1024) {
            throw new Exception("File size exceeds 5MB limit.");
        }

        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $safeName = uniqid('purok_', true) . '.' . $extension;
        $targetPath = $uploadDir . $safeName;

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new Exception("Failed to move uploaded file.");
        }

        // Return relative path for DB
        return 'uploads/purok_clearance/' . $safeName;
    }

    public function submitRequest(array $data): array {
        if (!$this->userId) {
            return ['success' => false, 'message' => 'User not authenticated'];
        }

        try {
            // Validate required fields
            $required = ['purpose', 'copies', 'document_method'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    return ['success' => false, 'message' => "Missing required field: $field"];
                }
            }

            // Handle file upload
            $documentPath = null;
            if ($data['document_method'] === 'upload' && !empty($_FILES['purok_clearance'])) {
                $documentPath = $this->handleFileUpload($_FILES['purok_clearance']);
                if (!$documentPath) {
                    return ['success' => false, 'message' => 'File upload failed.'];
                }
            }

            $referenceNumber = $this->generateReferenceNumber();
            $amount = $this->calculateFee((int)$data['copies']);
            $purposeDetails = ($data['purpose'] === 'Other') ? ($data['purpose_details'] ?? null) : null;

            // ✅ Insert into unified service_requests
            $stmt = $this->db->prepare("
                INSERT INTO service_requests 
                (user_id, service_type, purpose, purpose_details, copies, amount, 
                 document_method, document_path, reference_number, status, request_date)
                VALUES (?, 'barangay_clearance', ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
            ");
            $stmt->execute([
                $this->userId,
                $data['purpose'],
                $purposeDetails,
                $data['copies'],
                $amount,
                $data['document_method'],
                $documentPath,
                $referenceNumber
            ]);

            return [
                'success' => true,
                'reference_number' => $referenceNumber,
                'amount' => $amount,
                'message' => 'Application submitted successfully!'
            ];
        } catch (Exception $e) {
            error_log("Submit Request Error: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}

/**
 * API endpoint handler
 */
function handleCertificateRequest() {
    header('Content-Type: application/json');
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        exit;
    }
    
    try {
        ensureUserAccess();
    } catch (Exception $e) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Not authenticated']);
        exit;
    }
    
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }
    
    $certificate = new CertificateRequest();
    $data = [
        'purpose' => $_POST['purpose'] ?? '',
        'purpose_details' => $_POST['purpose_details'] ?? '',
        'copies' => intval($_POST['copies'] ?? 1),
        'document_method' => $_POST['document_method'] ?? ''
    ];
    
    $result = $certificate->submitRequest($data);
    echo json_encode($result);
    exit;
}

// Handle direct requests
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    if (isset($_GET['action']) && $_GET['action'] === 'submit_request') {
        handleCertificateRequest();
    } elseif (isset($_GET['action']) && $_GET['action'] === 'get_user_info') {
        $certificate = new CertificateRequest();
        echo json_encode([
            'success' => true,
            'data' => $certificate->getUserInfo()
        ]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    exit;
}
