<?php
/**
 * Logic Palette landing — editor API: inline text changes, gallery captions and counter settings (JSON).
 *
 * @author STM Webcode Systems (stm-project.ru)
 * @copyright 2026
 */

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/content.php';
require __DIR__ . '/includes/media.php';
require __DIR__ . '/includes/api.php';

requireEditorApi('save', 120);

$in = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($in)) {
    reply(400, ['ok' => false, 'error' => 'Некорректный запрос.']);
}
$lang = in_array($in['lang'] ?? '', SUPPORTED_LANGS, true) ? $in['lang'] : null;
if ($lang === null) {
    reply(400, ['ok' => false, 'error' => 'Не указан язык.']);
}

try {
    $saved = [];
    $changes = is_array($in['changes'] ?? null) ? array_slice($in['changes'], 0, 200) : [];
    foreach ($changes as $c) {
        $field = (string)($c['field'] ?? '');
        if (!is_string($c['content'] ?? null)) {
            reply(422, ['ok' => false, 'error' => 'Это поле нельзя редактировать: ' . $field]);
        }
        if (preg_match('/^gallery\.(\d{1,9})$/', $field, $m)) {
            $saved[] = ['field' => $field, 'content' => saveGalleryCaption($lang, (int)$m[1], $c['content'])];
            continue;
        }
        if (!isEditableKey($lang, $field)) {
            reply(422, ['ok' => false, 'error' => 'Это поле нельзя редактировать: ' . $field]);
        }
        $saved[] = ['field' => $field, 'content' => saveText($lang, $field, $c['content'])];
    }
    if ($saved) {
        audit('content', $lang . ': ' . implode(', ', array_column($saved, 'field')));
    }

    if (is_array($in['counter'] ?? null)) {
        $base = max(0, min(10_000_000, (int)($in['counter']['base'] ?? 0)));
        $enabled = !empty($in['counter']['enabled']) ? '1' : '0';
        setSetting('display_base', (string)$base);
        setSetting('display_enabled', $enabled);
        audit('settings', "base=$base enabled=$enabled");
    }

    reply(200, ['ok' => true, 'saved' => $saved, 'counter' => counterView($lang, loadTexts($lang))]);
} catch (DomainException $e) {
    reply(422, ['ok' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('save.php: ' . $e->getMessage());
    reply(500, ['ok' => false, 'error' => 'Не удалось сохранить, попробуйте ещё раз.']);
}
