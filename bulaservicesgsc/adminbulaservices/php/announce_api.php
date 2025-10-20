<?php
declare(strict_types=1);

/**
 * Announcements API (admin.bulaservicesgsc.com) - uploads inside user webroot
 */

header('Content-Type: application/json');

// ---- CORS ----
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed_origins = [
    'https://admin.bulaservicesgsc.com',
    'https://bulaservicesgsc.com'
];
if ($origin && in_array($origin, $allowed_origins, true)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Vary: Origin');
}
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { exit(0); }

// ---- Session (shared) ----
session_name('ADMIN_BULA_SESSID');
ini_set('session.cookie_domain', '.bulaservicesgsc.com');
ini_set('session.cookie_secure', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'None');
if (session_status() === PHP_SESSION_NONE) session_start();

// ---- Dependencies ----
require_once __DIR__ . '/../server/config.php';

// IMPORTANT: Upload to the *user site* webroot (document root you discovered)
define('UPLOAD_BASE_DIR', '/var/www/bulaservices/data/www/bulaservicesgsc.com/bulaservicesgsc/userbulaservices');
define('UPLOAD_BASE_URL', 'https://bulaservicesgsc.com');

// ---- Helpers ----
function json_response(bool $ok, $data = null, string $msg = '', int $code = 200): void {
    http_response_code($code);
    echo json_encode(['success'=>$ok,'data'=>$data,'message'=>$msg,'timestamp'=>time()]);
    exit;
}
function get_input(): array {
    static $input = null;
    if ($input !== null) return $input;
    $ctype = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($ctype, 'application/json') !== false) {
        $raw = file_get_contents('php://input') ?: '';
        $decoded = json_decode($raw, true);
        $input = is_array($decoded) ? $decoded : [];
    } else {
        $input = $_POST ?: [];
    }
    return $input;
}
function require_admin(): void {
    if (empty($_SESSION['admin_id'])) {
        json_response(false, null, 'Authentication required. Please log in.', 401);
    }
    if (isset($_SESSION['admin_ip'])) {
        $current = implode('.', array_slice(explode('.', $_SERVER['REMOTE_ADDR'] ?? ''), 0, 2));
        $sticky  = implode('.', array_slice(explode('.', $_SESSION['admin_ip']), 0, 2));
        if ($current !== $sticky) json_response(false, null, 'Security validation failed', 401);
    }
}

// ---- DB ----
try {
    $db = getDBConnection();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Throwable $e) {
    json_response(false, null, 'Database connection failed', 500);
}

