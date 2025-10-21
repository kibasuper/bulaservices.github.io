<?php
declare(strict_types=1);

/**
 * certificate_functions.php
 * Endpoint + service layer for certificate/service requests.
 *
 * Routes:
 *   ?action=get_user_info         GET  -> { success, data }
 *   ?action=submit_request        POST -> { success, reference_number, amount, message }
 */

// ── Error handling: quiet output, log details ──────────────────────────────────
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

$logDir = __DIR__ . '/../../logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0777, true);
}
ini_set('error_log', $logDir . '/certificate_errors.log');

set_exception_handler(function (Throwable $e) {
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json');
    }
    echo json_encode(['success' => false, 'message' => 'Server error. Please try again.']);
    error_log("[EXC] " . $e->getMessage() . " @ " . $e->getFile() . ":" . $e->getLine());
});

set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json');
    }
    echo json_encode(['success' => false, 'message' => 'Unexpected server error.']);
    error_log("[ERR] $errstr in $errfile:$errline");
    return true;
});

// ── App includes ───────────────────────────────────────────────────────────────
require_once __DIR__ . '/config.php';

// ── Helpers ───────────────────────────────────────────────────────────────────
/** Send JSON consistently */
function send_json(array $payload, int $status = 200): void {
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($payload);
}

/** Quick auth gate for routes */
function mustBeLoggedIn(): bool {
    try { ensureUserAccess(); return true; }
    catch (Throwable $e) { return false; }
}

final class CertificateRequest {
    private const ALLOWED_SERVICES = [
        'barangay_clearance',
        'business_permit',
        'indigency',
        'residency',
        'cedula',
        'ivs',
        'gym',
        'low_income',     // LIC
        'proof_income',   // PIC
        'other'
    ];

    /** map service_requests.service_type → certificate_pricing.type_code */
    private const SERVICE_TO_TYPECODE = [
        'barangay_clearance' => 'bc',
        'business_permit'    => 'bp',
        'indigency'          => 'indigency',
        'residency'          => 'residency',
        'cedula'             => 'cedula',
        'ivs'                => 'ivs',
        'low_income'         => 'lic',
        'proof_income'       => 'pic',
        'gym'                => 'gym',
        'other'              => 'other',
    ];

    /** Prefix for reference numbers */
    private const REF_PREFIX = [
        'barangay_clearance' => 'BC',
        'business_permit'    => 'BP',
        'indigency'          => 'IND',
        'residency'          => 'RES',
        'cedula'             => 'CED',
        'ivs'                => 'IVS',
        'low_income'         => 'LIC',
        'proof_income'       => 'PIC',
        'gym'                => 'GYM',
        'other'              => 'SR',
    ];

    /** Fallback prices if DB row missing */
    private const PRICE_FALLBACK = [
        'bc'        => 80.00,
        'bp'        => 150.00,
        'indigency' => 50.00,
        'residency' => 75.00,
        'cedula'    => 5.00,
        'ivs'       => 100.00,
        'lic'       => 80.00,
        'pic'       => 100.00,
        'gym'       => 0.00,
        'other'     => 0.00,
    ];

    private PDO $db;
    private ?int $userId;

    public function __construct() {
        $this->db = getDBConnection(); // assume ERRMODE_EXCEPTION in config
        $this->userId = $_SESSION['user_id'] ?? null;
    }

    /** price from certificate_pricing (with safe fallback) */
    private function getPrice(string $typeCode): float {
        try {
            $stmt = $this->db->prepare("SELECT price FROM certificate_pricing WHERE type_code = ? LIMIT 1");
            $stmt->execute([$typeCode]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && isset($row['price'])) return (float)$row['price'];
        } catch (Throwable $e) {
            error_log("[PRICE] query failed: " . $e->getMessage() . " (type_code=$typeCode)");
        }
        return self::PRICE_FALLBACK[$typeCode] ?? 0.00;
    }

    /** map service_requests.service_type → certificate_pricing.type_code */
    private function typeToCode(string $serviceType): string {
        return self::SERVICE_TO_TYPECODE[$serviceType] ?? 'other';
    }

    private function refPrefix(string $serviceType): string {
        return self::REF_PREFIX[$serviceType] ?? 'SR';
    }

