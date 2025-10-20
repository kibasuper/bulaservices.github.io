<?php
// db.php - put in the same folder or adjust require paths
// Edit these to match your environment
$dbHost = 'localhost';
$dbName = 'bulaservicesfiles';
$dbUser = 'bulaservices';
$dbPass = '84kjXKf8Tjf9WG1f';
$charset = 'utf8mb4';

$dsn = "mysql:host=$dbHost;dbname=$dbName;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
} catch (PDOException $e) {
    // In production, log and show a friendly message instead
    error_log("DB connection failed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
}
