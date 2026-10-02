<?php
/**
 * Logic Palette landing — replaceable images (slots) and the screenshot gallery.
 *
 * Uploads are decoded and re-encoded with GD (strips metadata and any non-image payload)
 * and stored under assets/img/uploads with random names. Original assets are never deleted.
 *
 * @author STM Webcode Systems (stm-project.ru)
 * @copyright 2026
 */

declare(strict_types=1);

const IMG_DIR = __DIR__ . '/../assets/img';
const IMG_URL = 'assets/img/';
const UPLOAD_SUBDIR = 'uploads';
const UPLOAD_MAX_BYTES = 15 * 1024 * 1024;
const UPLOAD_MAX_SIDE = 3200;
const UPLOAD_MAX_PIXELS = 40_000_000;
const GALLERY_MAX = 40;
/** Screenshots at least this many times wider than tall span the whole gallery row unless overridden. */
const GALLERY_WIDE_RATIO = 2.5;

const IMG_SLOTS = [
    'hero' => ['file' => 'hero.jpg', 'w' => 846, 'h' => 406],
    'about' => ['file' => 'about.png', 'w' => 189, 'h' => 183],
    'macbook' => [
        'file' => 'macbook-1440.jpg', 'w' => 1440, 'h' => 894, 'full' => 'macbook-2880.jpg',
        'srcset' => 'assets/img/macbook-1440.jpg 1440w, assets/img/macbook-2880.jpg 2880w',
    ],
];

function iniBytes(string $key): int
{
    $v = trim((string)ini_get($key));
    $n = (int)$v;
    return match (strtolower(substr($v, -1))) {
        'g' => $n * 1024 ** 3,
        'm' => $n * 1024 ** 2,
        'k' => $n * 1024,
        default => $n,
    };
}

/** Largest upload the server accepts right now (min of our cap and php.ini limits). */
function uploadLimit(): int
{
    $limits = [UPLOAD_MAX_BYTES];
    foreach (['upload_max_filesize', 'post_max_size'] as $key) {
        $b = iniBytes($key);
        if ($b > 0) {
            $limits[] = $key === 'post_max_size' ? max(0, $b - 65536) : $b;
        }
    }
    return min($limits);
}

function imgVersion(string $file): string
{
    $path = IMG_DIR . '/' . $file;
    return IMG_URL . $file . (is_file($path) ? '?v=' . filemtime($path) : '');
}

/** View model of an image slot: custom upload if set, otherwise the bundled default. */
function imageSlot(string $slot): array
{
    $def = IMG_SLOTS[$slot];
    $row = null;
    try {
        $st = db()->prepare('SELECT file, w, h FROM site_images WHERE slot = ?');
        $st->execute([$slot]);
        $row = $st->fetch() ?: null;
    } catch (Throwable $e) {
        error_log('site_images unavailable: ' . $e->getMessage());
    }
    if ($row && is_file(IMG_DIR . '/' . $row['file'])) {
        $src = IMG_URL . $row['file'];
        return ['src' => $src, 'full' => $src, 'srcset' => '', 'w' => (int)$row['w'], 'h' => (int)$row['h'], 'custom' => true];
    }
    return [
        'src' => imgVersion($def['file']),
        'full' => imgVersion($def['full'] ?? $def['file']),
        'srcset' => $def['srcset'] ?? '',
        'w' => $def['w'],
        'h' => $def['h'],
        'custom' => false,
    ];
}

/** Editor attributes for a replaceable image container. */
function edImg(string $slot, bool $custom): string
{
    if (!editorMode()) {
        return '';
    }
    return ' data-img-slot="' . e($slot) . '"' . ($custom ? ' data-img-custom' : '');
}

