<?php
/**
 * Logic Palette landing — language detection, helpers, security headers.
 *
 * @author STM Webcode Systems (stm-project.ru)
 * @copyright 2026
 */

declare(strict_types=1);

const SUPPORTED_LANGS = ['ru', 'en'];
const DATA_DIR = __DIR__ . '/../data';

/** KEY=VALUE pairs from site/.env (not web-accessible). */
function env(string $key, string $default = ''): string
{
    static $vars = null;
    if ($vars === null) {
        $vars = [];
        $file = __DIR__ . '/../.env';
        if (is_readable($file)) {
            foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                    continue;
                }
                [$k, $v] = array_map('trim', explode('=', $line, 2));
                $vars[$k] = trim($v, "\"'");
            }
        }
    }
    return $vars[$key] ?? $default;
}

date_default_timezone_set(env('APP_TIMEZONE', 'Asia/Yekaterinburg'));

function clientIp(): string
{
    return (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

function ipHash(): string
{
    return substr(hash('sha256', clientIp() . '|' . env('APP_SECRET', 'logic-palette')), 0, 16);
}

function isBot(string $ua): bool
{
    return $ua === '' || (bool)preg_match('/bot|crawl|spider|slurp|preview|fetch|curl|wget|python|httpclient|headless/i', $ua);
}

function startSecureSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name('lpadmin');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => isHttps(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    session_start();
    $idle = (int)env('ADMIN_SESSION_IDLE', '7200');
    if (isset($_SESSION['last_seen']) && time() - $_SESSION['last_seen'] > $idle) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['last_seen'] = time();
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrfValid(?string $token): bool
{
    return is_string($token) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
}

/** File-based limiter: true if the action is still allowed for this IP. */
function rateLimitHit(string $action, int $max, int $windowSec): bool
{
    $dir = DATA_DIR . '/rate';
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }
    $file = $dir . '/' . $action . '_' . ipHash() . '.json';
    $now = time();
    $hits = is_file($file) ? (json_decode((string)file_get_contents($file), true) ?: []) : [];
    $hits = array_values(array_filter($hits, fn($t) => $t > $now - $windowSec));
    if (count($hits) >= $max) {
        return false;
    }
    $hits[] = $now;
    file_put_contents($file, json_encode($hits), LOCK_EX);
    return true;
}

function rateLimitReset(string $action): void
{
    @unlink(DATA_DIR . '/rate/' . $action . '_' . ipHash() . '.json');
}

function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function formatCount(int $n, string $lang): string
{
    return $lang === 'ru' ? number_format($n, 0, '', "\u{202F}") : number_format($n);
}

/** $forms: ru — [one, few, many]; en — [one, other]. */
function pluralize(int $n, array $forms, string $lang): string
{
    if ($lang !== 'ru') {
        return $n === 1 ? $forms[0] : $forms[1];
    }
    $mod10 = $n % 10;
    $mod100 = $n % 100;
    if ($mod10 === 1 && $mod100 !== 11) {
        return $forms[0];
    }
    if ($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 12 || $mod100 > 14)) {
        return $forms[1];
    }
    return $forms[2];
}

function isHttps(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');
}

/** ?lang= (remembered in a cookie) → cookie → browser/system language → English. */
function detectLang(): string
{
    $q = $_GET['lang'] ?? '';
    if (in_array($q, SUPPORTED_LANGS, true)) {
        setcookie('lang', $q, [
            'expires' => time() + 31536000,
            'path' => '/',
            'secure' => isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        return $q;
    }
    $c = $_COOKIE['lang'] ?? '';
    if (in_array($c, SUPPORTED_LANGS, true)) {
        return $c;
    }
    foreach (explode(',', (string)($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '')) as $part) {
        $code = strtolower(substr(trim($part), 0, 2));
        if (in_array($code, ['ru', 'uk', 'be', 'kk'], true)) {
            return 'ru';
        }
        if ($code !== '' && $code !== '*') {
            return 'en';
        }
    }
    return 'en';
}

function sendSecurityHeaders(): void
{
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; "
        . "font-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header('Vary: Accept-Language, Cookie');
    if (isHttps()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

/** Latest installer of the given type in /downloads: ['file', 'version', 'kb']. */
function latestDownload(string $ext): ?array
{
    $found = [];
    foreach (glob(__DIR__ . '/../downloads/Logic-Palette-*.' . $ext) ?: [] as $f) {
        if (preg_match('/Logic-Palette-([\d.]+)\.' . preg_quote($ext, '/') . '$/', $f, $m)) {
            $found[$m[1]] = $f;
        }
    }
    if (!$found) {
        return null;
    }
    uksort($found, fn($a, $b) => version_compare((string)$b, (string)$a));
    $version = (string)array_key_first($found);
    return [
        'file' => 'downloads/' . basename($found[$version]),
        'version' => $version,
        'kb' => (int)round(filesize($found[$version]) / 1024),
    ];
}
