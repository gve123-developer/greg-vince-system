<?php

use Valet\Drivers\ValetDriver;

class LocalValetDriver extends ValetDriver
{
    /**
     * Determine if the driver serves the request.
     */
    public function serves($sitePath, $siteName, $uri)
    {
        return true;
    }

    /**
     * Determine if the incoming request is for a static file.
     */
    public function isStaticFile($sitePath, $siteName, $uri)
    {
        if (str_ends_with($uri, '.php')) {
            return false;
        }

        if ($uri === '/' || $uri === '' || $uri === '/index.html') {
            if (file_exists($sitePath . '/dist/index.html')) {
                return $sitePath . '/dist/index.html';
            }
            if (file_exists($sitePath . '/index.html')) {
                return $sitePath . '/index.html';
            }
        }

        if (file_exists($staticFilePath = $sitePath . '/dist' . $uri) && !is_dir($staticFilePath)) {
            return $staticFilePath;
        }

        if (file_exists($staticFilePath = $sitePath . '/public' . $uri) && !is_dir($staticFilePath)) {
            return $staticFilePath;
        }

        if (file_exists($staticFilePath = $sitePath . $uri) && !is_dir($staticFilePath)) {
            return $staticFilePath;
        }

        return false;
    }

    /**
     * Serve static files safely with proper MIME types.
     */
    public function serveStaticFile($staticFilePath, $sitePath, $siteName, $uri)
    {
        $mimeTypes = [
            'json'  => 'application/json',
            'js'    => 'application/javascript',
            'css'   => 'text/css',
            'html'  => 'text/html',
            'png'   => 'image/png',
            'jpg'   => 'image/jpeg',
            'jpeg'  => 'image/jpeg',
            'gif'   => 'image/gif',
            'svg'   => 'image/svg+xml',
            'ico'   => 'image/x-icon',
            'woff2' => 'font/woff2',
            'woff'  => 'font/woff',
            'ttf'   => 'font/ttf',
            'map'   => 'application/json'
        ];

        $ext = strtolower(pathinfo($staticFilePath, PATHINFO_EXTENSION));
        $type = $mimeTypes[$ext] ?? 'text/plain';

        header('Content-Type: ' . $type);
        readfile($staticFilePath);
        exit;
    }

    /**
     * Get the fully qualified path to the script or front controller.
     */
    public function frontControllerPath($sitePath, $siteName, $uri)
    {
        if (file_exists($sitePath . $uri) && str_ends_with($uri, '.php') && !is_dir($sitePath . $uri)) {
            return $sitePath . $uri;
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