    private function generateReferenceNumber(string $serviceType): string {
        // YYYYMMDD + 4 random chars (hex) for lower collision risk
        $rand = strtoupper(bin2hex(random_bytes(2))); // 4 hex chars
        return $this->refPrefix($serviceType) . '-' . date('Ymd') . '-' . $rand;
    }

    public function getUserInfo(): array {
        if (!$this->userId) return [];
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    CONCAT_WS(' ', first_name, middle_name, last_name, suffix) AS fullName,
                    contact_number AS contactNumber,
                    address,
                    year_started_staying AS yearOfStay
                FROM users WHERE id = ? LIMIT 1
            ");
            $stmt->execute([$this->userId]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            error_log("[getUserInfo] " . $e->getMessage());
            return [];
        }
    }

    private function handleFileUpload(?array $file): ?string {
        if (!$file || !isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) return null;

        // Validate size
        $size = (int)($file['size'] ?? 0);
        if ($size <= 0 || $size > 5 * 1024 * 1024) {
            throw new RuntimeException("File size exceeds 5MB limit.");
        }

        // Validate mime using finfo (stronger than $_FILES['type'])
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']) ?: 'application/octet-stream';
        $allowed = ['image/jpeg', 'image/png', 'application/pdf'];
        if (!in_array($mime, $allowed, true)) {
            throw new RuntimeException("Invalid file type. Only JPG, PNG, or PDF allowed.");
        }

        // Prepare destination
        $subdir = date('Y/m');
        $uploadDir = __DIR__ . "/../uploads/purok_clearance/$subdir/";
        if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            throw new RuntimeException("Failed to create upload directory.");
        }

