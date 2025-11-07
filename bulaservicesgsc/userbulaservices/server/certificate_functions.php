<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

$logDir = __DIR__ . '/../../logs';
if (!is_dir($logDir)) { @mkdir($logDir, 0777, true); }
ini_set('error_log', $logDir . '/certificate_errors.log');

set_exception_handler(function (Throwable $e) {
  if (!headers_sent()) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
  }
  echo json_encode(['success' => false, 'message' => 'Server error. Please try again.']);
  error_log("[EXC] {$e->getMessage()} @ {$e->getFile()}:{$e->getLine()}");
});

set_error_handler(function ($errno, $errstr, $errfile, $errline) {
  if (!headers_sent()) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
  }
  echo json_encode(['success' => false, 'message' => 'Unexpected server error.']);
  error_log("[ERR] $errstr in $errfile:$errline");
  return true;
});

require_once __DIR__ . '/config.php';

function send_json(array $payload, int $status = 200): void {
  if (!headers_sent()) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
  }
  echo json_encode($payload);
}

function mustBeLoggedIn(): bool {
  try { ensureUserAccess(); return true; }
  catch (Throwable $e) { return false; }
}

function db_get_user_type(PDO $db, int $userId): ?string {
  try {
    $stmt = $db->prepare("SELECT user_type FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;
    $t = strtolower((string)$row['user_type']);
    return in_array($t, ['resident','outsider'], true) ? $t : null;
  } catch (Throwable $e) {
    error_log('[user_type] ' . $e->getMessage());
    return null;
  }
}

final class CertificateRequest {
  private const ALLOWED_SERVICES = [
    'barangay_clearance','business_permit','indigency','residency','cedula','ivs','gym','low_income','proof_income',
  ];

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
  ];

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
  ];

  public const PRICE_FALLBACK = [
    'bc'=>80.00,'bp'=>150.00,'indigency'=>50.00,'residency'=>75.00,'cedula'=>5.00,'ivs'=>100.00,'lic'=>80.00,'pic'=>100.00,'gym'=>0.00,
  ];

  private PDO $db;
  private ?int $userId;

  public function __construct() {
    $this->db = getDBConnection();
    if ($this->db instanceof PDO) $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $this->userId = $_SESSION['user_id'] ?? null;
  }

  private function normalizeService(string $raw): string {
    $val = strtolower(trim($raw));
    $key = preg_replace('/[\s_]+/', '', $val);
    $map = [
      'bc'=>'barangay_clearance','barangayclearance'=>'barangay_clearance','barangay_clearance'=>'barangay_clearance','barangay clearance'=>'barangay_clearance',
      'bp'=>'business_permit','businesspermit'=>'business_permit','business_permit'=>'business_permit','business permit'=>'business_permit',
      'indigency'=>'indigency','certificateofindigency'=>'indigency',
      'residency'=>'residency','certificateofresidency'=>'residency',
      'cedula'=>'cedula','ctc'=>'cedula','communitytaxcertificate'=>'cedula',
      'ivs'=>'ivs',
      'lowincome'=>'low_income','low_income'=>'low_income','lic'=>'low_income',
      'proofincome'=>'proof_income','proof_income'=>'proof_income','pic'=>'proof_income',
      'gym'=>'gym','gymreservation'=>'gym',
    ];
    if (isset($map[$key])) return $map[$key];
    if (in_array($val, self::ALLOWED_SERVICES, true)) return $val;
    throw new InvalidArgumentException("Unsupported service type '{$raw}'.");
  }

  private function getPrice(string $typeCode): float {
    try {
      $stmt = $this->db->prepare("SELECT price FROM certificate_pricing WHERE type_code = ? LIMIT 1");
      $stmt->execute([$typeCode]);
      $row = $stmt->fetch(PDO::FETCH_ASSOC);
      if ($row && isset($row['price'])) return (float)$row['price'];
    } catch (Throwable $e) {
      error_log("[PRICE] query failed: {$e->getMessage()} (type_code=$typeCode)");
    }
    return self::PRICE_FALLBACK[$typeCode] ?? 0.00;
  }

  private function typeToCode(string $serviceType): string {
    if (!isset(self::SERVICE_TO_TYPECODE[$serviceType])) {
      throw new InvalidArgumentException("No price code for service '{$serviceType}'.");
    }
    return self::SERVICE_TO_TYPECODE[$serviceType];
  }

  private function refPrefix(string $serviceType): string {
    return self::REF_PREFIX[$serviceType] ?? 'SR';
  }

  private function generateReferenceNumber(string $serviceType): string {
    $rand = strtoupper(bin2hex(random_bytes(2)));
    return $this->refPrefix($serviceType) . '-' . date('Ymd') . '-' . $rand;
  }

  public function getUserInfo(): array {
    if (!$this->userId) return [];
    try {
      $stmt = $this->db->prepare("
        SELECT 
          user_type,
          CONCAT_WS(' ', first_name, middle_name, last_name, suffix) AS fullName,
          contact_number AS contactNumber,
          address,
          year_started_staying AS yearOfStay
        FROM users WHERE id = ? LIMIT 1
      ");
      $stmt->execute([$this->userId]);
      $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
      if (isset($row['user_type'])) $row['user_type'] = strtolower((string)$row['user_type']);
      return $row;
    } catch (Throwable $e) {
      error_log("[getUserInfo] " . $e->getMessage());
      return [];
    }
  }

  // helper to extract a single file from a nested $_FILES['requirements'] array
  private function pickFile(array $files, string $key): ?array {
    if (!isset($files['name'][$key])) return null;
    return [
      'name'     => $files['name'][$key]     ?? null,
      'type'     => $files['type'][$key]     ?? null,
      'tmp_name' => $files['tmp_name'][$key] ?? null,
      'error'    => $files['error'][$key]    ?? UPLOAD_ERR_NO_FILE,
      'size'     => $files['size'][$key]     ?? 0,
    ];
  }

  private function handleRequirementUpload(?array $file, string $serviceType, string $reqKey): ?array {
    if (!$file || !isset($file['tmp_name'])) return null;
    if ($file['error'] === UPLOAD_ERR_NO_FILE) return null;
    if ($file['error'] !== UPLOAD_ERR_OK) throw new RuntimeException("Upload failed for $reqKey (code {$file['error']}).");

    $size = (int)($file['size'] ?? 0);
    if ($size <= 0 || $size > 5 * 1024 * 1024) throw new RuntimeException("$reqKey exceeds 5MB limit.");

    $mime = 'application/octet-stream';
    if (class_exists('finfo')) {
      $finfo = new finfo(FILEINFO_MIME_TYPE);
      $mime  = $finfo->file($file['tmp_name']) ?: ($file['type'] ?? 'application/octet-stream');
    }
    $allowed = ['image/jpeg','image/png','application/pdf'];
    if (!in_array($mime, $allowed, true)) throw new RuntimeException("$reqKey must be JPG, PNG, or PDF.");

    $subdir = date('Y/m');
    $uploadDir = __DIR__ . "/../uploads/requirements/{$serviceType}/{$reqKey}/{$subdir}/";
    if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
      throw new RuntimeException("Failed to create upload directory for $reqKey.");
    }

    $extMap = ['image/jpeg'=>'jpg','image/png'=>'png','application/pdf'=>'pdf'];
    $ext = $extMap[$mime] ?? 'bin';
    $safeName = $reqKey . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $target = $uploadDir . $safeName;

    if (!@move_uploaded_file($file['tmp_name'], $target)) {
      throw new RuntimeException("Failed to save uploaded file for $reqKey.");
    }

    $publicPath = "uploads/requirements/{$serviceType}/{$reqKey}/{$subdir}/{$safeName}";
    return ['path' => $publicPath, 'mime' => $mime, 'size' => $size, 'original' => (string)($file['name'] ?? '')];
  }

  public function submitRequest(array $data, array $files): array {
    if (!$this->userId) return ['success' => false, 'message' => 'User not authenticated'];

    // Normalize service
    $rawService = (string)($data['service_type'] ?? '');
    try { $service = $this->normalizeService($rawService); }
    catch (InvalidArgumentException $e) { return ['success' => false, 'message' => $e->getMessage()]; }

    // outsiders may submit ONLY 'gym'
    $uType = db_get_user_type($this->db, (int)$this->userId) ?? '';
    if ($uType === 'outsider' && $service !== 'gym') {
      return ['success' => false, 'message' => 'Only Gym Reservation is allowed for outsider accounts.'];
    }

    $purpose = trim((string)($data['purpose'] ?? ''));
    $purposeDetails = trim((string)($data['purpose_details'] ?? ''));
    $copies = max(1, (int)($data['copies'] ?? 1)); if ($copies > 10) $copies = 10;

    if ($purpose === '') return ['success' => false, 'message' => 'Missing required field: purpose'];
    if ($purpose === 'Other' && $purposeDetails === '') return ['success' => false, 'message' => 'Please specify your purpose.'];

    // === NEW: per-requirement methods + uploads ===
    // Frontend sends: req_method[key] = upload|hall, files in requirements[key]
    $reqMethods = is_array($data['req_method'] ?? null) ? $data['req_method'] : [];
    $reqFiles   = is_array($files['requirements'] ?? null) ? $files['requirements'] : [];

    // Define required keys for the selected service (extensible later)
    $requiredKeys = [];
    if ($service === 'barangay_clearance') {
      $requiredKeys = ['purok_clearance', 'valid_id', 'cedula'];
    }
        //Business Permit required docs
    if ($service === 'business_permit') {
      $requiredKeys = ['purok_clearance', 'valid_id', 'business_docs'];
    }
    if ($service === 'cedula') {
      $requiredKeys = ['purok_clearance', 'valid_id'];
    }
    if ($service === 'ivs') {
    $requiredKeys = ['purok_clearance', 'valid_id'];
    }
    if ($service === 'indigency') {
    $requiredKeys = ['purok_clearance','valid_id','proof_of_residence','cedula'];
    }
    if ($service === 'residency') {
    $requiredKeys = ['purok_clearance','valid_id','proof_of_residence','cedula'];
    }
    if ($service === 'low_income') {
    $requiredKeys = ['purok_clearance','valid_id','proof_of_income'];
    }

    // Validate presence of a method per requirement
    foreach ($requiredKeys as $k) {
      $m = strtolower((string)($reqMethods[$k] ?? ''));
      if (!in_array($m, ['upload','hall'], true)) {
        return ['success' => false, 'message' => "Please choose a method for $k."];
      }
      if ($m === 'upload') {
        $one = $this->pickFile($reqFiles, $k);
        if (!$one || ($one['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
          return ['success' => false, 'message' => "Please upload a file for $k."];
        }
      }
    }

    // Handle uploads and assemble bundle
    $requirementsBundle = [];
    $legacyMethod = 'none';
    $legacyPath   = null;

    foreach ($requiredKeys as $k) {
      $method = strtolower((string)($reqMethods[$k] ?? 'hall'));
      if ($method === 'upload') {
        $one = $this->pickFile($reqFiles, $k);
        try {
          $meta = $this->handleRequirementUpload($one, $service, $k);
          if ($meta !== null) {
            $requirementsBundle[$k] = array_merge(['method' => 'upload'], $meta);
            // legacy bridge using purok_clearance
            if ($k === 'purok_clearance') { $legacyMethod = 'upload'; $legacyPath = $meta['path'] ?? null; }
          } else {
            $requirementsBundle[$k] = ['method' => 'upload']; // no path (shouldn’t happen)
          }
        } catch (Throwable $e) {
          error_log("[UPLOAD:$k] " . $e->getMessage());
          return ['success' => false, 'message' => $e->getMessage()];
        }
      } else {
        $requirementsBundle[$k] = ['method' => 'hall'];
        if ($k === 'purok_clearance' && $legacyMethod === 'none') $legacyMethod = 'hall';
      }
    }

    // Determine price & reference
    try { $typeCode = $this->typeToCode($service); }
    catch (InvalidArgumentException $e) { return ['success' => false, 'message' => $e->getMessage()]; }

    $price  = $this->getPrice($typeCode);
    if ($price <= 0) return ['success' => false, 'message' => 'Pricing for this service is not configured. Please contact the administrator.'];
    $amount = $copies * $price;
    $ref    = $this->generateReferenceNumber($service);

    // Optional extra_data service-specific block
    $extra = [
      'requirements' => $requirementsBundle,
    ];
    if ($service === 'business_permit') {
      $bn = trim((string)($data['business_name'] ?? ''));
      $bt = trim((string)($data['business_type'] ?? ''));
      $ba = trim((string)($data['business_address'] ?? ''));
      if ($bn !== '' || $bt !== '' || $ba !== '') {
        $extra['business'] = ['business_name' => $bn, 'business_type' => $bt, 'business_address' => $ba];
      }
    }

    // Legacy columns for compatibility
    $documentMethod = in_array($legacyMethod, ['upload','hall'], true) ? $legacyMethod : 'none';
    $documentPath   = ($documentMethod === 'upload' && $legacyPath) ? $legacyPath : null;

    // Insert
    try {
      $sql = "
        INSERT INTO service_requests
          (user_id, reference_number, service_type, purpose, purpose_details, copies, amount,
           document_method, document_path, status, request_date, extra_data)
        VALUES
          (:uid, :ref, :stype, :purpose, :pdetail, :copies, :amount,
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
      $stmt->bindValue(':dmethod', $documentMethod, PDO::PARAM_STR);
      if ($documentPath) $stmt->bindValue(':dpath', $documentPath, PDO::PARAM_STR);
      else               $stmt->bindValue(':dpath', null, PDO::PARAM_NULL);
      $stmt->bindValue(':extra', json_encode($extra, JSON_UNESCAPED_UNICODE), PDO::PARAM_STR);
      $stmt->execute();
    } catch (Throwable $e) {
      error_log("[DB][insert] {$e->getMessage()} | service=$service ref=$ref amount=$amount");
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
    if (!mustBeLoggedIn()) { send_json(['success' => false, 'message' => 'Not authenticated'], 401); exit; }
    $svc = new CertificateRequest();
    send_json(['success' => true, 'data' => $svc->getUserInfo()]);
    exit;
  }

  if ($action === 'get_price') {
    if (!mustBeLoggedIn()) { send_json(['success' => false, 'message' => 'Not authenticated'], 401); exit; }
    $type = strtolower(trim((string)($_GET['type'] ?? '')));
    $aliases = [
      'bc' => 'bc','bp'=>'bp','ivs'=>'ivs','cedula'=>'cedula','indigency'=>'indigency','residency'=>'residency','lic'=>'lic','pic'=>'pic','gym'=>'gym',
      'barangay_clearance'=>'bc','business_permit'=>'bp','low_income'=>'lic','proof_income'=>'pic',
    ];
    $typeCode = $aliases[$type] ?? $type;
    try {
      $db = getDBConnection(); $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      $stmt = $db->prepare("SELECT price FROM certificate_pricing WHERE type_code = ? LIMIT 1");
      $stmt->execute([$typeCode]);
      $row = $stmt->fetch(PDO::FETCH_ASSOC);
      $price = $row ? (float)$row['price'] : (CertificateRequest::PRICE_FALLBACK[$typeCode] ?? 0.0);
      if ($price <= 0) { send_json(['success' => false, 'message' => 'Price not configured for type_code='.$typeCode], 404); exit; }
      send_json(['success' => true, 'type_code' => $typeCode, 'price' => $price]);
    } catch (Throwable $e) {
      error_log('[get_price] ' . $e->getMessage());
      send_json(['success' => false, 'message' => 'Could not fetch price'], 500);
    }
    exit;
  }

  if ($action === 'submit_request') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { send_json(['success' => false, 'message' => 'Method not allowed'], 405); exit; }
    if (!mustBeLoggedIn()) { send_json(['success' => false, 'message' => 'Not authenticated'], 401); exit; }
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) { send_json(['success' => false, 'message' => 'Invalid CSRF token'], 403); exit; }

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
