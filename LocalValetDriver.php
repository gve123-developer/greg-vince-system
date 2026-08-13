<?php

if (!class_exists('Valet\Drivers\ValetDriver') && file_exists('C:/Program Files/Herd/resources/app.asar.unpacked/resources/valet/cli/Valet/Drivers/ValetDriver.php')) {
    require_once 'C:/Program Files/Herd/resources/app.asar.unpacked/resources/valet/cli/Valet/Drivers/ValetDriver.php';
}

use Valet\Drivers\ValetDriver;

class LocalValetDriver extends ValetDriver
{
    /**
     * Determine if the driver serves the request.
     */
    public function serves(string $sitePath, string $siteName, string $uri): bool
    {
        return true;
    }

    /**
     * Determine if the incoming request is for a static file.
     */
    public function isStaticFile(string $sitePath, string $siteName, string $uri)
    {
        $candidates = [
            $sitePath . '/dist' . $uri,
            $sitePath . '/public' . $uri,
            $sitePath . $uri,
        ];

        foreach ($candidates as $file) {
            if (file_exists($file) && !is_dir($file)) {
                return $file;
            }
        }

        return false;
    }

    /**
     * Get the fully resolved path to the application's front controller.
     */
    public function frontControllerPath(string $sitePath, string $siteName, string $uri): string
    {
        $this->loadEnvironment($sitePath);

        // 1. Handle API endpoints
        if (str_starts_with($uri, '/api')) {
            if (file_exists($file = $sitePath . $uri) && !is_dir($file)) {
                return $file;
            }
            if (file_exists($file = $sitePath . $uri . '.php')) {
                return $file;
            }
        }

        // 2. Direct PHP files anywhere
        if (str_ends_with($uri, '.php')) {
            if (file_exists($file = $sitePath . $uri) && !is_dir($file)) {
                return $file;
            }
        }

        // 3. Serve React SPA dist/index.html
        if (file_exists($distIndex = $sitePath . '/dist/index.html')) {
            $_SERVER['SCRIPT_FILENAME'] = $distIndex;
            return $distIndex;
        }

        if (file_exists($rootIndex = $sitePath . '/index.html')) {
            $_SERVER['SCRIPT_FILENAME'] = $rootIndex;
            return $rootIndex;
        }

        return $sitePath . '/index.html';
    }

    /**
     * Load environment variables from .env file.
     */
    protected function loadEnvironment(string $sitePath): void
    {
        $envFile = $sitePath . '/.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }
                if (str_contains($line, '=')) {
                    list($name, $value) = explode('=', $line, 2);
                    $name = trim($name);
                    $value = trim($value, " \t\n\r\0\x0B\"'");
                    putenv("{$name}={$value}");
                    $_ENV[$name] = $value;
                    $_SERVER[$name] = $value;
                }
            }
        }
    }
}
