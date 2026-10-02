<?php
/**
 * Logic Palette landing — counts a download and serves the latest installer.
 * download.php?f=pkg|dmg&l=ru|en
 *
 * @author STM Webcode Systems (stm-project.ru)
 * @copyright 2026
 */

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/db.php';

$kind = $_GET['f'] ?? 'pkg';
$file = in_array($kind, ['pkg', 'dmg'], true) ? latestDownload($kind) : null;
if (!$file) {
    http_response_code(404);
    exit('Not found');
}
$path = __DIR__ . '/' . $file['file'];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && !isBot((string)($_SERVER['HTTP_USER_AGENT'] ?? ''))) {
    try {
        $lang = in_array($_GET['l'] ?? '', SUPPORTED_LANGS, true) ? $_GET['l'] : '';
        recordDownload($kind, $file['version'], $lang);
    } catch (Throwable $e) {
        error_log('download count failed: ' . $e->getMessage());
    }
}

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($path) . '"');
header('Content-Length: ' . filesize($path));
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') {
    readfile($path);
}
