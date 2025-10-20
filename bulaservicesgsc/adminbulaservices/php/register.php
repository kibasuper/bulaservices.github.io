<?php
require_once __DIR__ . '/../../config/config.php';

header('Content-Type: application/json');

// Only superadmin can access this
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'superadmin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access Denied']);
    exit();
}

$response = ['success' => false, 'error' => ''];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    // Validate input
    $requiredFields = ['username', 'password', 'confirmPassword', 'email', 'firstName', 'lastName', 'role'];
    foreach ($requiredFields as $field) {
        if (empty($_POST[$field])) {
            throw new Exception('All required fields must be filled');
        }
    }

    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirmPassword'];
    $email = trim($_POST['email']);
    $firstName = trim($_POST['firstName']);
    $lastName = trim($_POST['lastName']);
    $role = $_POST['role'];
    $contactNumber = trim($_POST['contactNumber'] ?? '');

    if ($password !== $confirmPassword) {
        throw new Exception('Passwords do not match');
    }

    if (strlen($password) < 8) {
        throw new Exception('Password must be at least 8 characters');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }

    $conn = getDBConnection();
    
    // Check if username or email exists
    $stmt = $conn->prepare("SELECT admin_id FROM admins WHERE username = ? OR email = ?");
    $stmt->bind_param("ss", $username, $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        throw new Exception('Username or email already exists');
    }

    // Hash password
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert into admins table
    $insertStmt = $conn->prepare("INSERT INTO admins (
        username, password_hash, email, first_name, last_name, 
        role, contact_number, is_active
    ) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
    
    $insertStmt->bind_param(
        "sssssss", 
        $username, 
        $passwordHash, 
        $email, 
        $firstName, 
        $lastName, 
        $role, 
        $contactNumber
    );
    
    if ($insertStmt->execute()) {
        $response = [
            'success' => true,
            'message' => 'Admin registered successfully!',
            'redirect' => 'dashboard.php'
        ];
    } else {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $insertStmt->close();
    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
