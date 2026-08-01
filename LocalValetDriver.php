<?php

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
        if (str_ends_with($uri, '.php')) {
            return false;
        }

        if ($uri === '/' || $uri === '' || $uri === '/index.html') {
            if (file_exists($sitePath . '/dist/index.html')) {
                return $sitePath . '/dist/index.html';
            }
        }

        if (file_exists($staticFilePath = $sitePath . '/dist' . $uri) && !is_dir($staticFilePath)) {
            return $staticFilePath;
        }

        if (file_exists($staticFilePath = $sitePath . $uri) && !is_dir($staticFilePath)) {
            return $staticFilePath;
        }

        return false;
    }

    /**
     * Get the fully qualified path to the script or front controller.
     */
    public function frontControllerPath(string $sitePath, string $siteName, string $uri): ?string
    {
        if (file_exists($sitePath . $uri) && str_ends_with($uri, '.php') && !is_dir($sitePath . $uri)) {
            return $sitePath . $uri;
        }

        if (file_exists($sitePath . '/index.php')) {
            return $sitePath . '/index.php';
        }

        return null;
    }
}

