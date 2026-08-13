<?php
// Report ALL errors including notices so the error logger captures everything
error_reporting(E_ALL);
ini_set('display_errors', '0');  // Don't show errors to browser — log them to DB instead
ini_set('log_errors', '1');

// Auto-load .env if not loaded by server
if (!getenv('DB_HOST')) {
    $envPath = dirname(__DIR__) . '/.env';
    if (!file_exists($envPath)) {
        $envPath = dirname(__DIR__, 2) . '/.env';
    }
    if (file_exists($envPath)) {
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) continue;
            if (str_contains($line, '=')) {
                list($name, $val) = explode('=', $line, 2);
                $name = trim($name);
                $val = trim($val, " \t\n\r\0\x0B\"'");
                putenv("{$name}={$val}");
                $_ENV[$name] = $val;
                $_SERVER[$name] = $val;
            }
        }
    }
}

// Support Environment Variables for Production (Render/Aiven) with fallback to Localhost
$servername = getenv('DB_HOST') ?: "127.0.0.1";
$username = getenv('DB_USER') ?: "root";
$password = getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : "";
$dbname = getenv('DB_NAME') ?: "pos_inventory_system_db";
$port = getenv('DB_PORT') ?: "3306";

try {
    $conn = new mysqli($servername, $username, $password, $dbname, (int) $port);

    if ($conn->connect_error) {
        throw new RuntimeException("DB connection failed: " . $conn->connect_error);
    }

    $conn->set_charset("utf8mb4");
    $conn->query("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    date_default_timezone_set('Asia/Manila');
    $conn->query("SET time_zone = '+08:00'");

} catch (RuntimeException $e) {
    error_log("[DB_CONNECT] " . $e->getMessage());
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection error. Check server logs.']);
    exit;
}

// Install global error/exception/fatal handlers → log everything to error_logs table
include_once __DIR__ . '/error_logger.php';
?>