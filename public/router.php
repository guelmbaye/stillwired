<?php

declare(strict_types=1);

// Health check for a loopback-only, synthetic development server.
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if ($path === '/healthz') {
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    echo 'STILLWIRED_HTTP_READY';
    return true;
}

if ($path !== '/' && is_string($path) && is_file(__DIR__.$path)) {
    return false;
}

require __DIR__.'/index.php';