function setImageSlot(string $slot, array $img): void
{
    $old = db()->prepare('SELECT file FROM site_images WHERE slot = ?');
    $old->execute([$slot]);
    $oldFile = $old->fetchColumn();
    db()->prepare('INSERT INTO site_images (slot, file, w, h, updated_at) VALUES (?, ?, ?, ?, ?)
        ON CONFLICT(slot) DO UPDATE SET file = excluded.file, w = excluded.w, h = excluded.h, updated_at = excluded.updated_at')
        ->execute([$slot, $img['file'], $img['w'], $img['h'], date('Y-m-d H:i:s')]);
    if ($oldFile) {
        deleteUploadIfUnused((string)$oldFile);
    }
}

function resetImageSlot(string $slot): void
{
    $old = db()->prepare('SELECT file FROM site_images WHERE slot = ?');
    $old->execute([$slot]);
    $oldFile = $old->fetchColumn();
    db()->prepare('DELETE FROM site_images WHERE slot = ?')->execute([$slot]);
    if ($oldFile) {
        deleteUploadIfUnused((string)$oldFile);
    }
}

/** First use: copy the bundled screenshots (lang.php order) into the gallery table. */
function ensureGallerySeeded(): void
{
    if (setting('gallery_seeded') === '1') {
        return;
    }
    $db = db();
    $db->beginTransaction();
    try {
        if (setting('gallery_seeded') !== '1') {
            $ins = $db->prepare('INSERT INTO gallery (file, w, h, sort, def_key, created_at) VALUES (?, ?, ?, ?, ?, ?)');
            $sort = 0;
            foreach (array_keys(textDefaults('ru')['screens']) as $key) {
                $file = "screens/$key.png";
                $size = @getimagesize(IMG_DIR . '/' . $file) ?: [0, 0];
                $ins->execute([$file, (int)$size[0], (int)$size[1], ++$sort, $key, date('Y-m-d H:i:s')]);
            }
            setSetting('gallery_seeded', '1');
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

function galleryRows(): array
{
    ensureGallerySeeded();
    return db()->query('SELECT * FROM gallery ORDER BY sort, id')->fetchAll();
}

function galleryRow(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM gallery WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

/** Caption field key: bundled screenshots keep their lang.php text key, uploads use gallery.<id>. */
function galleryField(array $row): string
{
    return $row['def_key'] ? 'screens.' . $row['def_key'] : 'gallery.' . $row['id'];
}

function galleryView(array $row, string $lang, array $texts): array
{
    $caption = $row['def_key']
        ? (string)($texts['screens'][$row['def_key']] ?? '')
        : (string)($row['cap_' . $lang] ?? '');
    return [
        'id' => (int)$row['id'],
        'src' => str_starts_with($row['file'], UPLOAD_SUBDIR . '/') ? IMG_URL . $row['file'] : imgVersion($row['file']),
        'w' => (int)$row['w'],
        'h' => (int)$row['h'],
        'caption' => $caption,
        'field' => galleryField($row),
        'wide' => galleryIsWide($row),
    ];
}

/** Explicit per-item choice (wide = 1/0) wins; NULL means auto by aspect ratio. */
function galleryIsWide(array $row): bool
{
    if (isset($row['wide'])) {
        return (int)$row['wide'] === 1;
    }
    return (int)$row['h'] > 0 && (int)$row['w'] / (int)$row['h'] >= GALLERY_WIDE_RATIO;
}

function gallerySetWide(array $row, bool $wide): array
{
    db()->prepare('UPDATE gallery SET wide = ? WHERE id = ?')->execute([$wide ? 1 : 0, $row['id']]);
    return galleryRow((int)$row['id']);
}

/** Gallery for the page; falls back to the bundled screenshots if the DB is unavailable. */
function galleryItems(string $lang, array $texts): array
{
    try {
        return array_map(fn($r) => galleryView($r, $lang, $texts), galleryRows());
    } catch (Throwable $e) {
        error_log('gallery unavailable: ' . $e->getMessage());
        $items = [];
        foreach ($texts['screens'] as $key => $caption) {
            $items[] = ['id' => 0, 'src' => imgVersion("screens/$key.png"), 'w' => 0, 'h' => 0, 'caption' => $caption, 'field' => "screens.$key", 'wide' => false];
        }
        return $items;
    }
}

function galleryAdd(array $img): array
{
    if ((int)db()->query('SELECT COUNT(*) FROM gallery')->fetchColumn() >= GALLERY_MAX) {
        deleteUploadIfUnused($img['file']);
        throw new DomainException('В галерее уже ' . GALLERY_MAX . ' скриншотов — удалите лишние.');
    }
    $sort = (int)db()->query('SELECT COALESCE(MAX(sort), 0) FROM gallery')->fetchColumn() + 1;
    db()->prepare('INSERT INTO gallery (file, w, h, sort, created_at) VALUES (?, ?, ?, ?, ?)')
        ->execute([$img['file'], $img['w'], $img['h'], $sort, date('Y-m-d H:i:s')]);
    return galleryRow((int)db()->lastInsertId());
}

function galleryReplace(array $row, array $img): array
{
    db()->prepare('UPDATE gallery SET file = ?, w = ?, h = ?, wide = NULL WHERE id = ?')
        ->execute([$img['file'], $img['w'], $img['h'], $row['id']]);
    deleteUploadIfUnused($row['file']);
    return galleryRow((int)$row['id']);
}

function galleryDelete(array $row): void
{
    db()->prepare('DELETE FROM gallery WHERE id = ?')->execute([$row['id']]);
    deleteUploadIfUnused($row['file']);
}

/** Swaps the item with its neighbour; $dir is -1 (left) or 1 (right). */
function galleryMove(array $row, int $dir): void
{
    $rows = galleryRows();
    $ids = array_map(fn($r) => (int)$r['id'], $rows);
    $i = array_search((int)$row['id'], $ids, true);
    $j = $i + $dir;
    if ($i === false || $j < 0 || $j >= count($ids)) {
        return;
    }
    [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
    $db = db();
    $db->beginTransaction();
    $st = $db->prepare('UPDATE gallery SET sort = ? WHERE id = ?');
    foreach ($ids as $n => $id) {
        $st->execute([$n + 1, $id]);
    }
    $db->commit();
}

function saveGalleryCaption(string $lang, int $id, string $value): string
{
    $value = sanitizeText('gallery', $value);
    $col = $lang === 'en' ? 'cap_en' : 'cap_ru';
    $st = db()->prepare("UPDATE gallery SET $col = ? WHERE id = ? AND def_key IS NULL");
    $st->execute([$value === '' ? null : $value, $id]);
    if ($st->rowCount() === 0) {
        throw new DomainException('Скриншот не найден — обновите страницу.');
    }
    return $value;
}

function deleteUploadIfUnused(string $file): void
{
    if (!preg_match('~^' . UPLOAD_SUBDIR . '/[a-f0-9]{20}\.(jpg|png|webp)$~', $file)) {
        return;
    }
    $db = db();
    $used = (int)$db->query('SELECT
        (SELECT COUNT(*) FROM site_images WHERE file = ' . $db->quote($file) . ') +
        (SELECT COUNT(*) FROM gallery WHERE file = ' . $db->quote($file) . ')')->fetchColumn();
    if ($used === 0 && is_file(IMG_DIR . '/' . $file)) {
        @unlink(IMG_DIR . '/' . $file);
    }
}

/**
 * Validates an uploaded image, re-encodes it and stores it under uploads/.
 *
 * @return array{file: string, w: int, h: int}
 * @throws DomainException with a user-facing message
 */
function storeUpload(?array $f): array
{
    $mb = fn(int $b) => rtrim(rtrim(number_format($b / 1048576, 1, '.', ''), '0'), '.') . ' МБ';
    if (!$f || !isset($f['error']) || is_array($f['error'])) {
        throw new DomainException('Файл не получен.');
    }
    if (in_array($f['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || $f['size'] > uploadLimit()) {
        throw new DomainException('Файл больше ' . $mb(uploadLimit()) . '.');
    }
    if ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
        throw new DomainException('Не удалось загрузить файл.');
    }
    $info = @getimagesize($f['tmp_name']);
    $types = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'png'];
    if (!$info || !isset($types[$info[2]])) {
        throw new DomainException('Это не картинка. Подходят JPG, PNG, WebP.');
    }
    [$w, $h] = $info;
    if ($w < 16 || $h < 16 || $w * $h > UPLOAD_MAX_PIXELS) {
        throw new DomainException('Неподходящий размер картинки: ' . $w . '×' . $h . '.');
    }

    ini_set('memory_limit', '512M');
    $im = @imagecreatefromstring((string)file_get_contents($f['tmp_name']));
    if (!$im) {
        throw new DomainException('Картинка повреждена или формат не поддерживается.');
    }
    if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $angle = match ((int)(@exif_read_data($f['tmp_name'])['Orientation'] ?? 1)) {
            3 => 180, 6 => -90, 8 => 90, default => 0,
        };
        if ($angle) {
            $im = imagerotate($im, $angle, 0);
        }
    }
    if (!imageistruecolor($im)) {
        imagepalettetotruecolor($im);
    }
    $w = imagesx($im);
    $h = imagesy($im);
    $scale = min(1, UPLOAD_MAX_SIDE / max($w, $h));
    if ($scale < 1) {
        $nw = max(1, (int)round($w * $scale));
        $nh = max(1, (int)round($h * $scale));
        $dst = imagecreatetruecolor($nw, $nh);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        imagecopyresampled($dst, $im, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($im);
        [$im, $w, $h] = [$dst, $nw, $nh];
    }
    imagesavealpha($im, true);

    $dir = IMG_DIR . '/' . UPLOAD_SUBDIR;
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('cannot create uploads dir');
    }
    $ext = $types[$info[2]];
    $file = UPLOAD_SUBDIR . '/' . bin2hex(random_bytes(10)) . '.' . $ext;
    $path = IMG_DIR . '/' . $file;
    $ok = match ($ext) {
        'jpg' => imagejpeg($im, $path, 86),
        'webp' => imagewebp($im, $path, 86),
        default => imagepng($im, $path, 7),
    };
    imagedestroy($im);
    if (!$ok) {
        @unlink($path);
        throw new RuntimeException('cannot write image');
    }
    @chmod($path, 0644);
    return ['file' => $file, 'w' => $w, 'h' => $h];
}
