<?php
// Report ALL errors including notices so the error logger captures everything
error_reporting(E_ALL);
ini_set('display_errors', '0');  // Don't show errors to browser — log them to DB instead
ini_set('log_errors', '1');

// Auto-load .env if not loaded by server
if (!getenv('DB_HOST') && !getenv('DATABASE_URL') && !getenv('MYSQL_URL')) {
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

// Default Production / Coolify Credentials
$servername = "ierbkglctwgkpyshwqkdlht3";
$username = "mysql";
$password = "larable";
$dbname = "default";
$port = "3306";

// Parse DATABASE_URL / MYSQL_URL if provided (e.g. mysql://mysql:larable@ierbkglctwgkpyshwqkdlht3:3306/default)
$rawDbUrl = getenv('DATABASE_URL') ?: (getenv('MYSQL_URL') ?: (getenv('CLEARDB_DATABASE_URL') ?: ($_ENV['DATABASE_URL'] ?? ($_SERVER['DATABASE_URL'] ?? ''))));
if ($rawDbUrl) {
    $parsed = parse_url($rawDbUrl);
    if ($parsed) {
        if (!empty($parsed['host'])) $servername = $parsed['host'];
        if (!empty($parsed['user'])) $username = $parsed['user'];
        if (isset($parsed['pass'])) $password = $parsed['pass'];
        if (!empty($parsed['path'])) $dbname = ltrim($parsed['path'], '/');
        if (!empty($parsed['port'])) $port = (string)$parsed['port'];
    }
}

// Individual Environment Variables (override connection URL if explicitly provided)
$servername = getenv('DB_HOST') ?: (getenv('MYSQL_HOST') ?: (getenv('MYSQLHOST') ?: ($_ENV['DB_HOST'] ?? ($_SERVER['DB_HOST'] ?? $servername))));
$username   = getenv('DB_USER') ?: (getenv('MYSQL_USER') ?: (getenv('MYSQLUSER') ?: ($_ENV['DB_USER'] ?? ($_SERVER['DB_USER'] ?? $username))));

if (getenv('DB_PASSWORD') !== false && getenv('DB_PASSWORD') !== '') {
    $password = getenv('DB_PASSWORD');
} elseif (getenv('MYSQL_PASSWORD') !== false && getenv('MYSQL_PASSWORD') !== '') {
    $password = getenv('MYSQL_PASSWORD');
} elseif (getenv('MYSQLPASSWORD') !== false && getenv('MYSQLPASSWORD') !== '') {
    $password = getenv('MYSQLPASSWORD');
} elseif (!empty($_ENV['DB_PASSWORD'])) {
    $password = $_ENV['DB_PASSWORD'];
} elseif (!empty($_SERVER['DB_PASSWORD'])) {
    $password = $_SERVER['DB_PASSWORD'];
}

$dbname = getenv('DB_NAME') ?: (getenv('DB_DATABASE') ?: (getenv('MYSQL_DATABASE') ?: (getenv('MYSQLDATABASE') ?: ($_ENV['DB_NAME'] ?? ($_SERVER['DB_NAME'] ?? $dbname)))));
$port   = getenv('DB_PORT') ?: (getenv('MYSQL_PORT') ?: (getenv('MYSQLPORT') ?: ($_ENV['DB_PORT'] ?? ($_SERVER['DB_PORT'] ?? $port))));

try {
    mysqli_report(MYSQLI_REPORT_OFF);
    $conn = @new mysqli($servername, $username, $password, $dbname, (int) $port);

    // Auto-fallback if initial connection fails
    if ($conn->connect_error) {
        $fallbacks = ['larable', 'Larable@2025', 'root', ''];
        foreach ($fallbacks as $fbPass) {
            if ($fbPass === $password) continue;
            $testConn = @new mysqli($servername, $username, $fbPass, $dbname, (int) $port);
            if (!$testConn->connect_error) {
                $conn = $testConn;
                break;
            }
        }
    }

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