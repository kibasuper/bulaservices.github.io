<?php
declare(strict_types=1);
require_once __DIR__ . '/../server/config.php';

// Auto-cancel service requests older than 7 days if not claimed
$db->exec("
    UPDATE service_requests
    SET status = 'cancelled'
    WHERE status = 'approved'
    AND approved_date IS NOT NULL
    AND approved_date < NOW() - INTERVAL 7 DAY
");

echo "Auto-cancel cleanup done: " . date('Y-m-d H:i:s');
