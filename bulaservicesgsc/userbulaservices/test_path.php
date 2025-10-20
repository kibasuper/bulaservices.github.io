<?php
// test_path.php

$documentRoot = $_SERVER['DOCUMENT_ROOT'];
$host = $_SERVER['HTTP_HOST'];
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
$baseUrl = $https . "://" . $host;

// Expected location relative to DocumentRoot
$gymApiRelative = "/server/gym_api.php";
$gymApiFull = $documentRoot . $gymApiRelative;

header("Content-Type: text/plain");
echo "DocumentRoot: " . $documentRoot . PHP_EOL;
echo "Expected full path: " . $gymApiFull . PHP_EOL;

if (file_exists($gymApiFull)) {
    echo "✅ Found gym_api.php" . PHP_EOL;
    $testUrl = $baseUrl . $gymApiRelative . "?action=get_available_slots&date=" . date('Y-m-d');
    echo "Try this in browser: " . $testUrl . PHP_EOL;
} else {
    echo "❌ gym_api.php not found at expected location." . PHP_EOL;
}
