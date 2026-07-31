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
        if (file_exists($staticFilePath = $sitePath . $uri) && !is_dir($staticFilePath)) {
            return $staticFilePath;
        }

        if (file_exists($staticFilePath = $sitePath . '/dist' . $uri) && !is_dir($staticFilePath)) {
            return $staticFilePath;
        }

        return false;
    }

    /**
     * Get the fully qualified path to the script or front controller.
     */
    public function frontControllerPath(string $sitePath, string $siteName, string $uri): ?string
    {
        if (str_starts_with($uri, '/api/')) {
            $apiFile = $sitePath . $uri;
            if (file_exists($apiFile) && !is_dir($apiFile)) {
                return $apiFile;
            }
        }

        if (file_exists($sitePath . '/index.php')) {
            return $sitePath . '/index.php';
        }

        if (file_exists($sitePath . '/dist/index.html')) {
            return $sitePath . '/dist/index.html';
        }

        if (file_exists($sitePath . '/index.html')) {
            return $sitePath . '/index.html';
        }

        return null;
    }
}
