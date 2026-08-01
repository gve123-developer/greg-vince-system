<?php
// Report ALL errors including notices so the error logger captures everything
error_reporting(E_ALL);
ini_set('display_errors', '0');  // Don't show errors to browser — log them to DB instead
ini_set('log_errors', '1');

// Load .env file if present
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        $delim = strpos($line, '=') !== false ? '=' : (strpos($line, '-') !== false ? '-' : (strpos($line, ':') !== false ? ':' : null));
        if ($delim !== null) {
            list($key, $val) = explode($delim, $line, 2);
            $key = trim($key);
            $val = trim($val, "\"' \t\n\r\0\x0B");
            putenv("$key=$val");
            $_ENV[$key] = $val;
        }
    }
}

// Support Environment Variables for Production with fallback to Localhost
$servername = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: "127.0.0.1";
$username   = $_ENV['DB_USER'] ?? getenv('DB_USER') ?: "root";
$password   = $_ENV['DB_PASSWORD'] ?? (getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : "root");
$dbname     = $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: "newDBjustInCase";
$port       = $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: "3306";
$use_ssl    = ($_ENV['DB_SSL'] ?? getenv('DB_SSL')) === 'true' || ($_ENV['DB_SSL'] ?? getenv('DB_SSL')) === '1';

try {
    $conn = mysqli_init();
    if (!$conn) {
        throw new RuntimeException("mysqli_init failed");
    }

    if ($use_ssl) {
        $conn->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);
        $conn->ssl_set(NULL, NULL, NULL, NULL, NULL);
    }

    $connected = @$conn->real_connect(
        $servername,
        $username,
        $password,
        $dbname,
        (int)$port,
        NULL,
        $use_ssl ? MYSQLI_CLIENT_SSL : 0
    );

    if (!$connected || $conn->connect_error) {
        $err_msg = $conn->connect_error ?: mysqli_connect_error() ?: "Unknown connection error";
        throw new RuntimeException("DB connection failed to host '$servername:$port': " . $err_msg);
    }

    $conn->set_charset("utf8mb4");
    $conn->query("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

} catch (Throwable $e) {
    error_log("[DB_CONNECT] " . $e->getMessage());
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
        header("Access-Control-Allow-Origin: *");
    }
    http_response_code(500);
    
    $is_default_host = ($servername === "127.0.0.1" || $servername === "localhost");
    $response = [
        'success' => false,
        'message' => 'Database connection error.',
        'error'   => $e->getMessage(),
    ];
    if ($is_default_host) {
        $response['hint'] = "DB_HOST is currently using default (127.0.0.1). Ensure DB_HOST, DB_USER, DB_PASSWORD, DB_NAME, DB_PORT are set in Render Dashboard Environment Variables.";
    }
    echo json_encode($response);
    exit;
}

// Install global error/exception/fatal handlers → log everything to error_logs table
include_once __DIR__ . '/error_logger.php';
?>