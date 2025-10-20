<?php
declare(strict_types=1);
header('Content-Type: application/json');

require_once __DIR__ . '/../server/config.php';
require_once __DIR__ . '/mailer.php'; // helper stays (not sending here)

function hashPassword(string $plain): string {
    return password_hash($plain, PASSWORD_DEFAULT);
}

// ---------------- Debug toggle (write to /tmp) ----------------
const REG_DEBUG = true;
function reg_log(string $msg): void {
    if (REG_DEBUG) @file_put_contents('/tmp/register_api.log', '['.date('c')."] ".$msg."\n", FILE_APPEND);
}

/**
 * Parse a human-entered date safely.
 * Accepts "Y-m-d" or "m/d/Y". Returns "Y-m-d" or null if invalid.
 */
function parseBirthDate(?string $raw): ?string {
    if (!$raw) return null;
    $raw = trim($raw);
    foreach (['Y-m-d','m/d/Y'] as $fmt) {
        $dt = DateTime::createFromFormat($fmt, $raw);
        if ($dt && $dt->format($fmt) === $raw) return $dt->format('Y-m-d');
    }
    $ts = strtotime($raw);
    return ($ts !== false && $ts <= time()) ? date('Y-m-d', $ts) : null;
}

/**
 * Save a base64 data URL image (camera capture) to profile_pics folder.
 * Returns relative path like "uploads/profile_pics/abc.png".
 */
function saveBase64ProfilePicture(string $dataUrl): string {
    if (strpos($dataUrl, 'data:image') !== 0) throw new RuntimeException('Invalid image data.');
    [$meta, $b64] = explode(',', $dataUrl, 2);
    if (!$b64) throw new RuntimeException('Invalid image data.');
    $bin = base64_decode($b64, true);
    if ($bin === false) throw new RuntimeException('Could not decode image data.');

    $ext = (strpos($meta,'image/jpeg')!==false) ? 'jpg' :
           ((strpos($meta,'image/png')!==false)  ? 'png' :
           ((strpos($meta,'image/webp')!==false) ? 'webp' : 'png'));

    $dir = __DIR__ . '/../uploads/profile_pics';
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Failed to create upload directory.');
    }

    $fname  = 'cap_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $pathFs = $dir . '/' . $fname;
    if (file_put_contents($pathFs, $bin) === false) {
        throw new RuntimeException('Failed to save captured image.');
    }
    return 'uploads/profile_pics/' . $fname;
}

$input = $_POST ?: json_decode(file_get_contents('php://input'), true) ?: [];
$sanitize = fn($k) => sanitizeInput($input[$k] ?? '');

