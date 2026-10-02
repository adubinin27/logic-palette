<?php
/**
 * Logic Palette landing — editor API for images: replace/reset slot images, add/replace/delete/move screenshots,
 * toggle full-width screenshot previews.
 *
 * POST multipart/form-data: action, lang, slot | id, dir, wide, image (file). Header X-CSRF-Token.
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

if (!$_POST && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > iniBytes('post_max_size')) {
    reply(413, ['ok' => false, 'error' => 'Файл слишком большой для сервера.']);
}

requireEditorApi('media', 60);

$action = (string)($_POST['action'] ?? '');
$lang = in_array($_POST['lang'] ?? '', SUPPORTED_LANGS, true) ? $_POST['lang'] : 'ru';

$slotOf = function (): string {
    $slot = (string)($_POST['slot'] ?? '');
    if (!isset(IMG_SLOTS[$slot])) {
        throw new DomainException('Неизвестная картинка.');
    }
    return $slot;
};
$rowOf = function (): array {
    $row = galleryRow((int)($_POST['id'] ?? 0));
    if (!$row) {
        throw new DomainException('Скриншот не найден — обновите страницу.');
    }
    return $row;
};
$item = fn(array $row) => galleryView($row, $lang, loadTexts($lang));

try {
    switch ($action) {
        case 'slot_upload':
            $slot = $slotOf();
            $img = storeUpload($_FILES['image'] ?? null);
            setImageSlot($slot, $img);
            audit('media', "slot $slot: {$img['file']} {$img['w']}x{$img['h']}");
            reply(200, ['ok' => true, 'image' => imageSlot($slot)]);

        case 'slot_reset':
            $slot = $slotOf();
            resetImageSlot($slot);
            audit('media', "slot $slot: reset");
            reply(200, ['ok' => true, 'image' => imageSlot($slot)]);

        case 'gallery_add':
            ensureGallerySeeded();
            $img = storeUpload($_FILES['image'] ?? null);
            $row = galleryAdd($img);
            audit('media', "gallery add #{$row['id']}: {$img['file']}");
            reply(200, ['ok' => true, 'item' => $item($row)]);

        case 'gallery_replace':
            $row = $rowOf();
            $img = storeUpload($_FILES['image'] ?? null);
            $row = galleryReplace($row, $img);
            audit('media', "gallery replace #{$row['id']}: {$img['file']}");
            reply(200, ['ok' => true, 'item' => $item($row)]);

        case 'gallery_delete':
            $row = $rowOf();
            galleryDelete($row);
            audit('media', "gallery delete #{$row['id']}: {$row['file']}");
            reply(200, ['ok' => true]);

        case 'gallery_move':
            $row = $rowOf();
            $dir = (int)($_POST['dir'] ?? 0) < 0 ? -1 : 1;
            galleryMove($row, $dir);
            reply(200, ['ok' => true]);

        case 'gallery_wide':
            $row = gallerySetWide($rowOf(), ($_POST['wide'] ?? '') === '1');
            audit('media', "gallery wide #{$row['id']}: {$row['wide']}");
            reply(200, ['ok' => true, 'item' => $item($row)]);

        default:
            reply(400, ['ok' => false, 'error' => 'Неизвестное действие.']);
    }
} catch (DomainException $e) {
    reply(422, ['ok' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('media.php: ' . $e->getMessage());
    reply(500, ['ok' => false, 'error' => 'Не удалось обработать картинку, попробуйте ещё раз.']);
}
