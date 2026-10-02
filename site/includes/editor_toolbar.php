<?php
/**
 * Logic Palette landing — editor toolbar (rendered only for a logged-in admin).
 *
 * Expects: $lang, $other, $t, $counter, $csrf.
 *
 * @author STM Webcode Systems (stm-project.ru)
 * @copyright 2026
 */

declare(strict_types=1);
?>
<div class="ed-bar" role="toolbar" aria-label="Панель редактора">
  <div class="ed-bar__inner">
    <div class="ed-bar__group">
      <span class="ed-bar__badge" title="Кликните по любому тексту на странице, чтобы изменить его. ⌘/Ctrl + клик — перейти по ссылке.">✎ Редактор</span>
      <a class="ed-bar__lang" href="?lang=<?= e($other) ?>" title="Тексты правятся отдельно для каждого языка">Правка: <b><?= e(strtoupper($lang)) ?></b> <span>→ <?= e(strtoupper($other)) ?></span></a>
    </div>

    <form class="ed-bar__group ed-counter" data-ed="counter" novalidate>
      <span class="ed-bar__label">Счётчик</span>
      <input class="ed-input ed-input--num" type="number" name="base" min="0" max="10000000" step="1"
             value="<?= (int)$counter['base'] ?>" aria-label="Стартовое число" title="Стартовое число (накрутка)">
      <span class="ed-counter__sum">+ <b data-ed="real"><?= (int)$counter['real'] ?></b> реальных = <b data-ed="total"><?= e(formatCount($counter['total'], 'ru')) ?></b></span>
      <label class="ed-check" title="Показывать счётчик посетителям">
        <input type="checkbox" name="enabled" value="1"<?= $counter['enabled'] ? ' checked' : '' ?>> Показывать
      </label>
      <button class="ed-btn" type="submit">Применить</button>
    </form>

    <div class="ed-bar__group ed-bar__group--end">
      <button class="ed-btn ed-btn--primary" type="button" data-ed="save" disabled>Сохранить</button>
      <button class="ed-btn" type="button" data-ed="seo-toggle" aria-expanded="false" aria-controls="ed-seo">SEO</button>
      <a class="ed-btn" href="admin.php">Статистика</a>
      <form method="post" action="admin.php" class="ed-bar__logout">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="next" value="site">
        <button class="ed-btn ed-btn--ghost" name="action" value="logout">Выйти</button>
      </form>
    </div>
  </div>

  <form class="ed-seo" id="ed-seo" data-ed="seo" hidden>
    <div class="ed-seo__inner">
      <label>Заголовок страницы (title), <?= e(strtoupper($lang)) ?>
        <input class="ed-input" name="meta_title" maxlength="200" value="<?= e($t['meta_title']) ?>">
      </label>
      <label>Описание (description), <?= e(strtoupper($lang)) ?>
        <textarea class="ed-input" name="meta_desc" rows="2" maxlength="400"><?= e($t['meta_desc']) ?></textarea>
      </label>
      <div class="ed-seo__actions">
        <button class="ed-btn ed-btn--primary" type="submit">Сохранить SEO</button>
        <span class="ed-seo__hint">Пустое поле — вернуть исходный текст.</span>
      </div>
    </div>
  </form>
</div>
<div class="ed-toast" role="status" aria-live="polite" hidden></div>
