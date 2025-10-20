<?php
echo "<h1>File Permission Check</h1>";
echo "<pre>";

$paths_to_check = [
    __DIR__,
    __DIR__ . '/../../logs/',
    __DIR__ . '/../../../server/',
    session_save_path() ?: sys_get_temp_dir()
];

foreach ($paths_to_check as $path) {
    if (file_exists($path)) {
        $perms = fileperms($path);
        $readable = is_readable($path) ? 'READABLE' : 'NOT READABLE';
        $writable = is_writable($path) ? 'WRITABLE' : 'NOT WRITABLE';
        echo sprintf("%s - %o - %s, %s\n", $path, $perms, $readable, $writable);
    } else {
        echo "MISSING: $path\n";
    }
}

echo "\nSession Save Path: " . session_save_path() . "\n";
echo "Session Save Path Writable: " . (is_writable(session_save_path()) ? 'YES' : 'NO') . "\n";

echo "</pre>";
?>