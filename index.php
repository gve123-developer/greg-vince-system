<?php
// Auto-load .env
if (!getenv('DB_HOST') && !getenv('DATABASE_URL') && !getenv('MYSQL_URL')) {
    $envPath = __DIR__ . '/.env';
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

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));

// 1. Handle API routing
if (str_starts_with($uri, '/api')) {
    $file = __DIR__ . $uri;
    if (file_exists($file) && !is_dir($file)) {
        require $file;
        exit;
    }
    if (file_exists($file . '.php')) {
        require $file . '.php';
        exit;
    }
}

// 2. Direct PHP file execution
if (str_ends_with($uri, '.php')) {
    $file = __DIR__ . $uri;
    if (file_exists($file) && !is_dir($file)) {
        require $file;
        exit;
    }
}

// 3. Serve Static Files (JS, CSS, images, json, icons, fonts) from dist/ or public/
if ($uri !== '/' && $uri !== '') {
    $candidates = [
        __DIR__ . '/dist' . $uri,
        __DIR__ . '/public' . $uri,
        __DIR__ . $uri,
    ];

    foreach ($candidates as $file) {
        if (file_exists($file) && !is_dir($file)) {
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            $mimeTypes = [
                'js'    => 'application/javascript; charset=UTF-8',
                'mjs'   => 'application/javascript; charset=UTF-8',
                'css'   => 'text/css; charset=UTF-8',
                'png'   => 'image/png',
                'jpg'   => 'image/jpeg',
                'jpeg'  => 'image/jpeg',
                'gif'   => 'image/gif',
                'svg'   => 'image/svg+xml',
                'ico'   => 'image/x-icon',
                'json'  => 'application/json; charset=UTF-8',
                'woff'  => 'font/woff',
                'woff2' => 'font/woff2',
                'ttf'   => 'font/ttf',
                'webp'  => 'image/webp',
            ];
            $contentType = $mimeTypes[$ext] ?? (function_exists('mime_content_type') ? mime_content_type($file) : 'application/octet-stream');
            header("Content-Type: {$contentType}");
            header("Content-Length: " . filesize($file));
            readfile($file);
            exit;
        }
    }
}

// 4. Serve React SPA dist/index.html
if (file_exists(__DIR__ . '/dist/index.html')) {
    header('Content-Type: text/html; charset=UTF-8');
    readfile(__DIR__ . '/dist/index.html');
    exit;
}

if (file_exists(__DIR__ . '/index.html')) {
    header('Content-Type: text/html; charset=UTF-8');
    readfile(__DIR__ . '/index.html');
    exit;
}

http_response_code(404);
echo "404 Not Found";
