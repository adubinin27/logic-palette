<?php
/**
 * Logic Palette landing — inline-editable texts: defaults from lang.php + overrides in site_content.
 *
 * @author STM Webcode Systems (stm-project.ru)
 * @copyright 2026
 */

declare(strict_types=1);

/** Keys (first path segment) that are never editable inline. */
const TEXT_LOCKED = ['lang_switch', 'lang_switch_title', 'hero_meta', 'downloads_label', 'lb_prev', 'lb_next', 'lb_close'];
const TEXT_MAX_LEN = 3000;

function textDefaults(string $lang): array
{
    static $all = null;
    $all ??= require __DIR__ . '/lang.php';
    return $all[$lang] ?? $all['en'];
}

function textGet(array $texts, string $path): mixed
{
    foreach (explode('.', $path) as $k) {
        if (!is_array($texts) || !array_key_exists($k, $texts)) {
            return null;
        }
        $texts = $texts[$k];
    }
    return $texts;
}

function textSet(array &$texts, string $path, string $value): void
{
    $ref = &$texts;
    foreach (explode('.', $path) as $k) {
        $ref = &$ref[$k];
    }
    $ref = $value;
}

function isEditableKey(string $lang, string $key): bool
{
    if (!preg_match('/^[a-z_]+(\.[a-z0-9_]+){0,2}$/', $key)) {
        return false;
    }
    $first = strtok($key, '.');
    if (in_array($first, TEXT_LOCKED, true) || preg_match('/^space_points\.\d+\.0$/', $key)) {
        return false;
    }
    return is_string(textGet(textDefaults($lang), $key));
}

/** Fields that keep <b> markup (install steps). */
function isHtmlKey(string $key): bool
{
    return (bool)preg_match('/^install\.\d+$/', $key);
}

function sanitizeText(string $key, string $value): string
{
    if (isHtmlKey($key)) {
        $value = str_ireplace(['<strong', '</strong>'], ['<b', '</b>'], $value);
        $value = strip_tags($value, '<b>');
        $value = preg_replace('/<b\b[^>]*>/i', '<b>', $value);
        $value = preg_replace('~<b>\s*</b>~i', '', $value);
        if (substr_count(strtolower($value), '<b>') !== substr_count(strtolower($value), '</b>')) {
            $value = strip_tags($value);
        }
    } else {
        $value = strip_tags($value);
    }
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
    $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    return mb_substr($value, 0, TEXT_MAX_LEN);
}

/** lang.php texts with saved inline edits applied. */
function loadTexts(string $lang): array
{
    $texts = textDefaults($lang);
    try {
        $st = db()->prepare('SELECT field, content FROM site_content WHERE lang = ?');
        $st->execute([$lang]);
        foreach ($st->fetchAll() as $row) {
            if (isEditableKey($lang, $row['field'])) {
                textSet($texts, $row['field'], (string)$row['content']);
            }
        }
    } catch (Throwable $e) {
        error_log('site_content unavailable: ' . $e->getMessage());
    }
    return $texts;
}

/** Saves one field; empty value or default restores the original text. Returns the text now in effect. */
function saveText(string $lang, string $key, string $value): string
{
    $default = (string)textGet(textDefaults($lang), $key);
    $value = sanitizeText($key, $value);
    if ($value === '' || $value === $default) {
        db()->prepare('DELETE FROM site_content WHERE lang = ? AND field = ?')->execute([$lang, $key]);
        return $default;
    }
    db()->prepare('INSERT INTO site_content (lang, field, content, updated_at) VALUES (?, ?, ?, ?)
        ON CONFLICT(lang, field) DO UPDATE SET content = excluded.content, updated_at = excluded.updated_at')
        ->execute([$lang, $key, $value, date('Y-m-d H:i:s')]);
    return $value;
}

function editorMode(?bool $set = null): bool
{
    static $on = false;
    if ($set !== null) {
        $on = $set;
    }
    return $on;
}

/** Inline-edit attributes for a text field (only in editor mode). */
function ed(string $key, string $placeholder = ''): string
{
    if (!editorMode()) {
        return '';
    }
    return ' data-editable data-section="text" data-field="' . e($key) . '"'
        . (isHtmlKey($key) ? ' data-html' : '')
        . ($placeholder !== '' ? ' data-placeholder="' . e($placeholder) . '"' : '')
        . ' contenteditable="true" spellcheck="true"';
}

/** Public counter view model for the current language. */
function counterView(string $lang, array $texts): array
{
    $real = realDownloadCount();
    $base = max(0, (int)setting('display_base', '0'));
    $total = $base + $real;
    return [
        'enabled' => setting('display_enabled', '1') === '1',
        'base' => $base,
        'real' => $real,
        'total' => $total,
        'number' => formatCount($total, $lang),
        'label' => pluralize($total, $texts['downloads_label'], $lang),
    ];
}