// ---- Router ----
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'list': {
            $scope = $_GET['scope'] ?? 'public';
            $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));

            if ($scope === 'admin') {
                require_admin();
                $sql = "SELECT id, title, content, image_path, status, is_deleted, created_at, updated_at, published_at, created_by, updated_by
                        FROM announcements
                        WHERE is_deleted = 0
                        ORDER BY updated_at DESC
                        LIMIT ?";
            } else {
                $sql = "SELECT id, title, content, image_path, status, is_deleted, created_at, updated_at, published_at
                        FROM announcements
                        WHERE status='published' AND is_deleted=0
                          AND (published_at IS NULL OR published_at <= NOW())
                        ORDER BY COALESCE(published_at, updated_at) DESC
                        LIMIT ?";
            }
            $stmt = $db->prepare($sql);
            $stmt->bindValue(1, $limit, PDO::PARAM_INT);
            $stmt->execute();
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            foreach ($items as &$a) {
                if (!empty($a['image_path']) && !preg_match('#^https?://#i', $a['image_path'])) {
                    $path = ltrim($a['image_path'], '/');
                    $a['image_url'] = rtrim(UPLOAD_BASE_URL, '/') . '/' . $path;
                } else {
                    $a['image_url'] = $a['image_path'] ?? '';
                }
            }

            json_response(true, ['items'=>$items], 'Success');
            break;
        }

        case 'upload': {
            require_admin();

            if (empty($_FILES['image'])) json_response(false, null, 'No image file provided', 400);
            $f = $_FILES['image'];
            if ($f['error'] !== UPLOAD_ERR_OK) json_response(false, null, 'Upload error: '.$f['error'], 400);
            if ($f['size'] > 2 * 1024 * 1024) json_response(false, null, 'File too large (max 2MB)', 400);

            $allowed = ['image/jpeg','image/png','image/webp'];
            $fi = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($fi, $f['tmp_name']);
            finfo_close($fi);
            if (!in_array($mime, $allowed, true)) json_response(false, null, 'Invalid file type. Use JPG, PNG, or WebP', 400);

            $year = date('Y'); $month = date('m');
            $dir = rtrim(UPLOAD_BASE_DIR, '/') . "/uploads/announcements/{$year}/{$month}";
            if (!is_dir($dir) && !mkdir($dir, 0755, true)) json_response(false, null, 'Failed to create upload directory', 500);

            $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
            if ($mime === 'image/jpeg') $ext = 'jpg';
            if ($mime === 'image/png')  $ext = 'png';
            if ($mime === 'image/webp') $ext = 'webp';

            $filename = uniqid('', true) . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
            $dest = $dir . '/' . $filename;

            if (!move_uploaded_file($f['tmp_name'], $dest)) json_response(false, null, 'Failed to save file', 500);
            if (!file_exists($dest)) json_response(false, null, 'File verification failed', 500);

            $rel = "/uploads/announcements/{$year}/{$month}/{$filename}";
            $url = rtrim(UPLOAD_BASE_URL, '/') . $rel;

            json_response(true, ['image_path'=>$rel,'image_url'=>$url], 'Image uploaded successfully');
            break;
        }

        case 'create': {
            require_admin();
            $in = get_input();

            $title = trim((string)($in['title'] ?? ''));
            $content = (string)($in['content'] ?? '');
            $status = (string)($in['status'] ?? 'draft');
            if (!in_array($status, ['draft','published'], true)) $status = 'draft';
            $image_path = trim((string)($in['image_path'] ?? ''));

            if ($title === '') json_response(false, null, 'Title is required', 400);

            $published_at = $status === 'published' ? date('Y-m-d H:i:s') : null;

            $sql = "INSERT INTO announcements (title, content, image_path, status, published_at, created_by)
                    VALUES (?, ?, ?, ?, ?, ?)";
            $st = $db->prepare($sql);
            $st->execute([
                $title, $content,
                $image_path !== '' ? $image_path : null,
                $status, $published_at,
                $_SESSION['admin_id'] ?? null
            ]);

            json_response(true, ['id'=>(int)$db->lastInsertId()], 'Announcement created');
            break;
        }

        case 'update': {
            require_admin();
            $in = get_input();

            $id = (int)($in['id'] ?? 0);
            if ($id <= 0) json_response(false, null, 'Invalid ID', 400);

            $updates = [];
            $params  = [];

            foreach (['title','content','image_path','status'] as $f) {
                if (array_key_exists($f, $in)) {
                    if ($f === 'status' && !in_array($in[$f], ['draft','published'], true)) continue;
                    $updates[] = "$f = ?";
                    $params[]  = $in[$f];
                }
            }
            if (isset($in['status']) && $in['status'] === 'published') {
                $updates[] = "published_at = COALESCE(published_at, NOW())";
            }

            $updates[] = "updated_at = NOW()";
            $updates[] = "updated_by = ?";
            $params[]  = $_SESSION['admin_id'] ?? null;
            $params[]  = $id;

            if (empty($updates)) json_response(true, ['id'=>$id], 'No changes');

            $sql = "UPDATE announcements SET ".implode(', ',$updates)." WHERE id = ? AND is_deleted = 0";
            $st = $db->prepare($sql);
            $st->execute($params);

            json_response(true, ['id'=>$id], 'Announcement updated');
            break;
        }

        case 'delete': {
            require_admin();
            $in = get_input();
            $id = (int)($in['id'] ?? 0);
            if ($id <= 0) json_response(false, null, 'Invalid ID', 400);

            $st = $db->prepare("UPDATE announcements SET is_deleted = 1, updated_at = NOW(), updated_by = ? WHERE id = ?");
            $st->execute([ $_SESSION['admin_id'] ?? null, $id ]);

            json_response(true, ['id'=>$id], 'Announcement deleted');
            break;
        }

        default:
            json_response(false, null, 'Invalid action', 400);
    }
} catch (Throwable $e) {
    json_response(false, null, 'Server error: '.$e->getMessage(), 500);
}