try {
    $pdo = getDBConnection();

    // -------------------- Capture & Normalize --------------------
    $userType       = $sanitize('resident_status');           // resident | outsider
    $firstName      = $sanitize('first_name');
    $middleName     = $sanitize('middle_name');
    $lastName       = $sanitize('last_name');
    $suffix         = $sanitize('suffix');
    $email          = strtolower(trim($sanitize('email')));
    $username       = trim($sanitize('username'));
    $password       = (string)($input['password'] ?? '');
    $confirmPassword= (string)($input['confirm_password'] ?? '');
    $contactNumber  = preg_replace('/\D+/', '', (string)$sanitize('contact_number')); // digits only
    $address        = $sanitize('address');

    // Resident-only (null for outsiders)
    $birthPlace         = ($userType === 'resident') ? $sanitize('birth_place') : null;
    $birthDate          = ($userType === 'resident') ? parseBirthDate($input['birth_date'] ?? null) : null;
    $age                = ($userType === 'resident') ? (int)($input['age'] ?? 0) : null;
    $civilStatus        = ($userType === 'resident') ? $sanitize('civil_status') : null;
    $gender             = ($userType === 'resident') ? $sanitize('gender') : null;
    $purok              = ($userType === 'resident') ? $sanitize('purok') : null;
    $yearStartedStaying = ($userType === 'resident') ? (int)($input['year_started_staying'] ?? 0) : null;
    $occupation         = ($userType === 'resident') ? $sanitize('occupation') : null;

    // -------------------- Validate --------------------
    $errors = [];

    $req = [
        'resident_status' => $userType,
        'first_name'      => $firstName,
        'last_name'       => $lastName,
        'email'           => $email,
        'username'        => $username,
        'password'        => $password,
        'confirm_password'=> $confirmPassword,
        'contact_number'  => $contactNumber,
        'address'         => $address,
    ];
    foreach ($req as $k => $v) {
        if ($v === '' || $v === null) $errors[$k] = ucfirst(str_replace('_', ' ', $k)) . ' is required';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL))                $errors['email'] = 'Invalid email format';
    if (strlen($email) > 100)                                      $errors['email'] = 'Email too long';
    if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username))        $errors['username'] = '3–50 chars; letters, numbers, _ . - only';
    if (strlen($firstName) > 100)                                  $errors['first_name'] = 'First name too long';
    if ($middleName !== '' && strlen($middleName) > 100)           $errors['middle_name'] = 'Middle name too long';
    if (strlen($lastName) > 100)                                   $errors['last_name'] = 'Last name too long';
    if ($suffix !== '' && strlen($suffix) > 10)                    $errors['suffix'] = 'Suffix too long';
    if (strlen($address) > 255)                                    $errors['address'] = 'Address too long';
    if (strlen($contactNumber) > 20)                               $errors['contact_number'] = 'Contact number too long';
    if ($contactNumber !== '' && !preg_match('/^(09\d{9}|639\d{9})$/', $contactNumber))
        $errors['contact_number'] = 'Invalid PH mobile number';

    if ($password !== $confirmPassword)                            $errors['confirm_password'] = 'Passwords do not match';
    if (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password))
        $errors['password'] = 'Min 8 chars, include letters and numbers';

    if (!in_array($userType, ['resident','outsider'], true))       $errors['resident_status'] = 'Invalid resident status';

    if ($userType === 'resident') {
        if ($gender && !in_array($gender, ['male','female'], true))                      $errors['gender'] = 'Invalid sex';
        if ($civilStatus && !in_array($civilStatus, ['single','married','widowed','separated'], true))
            $errors['civil_status'] = 'Invalid civil status';
        if ($purok && !in_array($purok, array_map('strval', range(1,25)), true))         $errors['purok'] = 'Invalid purok';

        if (($input['birth_date'] ?? '') !== '') {
            if ($birthDate === null) {
                $errors['birth_date'] = 'Invalid birth date';
            } else {
                $ts = strtotime($birthDate);
                if ($ts === false || $ts > time()) $errors['birth_date'] = 'Invalid birth date';
                if ($age !== null && $age > 0) {
                    $calcAge = (int) floor((time() - $ts) / (365.25*24*3600));
                    if (abs($calcAge - $age) > 2) $errors['age'] = 'Age doesn’t match birth date';
                }
            }
        }
    } else {
        // Outsider: hard-null resident-only values to avoid accidental persistence
        $birthPlace = $birthDate = $civilStatus = $gender = $purok = $occupation = null;
        $age = $yearStartedStaying = null;
    }

    // Duplicates
    $stmt = $pdo->prepare("SELECT id, email, username, email_verified, status FROM users WHERE email = ? OR username = ?");
    $stmt->execute([$email, $username]);
    $existingByEmail = null; $usernameTaken = false;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (strcasecmp($row['email'], $email) === 0)   $existingByEmail = $row;
        if (strcasecmp($row['username'], $username)===0) $usernameTaken = true;
    }
    if ($existingByEmail && (int)$existingByEmail['email_verified'] === 1 && $existingByEmail['status'] === 'active')
        $errors['email'] = 'Email already exists';
    if ($usernameTaken) $errors['username'] = 'Username already exists';

    if ($errors) {
        echo json_encode(['success'=>false,'message'=>'Please correct the errors below','errors'=>$errors]);
        exit;
    }

    // -------------------- Profile picture (optional) --------------------
    $profilePicture = null;

    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        try {
            $profilePicture = uploadProfilePicture($_FILES['profile_picture']);
        } catch (Throwable $e) {
            echo json_encode(['success'=>false,'errors'=>['profile_picture'=>$e->getMessage()]]);
            exit;
        }
    }
    if (!$profilePicture && !empty($input['profile_picture_data'])) {
        try {
            $profilePicture = saveBase64ProfilePicture((string)$input['profile_picture_data']);
        } catch (Throwable $e) {
            echo json_encode(['success'=>false,'errors'=>['profile_picture'=>$e->getMessage()]]);
            exit;
        }
    }

    // -------------------- Insert or Refresh (unverified) --------------------
    // Normalize every optional value to NULL to avoid strict-mode issues on ENUM/nullable columns
    $middleName         = ($middleName === '') ? null : $middleName;
    $suffix             = ($suffix === '') ? null : $suffix;
    $birthPlace         = ($birthPlace === '') ? null : $birthPlace;
    $birthDate          = ($birthDate === '') ? null : $birthDate;
    $age                = ($age ?: null);
    $civilStatus        = ($civilStatus === '' ? null : $civilStatus); // ENUM
    $gender             = ($gender === '' ? null : $gender);           // ENUM
    $purok              = ($purok === '' ? null : $purok);
    $yearStartedStaying = ($yearStartedStaying ?: null);
    $occupation         = ($occupation === '' ? null : $occupation);
    $profilePicture     = ($profilePicture === '' ? null : $profilePicture);

    $pdo->beginTransaction();

    // Re-check inside txn
    $stmt = $pdo->prepare("SELECT id, email_verified, status FROM users WHERE email=? LIMIT 1");
    $stmt->execute([$email]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing && (int)$existing['email_verified'] === 1 && $existing['status'] === 'active') {
        $pdo->rollBack();
        echo json_encode(['success'=>false,'message'=>'Email already exists','errors'=>['email'=>'Email already exists']]);
        exit;
    }

    if (!$existing) {
        $sql = "
            INSERT INTO users (
                user_type, first_name, middle_name, last_name, suffix, birth_place, birth_date, age,
                civil_status, gender, purok, year_started_staying, contact_number, occupation,
                address, email, username, password, profile_picture,
                is_active, email_verified, verified_at, status, created_at
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                1, 0, NULL, 'unverified', NOW()
            )";
        $params = [
            $userType, $firstName, $middleName, $lastName, $suffix, $birthPlace, $birthDate, $age,
            $civilStatus, $gender, $purok, $yearStartedStaying, $contactNumber, $occupation,
            $address, $email, $username, hashPassword($password), $profilePicture
        ];
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    } else {
        $sql = "
            UPDATE users SET
                user_type=?, first_name=?, middle_name=?, last_name=?, suffix=?,
                birth_place=?, birth_date=?, age=?, civil_status=?, gender=?,
                purok=?, year_started_staying=?, contact_number=?, occupation=?, address=?,
                username=?, password=?, profile_picture=?,
                is_active=1, email_verified=0, verified_at=NULL, status='unverified',
                updated_at=NOW()
            WHERE id=?";
        $params = [
            $userType, $firstName, $middleName, $lastName, $suffix,
            $birthPlace, $birthDate, $age, $civilStatus, $gender,
            $purok, $yearStartedStaying, $contactNumber, $occupation, $address,
            $username, hashPassword($password), $profilePicture,
            (int)$existing['id']
        ];
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Registration received. Please review and accept the Terms of Service & Privacy Policy.',
        'email'   => $email
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    reg_log('register_api fatal: '.$e->getMessage());
    if (isset($stmt) && $stmt instanceof PDOStatement) {
        $ei = $stmt->errorInfo();
        reg_log('PDO errorInfo: '.json_encode($ei));
    }
    echo json_encode([
        'success'=>false,
        'message'=>'Server error. Please try again.',
        'debug'  => REG_DEBUG ? $e->getMessage() : null
    ]);
}
