<?php
/**
 * Logic Palette landing — SQLite storage: downloads, settings, texts, images, gallery, admin audit.
 *
 * @author STM Webcode Systems (stm-project.ru)
 * @copyright 2026
 */

declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo) {
        return $pdo;
    }
    if (!is_dir(DATA_DIR)) {
        mkdir(DATA_DIR, 0750, true);
    }
    $pdo = new PDO('sqlite:' . DATA_DIR . '/stats.db', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA busy_timeout = 3000');
    $pdo->exec("CREATE TABLE IF NOT EXISTS downloads (
        id INTEGER PRIMARY KEY,
        created_at TEXT NOT NULL,
        kind TEXT NOT NULL,
        version TEXT NOT NULL DEFAULT '',
        ip_hash TEXT NOT NULL DEFAULT '',
        lang TEXT NOT NULL DEFAULT '',
        referer TEXT NOT NULL DEFAULT '',
        user_agent TEXT NOT NULL DEFAULT ''
    )");
    $pdo->exec('CREATE INDEX IF NOT EXISTS downloads_created ON downloads (created_at)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT NOT NULL)');
    $pdo->exec("CREATE TABLE IF NOT EXISTS site_content (
        lang TEXT NOT NULL,
        field TEXT NOT NULL,
        content TEXT NOT NULL,
        updated_at TEXT NOT NULL,
        PRIMARY KEY (lang, field)
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS site_images (
        slot TEXT PRIMARY KEY,
        file TEXT NOT NULL,
        w INTEGER NOT NULL,
        h INTEGER NOT NULL,
        updated_at TEXT NOT NULL
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS gallery (
        id INTEGER PRIMARY KEY,
        file TEXT NOT NULL,
        w INTEGER NOT NULL,
        h INTEGER NOT NULL,
        sort INTEGER NOT NULL,
        def_key TEXT,
        cap_ru TEXT,
        cap_en TEXT,
        wide INTEGER,
        created_at TEXT NOT NULL
    )");
    $cols = array_column($pdo->query('PRAGMA table_info(gallery)')->fetchAll(), 'name');
    if (!in_array('wide', $cols, true)) {
        $pdo->exec('ALTER TABLE gallery ADD COLUMN wide INTEGER');
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_audit (
        id INTEGER PRIMARY KEY,
        created_at TEXT NOT NULL,
        action TEXT NOT NULL,
        ip_hash TEXT NOT NULL,
        details TEXT NOT NULL DEFAULT ''
    )");
    return $pdo;
}

function setting(string $key, string $default = ''): string
{
    $st = db()->prepare('SELECT value FROM settings WHERE key = ?');
    $st->execute([$key]);
    $v = $st->fetchColumn();
    return $v === false ? $default : (string)$v;
}

function setSetting(string $key, string $value): void
{
    db()->prepare('INSERT INTO settings (key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value')
        ->execute([$key, $value]);
}

function audit(string $action, string $details = ''): void
{
    db()->prepare('INSERT INTO admin_audit (created_at, action, ip_hash, details) VALUES (?, ?, ?, ?)')
        ->execute([date('Y-m-d H:i:s'), $action, ipHash(), mb_substr($details, 0, 500)]);
}

function recordDownload(string $kind, string $version, string $lang): void
{
    $ref = (string)parse_url((string)($_SERVER['HTTP_REFERER'] ?? ''), PHP_URL_HOST);
    db()->prepare('INSERT INTO downloads (created_at, kind, version, ip_hash, lang, referer, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)')
        ->execute([
            date('Y-m-d H:i:s'), $kind, $version, ipHash(), $lang,
            mb_substr($ref, 0, 200), mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 300),
        ]);
}

function realDownloadCount(): int
{
    return (int)db()->query('SELECT COUNT(*) FROM downloads')->fetchColumn();
}

/** Number shown on the landing: manual base + real downloads. */
function publicDownloadCount(): int
{
    return max(0, (int)setting('display_base', '0')) + realDownloadCount();
}

function downloadStats(): array
{
    $db = db();
    $one = fn(string $sql, array $p = []) => (function () use ($db, $sql, $p) {
        $st = $db->prepare($sql);
        $st->execute($p);
        return (int)$st->fetchColumn();
    })();
    $today = date('Y-m-d');

    $days = [];
    for ($i = 29; $i >= 0; $i--) {
        $days[date('Y-m-d', strtotime("-$i day"))] = 0;
    }
    $st = $db->prepare('SELECT substr(created_at, 1, 10) d, COUNT(*) c FROM downloads WHERE created_at >= ? GROUP BY d');
    $st->execute([array_key_first($days)]);
    foreach ($st->fetchAll() as $r) {
        $days[$r['d']] = (int)$r['c'];
    }

    return [
        'total' => realDownloadCount(),
        'unique' => $one('SELECT COUNT(DISTINCT ip_hash) FROM downloads'),
        'pkg' => $one('SELECT COUNT(*) FROM downloads WHERE kind = ?', ['pkg']),
        'dmg' => $one('SELECT COUNT(*) FROM downloads WHERE kind = ?', ['dmg']),
        'today' => $one('SELECT COUNT(*) FROM downloads WHERE created_at >= ?', [$today]),
        'week' => $one('SELECT COUNT(*) FROM downloads WHERE created_at >= ?', [date('Y-m-d', strtotime('-6 day'))]),
        'month' => $one('SELECT COUNT(*) FROM downloads WHERE created_at >= ?', [array_key_first($days)]),
        'days' => $days,
        'versions' => $db->query('SELECT version, kind, COUNT(*) c FROM downloads GROUP BY version, kind ORDER BY version DESC, kind')->fetchAll(),
        'langs' => $db->query('SELECT lang, COUNT(*) c FROM downloads GROUP BY lang ORDER BY c DESC')->fetchAll(),
        'referers' => $db->query("SELECT referer, COUNT(*) c FROM downloads WHERE referer <> '' GROUP BY referer ORDER BY c DESC LIMIT 10")->fetchAll(),
        'recent' => $db->query('SELECT * FROM downloads ORDER BY id DESC LIMIT 30')->fetchAll(),
    ];
}
