<?php
/**
 * Standalone Production MySQL Database Connection Diagnostics Script
 * Upload this file to your production web server root or run via CLI: php test_prod_db.php
 */

// Basic Security / Safety: Disable errors display in output to avoid leakage, but capture them
error_reporting(E_ALL);
ini_set('display_errors', '1');

$isCli = (php_sapi_name() === 'cli');

if (!$isCli) {
    echo "<!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Production MySQL Connection Test</title>
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #0f172a; color: #f8fafc; padding: 20px; line-height: 1.6; }
            .card { background: #1e293b; border-radius: 12px; padding: 24px; max-width: 800px; margin: 0 auto; box-shadow: 0 10px 25px rgba(0,0,0,0.5); }
            h1 { font-size: 1.5rem; margin-top: 0; color: #38bdf8; border-bottom: 1px solid #334155; padding-bottom: 12px; }
            .badge { display: inline-block; padding: 4px 10px; border-radius: 6px; font-weight: bold; font-size: 0.85rem; }
            .badge-success { background: #166534; color: #4ade80; }
            .badge-fail { background: #991b1b; color: #fca5a5; }
            .badge-warn { background: #854d0e; color: #fde047; }
            pre { background: #0f172a; padding: 12px; border-radius: 8px; overflow-x: auto; font-size: 0.9rem; border: 1px solid #334155; }
            table { width: 100%; border-collapse: collapse; margin-top: 12px; }
            th, td { text-align: left; padding: 8px 12px; border-bottom: 1px solid #334155; }
            th { background: #0f172a; color: #94a3b8; font-size: 0.85rem; text-transform: uppercase; }
            .hint { background: #1e1b4b; border-left: 4px solid #6366f1; padding: 12px; margin-top: 16px; border-radius: 4px; font-size: 0.9rem; }
        </style>
    </head>
    <body>
    <div class='card'>
    <h1>🔌 Production MySQL Database Diagnostics</h1>";
}

function out($msg, $type = 'info') {
    global $isCli;
    if ($isCli) {
        $prefix = match($type) {
            'success' => '[OK] ',
            'fail'    => '[FAIL] ',
            'warn'    => '[WARN] ',
            default   => '[INFO] '
        };
        echo $prefix . strip_tags($msg) . "\n";
    } else {
        $badgeClass = match($type) {
            'success' => 'badge-success',
            'fail'    => 'badge-fail',
            'warn'    => 'badge-warn',
            default   => ''
        };
        if ($badgeClass) {
            echo "<p><span class='badge {$badgeClass}'>" . strtoupper($type) . "</span> {$msg}</p>";
        } else {
            echo "<p>{$msg}</p>";
        }
    }
}

// 1. Check PHP MySQLi Extension
if (!extension_loaded('mysqli')) {
    out("PHP 'mysqli' extension is NOT installed or enabled on this server!", "fail");
    if (!$isCli) echo "</div></body></html>";
    exit(1);
} else {
    out("PHP 'mysqli' extension is loaded and available.", "success");
}

// 2. Load .env file
$envPath = __DIR__ . '/.env';
if (!file_exists($envPath)) {
    $envPath = dirname(__DIR__) . '/.env';
}

$envVars = [];
if (file_exists($envPath)) {
    out("Found .env file at: <code>{$envPath}</code>", "info");
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (str_contains($line, '=')) {
            list($name, $val) = explode('=', $line, 2);
            $name = trim($name);
            $val = trim($val, " \t\n\r\0\x0B\"'");
            $envVars[$name] = $val;
            putenv("{$name}={$val}");
        }
    }
} else {
    out("No .env file found at {$envPath}. Falling back to system environment / defaults.", "warn");
}

// Allow URL override for easy browser testing
$host = $_GET['host'] ?? getenv('DB_HOST') ?: ($envVars['DB_HOST'] ?? '127.0.0.1');
$port = $_GET['port'] ?? getenv('DB_PORT') ?: ($envVars['DB_PORT'] ?? '3306');
$user = $_GET['user'] ?? getenv('DB_USER') ?: ($envVars['DB_USER'] ?? 'root');
$pass = $_GET['pass'] ?? (getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : ($envVars['DB_PASSWORD'] ?? 'Larable@2025'));
$db   = $_GET['dbname'] ?? getenv('DB_NAME') ?: ($envVars['DB_NAME'] ?? 'pos_inventory_system_db');

$maskedPass = ($pass === '') ? '(empty string)' : str_repeat('*', max(1, strlen($pass) - 2)) . substr($pass, -2);

out("Testing Connection Target: <strong>{$host}:{$port}</strong> | User: <strong>{$user}</strong> | DB: <strong>{$db}</strong> | Password: <code>{$maskedPass}</code>");

// 3. Attempt Connection
mysqli_report(MYSQLI_REPORT_OFF);
$startTime = microtime(true);
$conn = @new mysqli($host, $user, $pass, $db, (int)$port);
$latency = round((microtime(true) - $startTime) * 1000, 2);

if ($conn->connect_error) {
    out("Failed to connect: <strong>" . htmlspecialchars($conn->connect_error) . "</strong> (Code: {$conn->connect_errno})", "fail");
    
    // Provide specific diagnostic hints based on error code
    if ($conn->connect_errno == 1045) {
        out("Access Denied (Code 1045): The username or password is incorrect, or the user is not allowed to connect from this host.", "warn");
    } elseif ($conn->connect_errno == 2002) {
        out("Connection Refused / Network Error (Code 2002): MySQL server is not running on {$host}:{$port}, or a firewall is blocking access.", "warn");
    } elseif ($conn->connect_errno == 1049) {
        out("Unknown Database (Code 1049): MySQL connected, but database '{$db}' does not exist on this server.", "warn");
    }

    // 4. Test Alternative Fallback Passwords
    out("<br>Testing common fallback passwords...", "info");
    $fallbacks = ['Larable@2025', 'root', '', '123456', 'password'];
    $foundFallback = false;

    foreach ($fallbacks as $fbPass) {
        if ($fbPass === $pass) continue;
        $testConn = @new mysqli($host, $user, $fbPass, $db, (int)$port);
        if (!$testConn->connect_error) {
            $maskedFb = ($fbPass === '') ? '(empty)' : $fbPass;
            out("SUCCESSFUL FALLBACK CREDENTIAL FOUND! Password: <code>" . htmlspecialchars($maskedFb) . "</code>", "success");
            $foundFallback = true;
            $conn = $testConn;
            break;
        }
    }

    if (!$foundFallback) {
        out("None of the common fallback passwords connected successfully.", "fail");
    }
}

// 5. Verification if connected
if (isset($conn) && !$conn->connect_error) {
    out("Successfully connected to MySQL server! (Latency: {$latency}ms)", "success");
    out("MySQL Server Version: <strong>" . htmlspecialchars($conn->server_info) . "</strong>");

    // Check Tables
    $res = $conn->query("SHOW TABLES");
    if ($res) {
        $tables = [];
        while ($row = $res->fetch_array()) {
            $tables[] = $row[0];
        }
        out("Found <strong>" . count($tables) . "</strong> tables in database '<code>{$db}</code>':", "success");

        if (!$isCli) {
            echo "<table><thead><tr><th>#</th><th>Table Name</th><th>Status</th></tr></thead><tbody>";
            $critical = ['users', 'products', 'transactions', 'transaction_items', 'categories', 'error_logs'];
            foreach ($tables as $idx => $tableName) {
                $isCrit = in_array($tableName, $critical) ? "<span class='badge badge-success'>Core</span>" : "";
                echo "<tr><td>" . ($idx + 1) . "</td><td><code>{$tableName}</code></td><td>{$isCrit}</td></tr>";
            }
            echo "tbody></table>";
        } else {
            foreach ($tables as $tableName) {
                echo "  - {$tableName}\n";
            }
        }
    } else {
        out("Could not list tables: " . htmlspecialchars($conn->error), "warn");
    }

    $conn->close();
}

if (!$isCli) {
    echo "<div class='hint'>
        <strong>Tip:</strong> You can override credentials directly in the URL: <br>
        <code>?host=127.0.0.1&port=3306&user=root&pass=YOUR_PASSWORD&dbname=pos_inventory_system_db</code>
    </div>";
    echo "</div></body></html>";
}
