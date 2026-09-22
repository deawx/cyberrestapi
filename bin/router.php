<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = is_string($uri) && $uri !== '' ? urldecode($uri) : '/';
$path = str_replace('\\', '/', $path);

if (
    (str_starts_with($path, '/assets/') || str_starts_with($path, '/uploads/'))
    && !str_contains($path, '..')
) {
    $file = $root . '/public' . $path;
    if (is_file($file)) {
        $mime = mime_content_type($file) ?: 'application/octet-stream';
        header('Content-Type: ' . $mime);
        readfile($file);
        exit;
    }
}

$file = $root . $path;
if ($path !== '/' && is_file($file)) {
    return false;
}

require $root . '/index.php';