        // Safe name
        $extMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'application/pdf' => 'pdf'];
        $ext = $extMap[$mime] ?? 'bin';
        $safeName = 'purok_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $target = $uploadDir . $safeName;

        if (!@move_uploaded_file($file['tmp_name'], $target)) {
            throw new RuntimeException("Failed to save uploaded file.");
        }

        // Return web-accessible relative path (no leading ../)
        return "uploads/purok_clearance/$subdir/$safeName";
    }

    public function submitRequest(array $data, array $files): array {
        if (!$this->userId) {
            return ['success' => false, 'message' => 'User not authenticated'];
        }

        // ── Normalize & validate payload ────────────────────────────────────────
        $rawService = (string)($data['service_type'] ?? '');
        $service = strtolower(trim($rawService));

        // short aliases (optional)
        $aliases = [
            'bc'  => 'barangay_clearance',
            'bp'  => 'business_permit',
            'coi' => 'indigency',
            'ind' => 'indigency',
            'ced' => 'cedula',
            'ctc' => 'cedula',
            'iv'  => 'ivs',
            'lic' => 'low_income',
            'pic' => 'proof_income',
        ];
        if (isset($aliases[$service])) $service = $aliases[$service];

        if (!in_array($service, self::ALLOWED_SERVICES, true)) {
            error_log("[SRV] Unknown service_type: raw='{$rawService}' -> '{$service}'. Forcing 'other'.");
            $service = 'other';
        }

        $purpose = trim((string)($data['purpose'] ?? ''));
        $purposeDetails = trim((string)($data['purpose_details'] ?? ''));

        $copies = max(1, (int)($data['copies'] ?? 1));
        if ($copies > 10) $copies = 10;

        $docMethod = (string)($data['document_method'] ?? '');
        // For front-ends that post 'upload' or 'hall'. Treat anything else as invalid.
        $validDoc = in_array($docMethod, ['upload', 'hall'], true);

        // Requireds
        if ($purpose === '')  return ['success' => false, 'message' => 'Missing required field: purpose'];
        if (!$validDoc)       return ['success' => false, 'message' => 'Please choose a submission method (upload or hall).'];
        if ($purpose === 'Other' && $purposeDetails === '') {
            return ['success' => false, 'message' => 'Please specify your purpose.'];
        }

        // Optional upload (only when method=upload)
        $documentPath = null;
        try {
            if ($docMethod === 'upload') {
                $documentPath = $this->handleFileUpload($files['purok_clearance'] ?? null);
            }
        } catch (Throwable $e) {
            error_log("[UPLOAD] " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }

        // ── Pricing & reference ────────────────────────────────────────────────
        $typeCode = $this->typeToCode($service);
        $price    = $this->getPrice($typeCode);
        if ($price <= 0 && $typeCode !== 'other') {
            error_log("[SRV] Zero/invalid price for type_code='{$typeCode}'. Check certificate_pricing.");
        }
        $amount = $copies * $price;
        $ref    = $this->generateReferenceNumber($service);

        // ── Optional extra_data (Business Permit only) ─────────────────────────
        $extra = null;
        if ($service === 'business_permit') {
            $bn = trim((string)($data['business_name'] ?? ''));
            $bt = trim((string)($data['business_type'] ?? ''));
            $ba = trim((string)($data['business_address'] ?? ''));
            if ($bn !== '' || $bt !== '' || $ba !== '') {
                $extra = ['business_name' => $bn, 'business_type' => $bt, 'business_address' => $ba];
            }
        }
        // NOTE: For low_income/proof_income we DO NOT store income details.
        //       (No monthly income/source columns in service_requests; keep it clean.)

        // ── Insert ─────────────────────────────────────────────────────────────
        try {
            $sql = "
                INSERT INTO service_requests
                (user_id, reference_number, service_type, purpose, purpose_details, copies, amount,
                 document_method, document_path, status, request_date, extra_data)
                VALUES (:uid, :ref, :stype, :purpose, :pdetail, :copies, :amount,
                        :dmethod, :dpath, 'pending', NOW(), :extra)
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':uid', $this->userId, PDO::PARAM_INT);
            $stmt->bindValue(':ref', $ref, PDO::PARAM_STR);
            $stmt->bindValue(':stype', $service, PDO::PARAM_STR);
            $stmt->bindValue(':purpose', $purpose, PDO::PARAM_STR);
            if ($purpose === 'Other' && $purposeDetails !== '') {
                $stmt->bindValue(':pdetail', $purposeDetails, PDO::PARAM_STR);
            } else {
                $stmt->bindValue(':pdetail', null, PDO::PARAM_NULL);
            }
            $stmt->bindValue(':copies', $copies, PDO::PARAM_INT);
            $stmt->bindValue(':amount', $amount);
            $stmt->bindValue(':dmethod', $docMethod, PDO::PARAM_STR);
            if ($documentPath) {
                $stmt->bindValue(':dpath', $documentPath, PDO::PARAM_STR);
            } else {
                $stmt->bindValue(':dpath', null, PDO::PARAM_NULL);
            }
            if ($extra !== null) {
                $stmt->bindValue(':extra', json_encode($extra, JSON_UNESCAPED_UNICODE), PDO::PARAM_STR);
            } else {
                $stmt->bindValue(':extra', null, PDO::PARAM_NULL);
            }
            $stmt->execute();
        } catch (Throwable $e) {
            error_log("[DB][insert service_requests] " . $e->getMessage()
                . " | service=$service copies=$copies amount=$amount ref=$ref doc=$docMethod");
            return ['success' => false, 'message' => 'Could not save your request. Please try again.'];
        }

        return [
            'success' => true,
            'reference_number' => $ref,
            'amount' => $amount,
            'message' => 'Application submitted successfully!'
        ];
    }
}

// ── Router ─────────────────────────────────────────────────────────────────────
if (isset($_GET['action'])) {
    $action = $_GET['action'];

    if ($action === 'get_user_info') {
        if (!mustBeLoggedIn()) {
            send_json(['success' => false, 'message' => 'Not authenticated'], 401);
            exit;
        }
        $svc = new CertificateRequest();
        send_json(['success' => true, 'data' => $svc->getUserInfo()]);
        exit;
    }

    if ($action === 'submit_request') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            send_json(['success' => false, 'message' => 'Method not allowed'], 405);
            exit;
        }
        if (!mustBeLoggedIn()) {
            send_json(['success' => false, 'message' => 'Not authenticated'], 401);
            exit;
        }
        if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
            send_json(['success' => false, 'message' => 'Invalid CSRF token'], 403);
            exit;
        }

        $svc = new CertificateRequest();
        try {
            $result = $svc->submitRequest($_POST, $_FILES);
            send_json($result, $result['success'] ? 200 : 400);
        } catch (Throwable $e) {
            error_log("[ROUTE submit_request] " . $e->getMessage());
            send_json(['success' => false, 'message' => 'Unexpected error while saving request.'], 500);
        }
        exit;
    }

    send_json(['success' => false, 'message' => 'Invalid action'], 400);
    exit;
}

// included without action → no output
