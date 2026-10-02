<?php
/**
 * Local dev router: php -S 127.0.0.1:8090 router.php
 *
 * @author STM Webcode Systems (stm-project.ru)
 * @copyright 2026
 */

declare(strict_types=1);

$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');

if (preg_match('~^/(includes|data)/|/\.|\.(env|log|db|sqlite|md)$~i', $path)) {
    http_response_code(403);
    exit('Forbidden');
}

if (preg_match('~^/admin/?$~', $path)) {
    header('Location: /admin.php', true, 302);
    exit;
}

if (preg_match('~^/downloads/[\w.-]+\.(pkg|dmg)$~', $path) && is_file(__DIR__ . $path)) {
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($path) . '"');
    header('Content-Length: ' . filesize(__DIR__ . $path));
    readfile(__DIR__ . $path);
    exit;
}

if ($path !== '/' && is_file(__DIR__ . $path)) {
    return false;
}

require __DIR__ . '/index.php';
