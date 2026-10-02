<?php
/**
 * Logic Palette landing — shared guard for editor JSON endpoints (POST + admin session + CSRF header).
 *
 * @author STM Webcode Systems (stm-project.ru)
 * @copyright 2026
 */

declare(strict_types=1);

function reply(int $status, array $body): never
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

function requireEditorApi(string $bucket, int $maxPerMinute): void
{
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    header('X-Robots-Tag: noindex, nofollow');

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        reply(405, ['ok' => false, 'error' => 'Method not allowed']);
    }
    startSecureSession();
    if (empty($_SESSION['admin'])) {
        reply(401, ['ok' => false, 'error' => 'Сессия истекла — войдите снова.']);
    }
    if (!csrfValid($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
        reply(403, ['ok' => false, 'error' => 'Сессия устарела — обновите страницу.']);
    }
    if (!rateLimitHit($bucket, $maxPerMinute, 60)) {
        reply(429, ['ok' => false, 'error' => 'Слишком много запросов, подождите минуту.']);
    }
}
