<?php
// Start session at the very top (use consistent session name)
session_name('BARANGAY_BULA_SESSID');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Development: log errors but do NOT output them into JSON responses
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/user_errors.log');

// Buffer output so stray output/warnings don't break JSON
ob_start();

// Shutdown handler to catch fatal errors and respond with safe JSON
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        error_log("Fatal error in php/index.php: " . print_r($err, true));
        // Clean any output and return a safe JSON error
        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Internal server error'
        ]);
    }
});

require_once __DIR__ . '/../server/config.php';

header('Content-Type: application/json');
echo json_encode(['success'=>false,'message'=>'Deprecated endpoint']);

$response = [
    'success' => false,
    'message' => 'Something went wrong',
    'errors' => []
];

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Clean buffer and return
    while (ob_get_level()) ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// --- LOGIN HANDLER ---
if (isset($_POST['login'])) {
    try {
        $conn = getDBConnection();

        $email = sanitizeInput($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            while (ob_get_level()) ob_end_clean();
            echo json_encode([
                "success" => false,
                "message" => "Email and password are required"
            ]);
            exit;
        }

        $stmt = $conn->prepare("SELECT id, email, password, first_name, last_name, is_active FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            if (!$user['is_active']) {
                while (ob_get_level()) ob_end_clean();
                echo json_encode([
                    "success" => false,
                    "message" => "Account is deactivated. Please contact administrator."
                ]);
                exit;
            }

            if (password_verify($password, $user['password'])) {
                // SUCCESSFUL LOGIN - SET SESSION VARIABLES
                // NOTE: auth_functions::loginUser accepts (int $userId, string $role)
                loginUser((int)$user['id'], 'user');

                // Set additional user information in session
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_name'] = trim($user['first_name'] . ' ' . $user['last_name']);

                // Update last login timestamp & reset failed attempts
                $updateStmt = $conn->prepare("UPDATE users SET last_login = NOW(), failed_login_attempts = 0 WHERE id = ?");
                $updateStmt->execute([$user['id']]);

                error_log("User {$user['id']} logged in successfully from {$_SERVER['REMOTE_ADDR']}");

                while (ob_get_level()) ob_end_clean();
                echo json_encode([
                    "success" => true,
                    "message" => "Login successful!",
                    "redirect" => "home.php"
                ]);
                exit;
            } else {
                // Increment failed login attempts
                $updateStmt = $conn->prepare("UPDATE users SET failed_login_attempts = failed_login_attempts + 1 WHERE email = ?");
                $updateStmt->execute([$email]);

                while (ob_get_level()) ob_end_clean();
                echo json_encode([
                    "success" => false,
                    "message" => "Invalid email or password"
                ]);
                exit;
            }
        } else {
            while (ob_get_level()) ob_end_clean();
            echo json_encode([
                "success" => false,
                "message" => "Invalid email or password"
            ]);
            exit;
        }
    } catch (Exception $e) {
        error_log("Login error: " . $e->getMessage());
        while (ob_get_level()) ob_end_clean();
        echo json_encode([
            "success" => false,
            "message" => "Login error: " . $e->getMessage()
        ]);
        exit;
    }
}

// --- REGISTRATION HANDLER ---
try {
    $conn = getDBConnection();

    // Sanitize and capture inputs
    $userType = sanitizeInput($_POST['resident_status'] ?? '');
    $firstName = sanitizeInput($_POST['first_name'] ?? '');
    $lastName = sanitizeInput($_POST['last_name'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $username = sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $contactNumber = sanitizeInput($_POST['contact_number'] ?? '');
    $address = sanitizeInput($_POST['address'] ?? '');

    // Resident-specific fields
    $middleName = isset($_POST['middle_name']) ? sanitizeInput($_POST['middle_name']) : null;
    $suffix = isset($_POST['suffix']) ? sanitizeInput($_POST['suffix']) : null;
    $birthPlace = ($userType === 'resident') ? sanitizeInput($_POST['birth_place'] ?? '') : null;
    $birthDate = ($userType === 'resident') ? ($_POST['birth_date'] ?? null) : null;
    $age = ($userType === 'resident') ? (int)($_POST['age'] ?? 0) : null;
    $civilStatus = ($userType === 'resident') ? sanitizeInput($_POST['civil_status'] ?? '') : null;
    $gender = ($userType === 'resident') ? sanitizeInput($_POST['gender'] ?? '') : null;
    $purok = ($userType === 'resident') ? sanitizeInput($_POST['purok'] ?? '') : null;
    $yearStartedStaying = ($userType === 'resident') ? (int)($_POST['year_started_staying'] ?? 0) : null;
    $occupation = ($userType === 'resident') ? sanitizeInput($_POST['occupation'] ?? '') : null;

    // Validate required fields
    $requiredFields = [
        'first_name' => $firstName,
        'last_name' => $lastName,
        'email' => $email,
        'username' => $username,
        'password' => $password,
        'confirm_password' => $confirmPassword,
        'contact_number' => $contactNumber,
        'address' => $address,
        'resident_status' => $userType
    ];

    foreach ($requiredFields as $field => $value) {
        if (empty($value)) {
            $response['errors'][$field] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
        }
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response['errors']['email'] = 'Invalid email format';
    }

    if ($password !== $confirmPassword) {
        $response['errors']['confirm_password'] = 'Passwords do not match';
    }

    if (strlen($password) < 6) {
        $response['errors']['password'] = 'Password must be at least 6 characters';
    }

    // Check duplicates
    $stmt = $conn->prepare("SELECT email, username FROM users WHERE email = ? OR username = ?");
    $stmt->execute([$email, $username]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row['email'] === $email) $response['errors']['email'] = 'Email already exists';
        if ($row['username'] === $username) $response['errors']['username'] = 'Username already exists';
    }

    if (!empty($response['errors'])) {
        $response['message'] = 'Please correct the errors below';
        while (ob_get_level()) ob_end_clean();
        echo json_encode($response);
        exit;
    }

    // Handle file upload
    $profilePicture = null;
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        try {
            $profilePicture = uploadProfilePicture($_FILES['profile_picture']);
        } catch (Exception $e) {
            $response['errors']['profile_picture'] = $e->getMessage();
            while (ob_get_level()) ob_end_clean();
            echo json_encode($response);
            exit;
        }
    }

    $hashedPassword = hashPassword($password);

    $sql = "INSERT INTO users (
        user_type, first_name, middle_name, last_name, suffix, birth_place, birth_date, age,
        civil_status, gender, purok, year_started_staying, contact_number, occupation,
        address, email, username, password, profile_picture, is_verified
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        $userType, $firstName, $middleName, $lastName, $suffix, $birthPlace, $birthDate, $age,
        $civilStatus, $gender, $purok, $yearStartedStaying, $contactNumber, $occupation,
        $address, $email, $username, $hashedPassword, $profilePicture, 1
    ]);

    $userId = $conn->lastInsertId();
    error_log("New user registered: ID {$userId}, Email: {$email}");

    $response['success'] = true;
    $response['message'] = 'Registration successful! You can now login.';
    $response['email'] = $email;

} catch (PDOException $e) {
    error_log("PDO Exception in registration: " . $e->getMessage());
    $response['message'] = 'Database error';
} catch (Exception $e) {
    error_log("Exception in registration: " . $e->getMessage());
    $response['message'] = 'Server error';
}

// Clean buffer and return JSON
while (ob_get_level()) ob_end_clean();
echo json_encode($response);
exit;
