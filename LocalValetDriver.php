<?php

class LocalValetDriver extends ValetDriver
{
    /**
     * Determine if the driver serves the request.
     */
    public function serves(string $sitePath, string $siteName, string $uri): bool
    {
        return file_exists($sitePath . '/index.php');
    }

    /**
     * Determine if the incoming request is for a static file.
     */
    public function isStaticFile(string $sitePath, string $siteName, string $uri)
    {
        if ($uri === '/') {
            if (file_exists($sitePath . '/dist/index.html')) {
                return $sitePath . '/dist/index.html';
            }
            if (file_exists($sitePath . '/index.html')) {
                return $sitePath . '/index.html';
            }
        }

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

        if (!str_starts_with($uri, '/api') && !pathinfo($uri, PATHINFO_EXTENSION)) {
            if (file_exists($sitePath . '/dist/index.html')) {
                return $sitePath . '/dist/index.html';
            }
            if (file_exists($sitePath . '/index.html')) {
                return $sitePath . '/index.html';
            }
        }

        return false;
    }

    /**
     * Get the fully resolved path to the application's front controller.
     */
    public function frontControllerPath(string $sitePath, string $siteName, string $uri): string
    {
        return $sitePath . '/index.php';
    }
}
