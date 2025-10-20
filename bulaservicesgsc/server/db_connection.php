<?php
/**
 * Database Connection Handler (no session logic here)
 */

declare(strict_types=1);

// ---------- DB config ----------
define('DB_HOST', 'localhost');
define('DB_USERNAME', 'bulaservices');
define('DB_PASSWORD', '84kjXKf8Tjf9WG1f');
define('DB_NAME', 'bulaservicesfiles');
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATION', 'utf8mb4_unicode_ci');
define('DB_OPTIONS', [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::ATTR_PERSISTENT         => true,
]);

// ---------- Error logging ----------
error_reporting(E_ALL);
ini_set('display_errors', '0'); // keep OFF in production
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../logs/db_errors.log');

/**
 * Return a shared PDO instance (singleton).
 */
function getDBConnection(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);

        try {
            $pdo = new PDO($dsn, DB_USERNAME, DB_PASSWORD, DB_OPTIONS);

            // Ensure proper charset/collation & timezone per connection
            $pdo->exec("SET NAMES '" . DB_CHARSET . "' COLLATE '" . DB_COLLATION . "'");
            $pdo->exec("SET time_zone = '+08:00'"); // Philippines time

        } catch (PDOException $e) {
            error_log('PDO Connection Error: ' . $e->getMessage());

            // Optional graceful error page (web only)
            if (php_sapi_name() !== 'cli') {
                header('HTTP/1.1 503 Service Unavailable');
                $fallback = __DIR__ . '/../templates/db_error.php';
                if (is_file($fallback)) {
                    include $fallback;
                } else {
                    echo 'Database connection error. Please try again later.';
                }
                exit();
            }

            // Re-throw for CLI / workers
            throw $e;
        }
    }

    return $pdo;
}

/**
 * Transaction helpers (optional)
 */
function beginTransaction(): bool { return getDBConnection()->beginTransaction(); }
function commitTransaction(): bool { return getDBConnection()->commit(); }
function rollbackTransaction(): bool { return getDBConnection()->rollBack(); }

/*
 * IMPORTANT:
 * ❌ Do NOT start or configure sessions here.
 * Session and cookie settings are centralized in /userbulaservices/server/config.php
 */
