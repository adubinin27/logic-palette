<?php
/**
 * Logic Palette landing — single page (inline-editable for a logged-in admin).
 *
 * @author STM Webcode Systems (stm-project.ru)
 * @copyright 2026
 */

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/content.php';
require __DIR__ . '/includes/media.php';

$editor = false;
if (isset($_COOKIE['lpadmin'])) {
    startSecureSession();
    $editor = !empty($_SESSION['admin']);
}
editorMode($editor);

$lang = detectLang();
$t = loadTexts($lang);
$other = $lang === 'ru' ? 'en' : 'ru';
$pkg = latestDownload('pkg');
$dmg = latestDownload('dmg');
$version = $pkg['version'] ?? ($dmg['version'] ?? '');
$pkgUrl = 'download.php?f=pkg&l=' . $lang;
$dmgUrl = 'download.php?f=dmg&l=' . $lang;

$counter = null;
try {
    $counter = counterView($lang, $t);
} catch (Throwable $e) {
    error_log('download counter unavailable: ' . $e->getMessage());
}
$showCounter = $counter && ($counter['enabled'] || $editor);
$csrf = $editor ? csrfToken() : '';

$heroImg = imageSlot('hero');
$aboutImg = imageSlot('about');
$macImg = imageSlot('macbook');
$gallery = galleryItems($lang, $t);

$terminalCmd = 'APP="/Applications/Logic Palette.app"
chmod +x "$APP/Contents/MacOS/LogicPalette"
xattr -cr "$APP"
codesign --force --deep --sign - "$APP"';

const GITHUB_URL = 'https://github.com/adubinin27/logic-palette';
const GITHUB_ICON = 'M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.01 8.01 0 0 0 16 8c0-4.42-3.58-8-8-8Z';

$asset = fn(string $path): string => $path . '?v=' . filemtime(__DIR__ . '/' . $path);
$host = preg_match('/^[a-z0-9.-]+(:\d{1,5})?$/i', $_SERVER['HTTP_HOST'] ?? '') ? $_SERVER['HTTP_HOST'] : 'stm-project.ru';
$baseUrl = (isHttps() ? 'https' : 'http') . '://' . $host . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/') . '/';

sendSecurityHeaders();
header('Content-Type: text/html; charset=utf-8');
header('Content-Language: ' . $lang);
if ($editor) {
    header('Cache-Control: no-store, private');
    header('X-Robots-Tag: noindex, nofollow');
}
?><!doctype html>
<html lang="<?= e($lang) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($t['meta_title']) ?></title>
  <meta name="description" content="<?= e($t['meta_desc']) ?>">
  <meta name="author" content="STM Webcode Systems, stm-project.ru">
  <meta property="og:title" content="<?= e($t['meta_title']) ?>">
  <meta property="og:description" content="<?= e($t['meta_desc']) ?>">
  <meta property="og:type" content="website">
  <meta property="og:image" content="<?= e($baseUrl . $asset('assets/img/og-image.png')) ?>">
  <meta property="og:image:width" content="1280">
  <meta property="og:image:height" content="640">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="theme-color" content="#161618">
  <link rel="alternate" hreflang="ru" href="?lang=ru">
  <link rel="alternate" hreflang="en" href="?lang=en">
  <link rel="icon" type="image/png" href="assets/img/favicon.png">
  <link rel="apple-touch-icon" href="assets/img/icon.png">
  <link rel="stylesheet" href="<?= e($asset('assets/css/style.css')) ?>">
  <?php if ($editor): ?>
    <meta name="csrf-token" content="<?= e($csrf) ?>">
    <meta name="editor-lang" content="<?= e($lang) ?>">
    <meta name="upload-max" content="<?= uploadLimit() ?>">
    <link rel="stylesheet" href="<?= e($asset('assets/css/editor.css')) ?>">
  <?php endif; ?>
</head>
<body<?= $editor ? ' class="is-editor"' : '' ?>>
  <?php if ($editor) {
      require __DIR__ . '/includes/editor_toolbar.php';
  } ?>
  <div class="spectrum" aria-hidden="true"></div>

  <header class="header">
    <div class="wrap header__inner">
      <a class="brand" href="#top">
        <img src="assets/img/icon.png" alt="" width="32" height="32">
        <span>Logic Palette</span>
      </a>
      <nav class="nav" aria-label="Main">
        <a href="#features"><span<?= ed('nav_features') ?>><?= e($t['nav_features']) ?></span></a>
        <a href="#screens"><span<?= ed('nav_screens') ?>><?= e($t['nav_screens']) ?></span></a>
        <a href="#install"><span<?= ed('nav_install') ?>><?= e($t['nav_install']) ?></span></a>
      </nav>
      <div class="header__actions">
        <a class="lang" href="?lang=<?= e($other) ?>" title="<?= e($t['lang_switch_title']) ?>" hreflang="<?= e($other) ?>"><?= e($t['lang_switch']) ?></a>
        <?php if ($pkg): ?>
          <a class="btn btn--small" href="<?= e($pkgUrl) ?>" download><span<?= ed('nav_download') ?>><?= e($t['nav_download']) ?></span></a>
        <?php endif; ?>
      </div>
    </div>
  </header>

  <main id="top">
    <section class="hero">
      <div class="wrap">
        <div class="hero__card"<?= edImg('hero', $heroImg['custom']) ?>>
          <img class="hero__img" src="<?= e($heroImg['src']) ?>" alt="<?= e($t['hero_caption']) ?>" width="<?= $heroImg['w'] ?>" height="<?= $heroImg['h'] ?>">
          <div class="hero__text">
            <span class="badge"<?= ed('hero_badge') ?>><?= e($t['hero_badge']) ?></span>
            <h1<?= ed('hero_title') ?>><?= e($t['hero_title']) ?></h1>
            <p class="lead"<?= ed('hero_lead') ?>><?= e($t['hero_lead']) ?></p>
          </div>
        </div>
        <div class="hero__cta">
          <div class="hero__buttons">
            <?php if ($pkg): ?>
              <a class="btn" href="<?= e($pkgUrl) ?>" download>
                <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M12 3a1 1 0 0 1 1 1v9.6l3.3-3.3a1 1 0 1 1 1.4 1.4l-5 5a1 1 0 0 1-1.4 0l-5-5a1 1 0 1 1 1.4-1.4l3.3 3.3V4a1 1 0 0 1 1-1Zm-8 15a1 1 0 0 1 1 1v1h14v-1a1 1 0 1 1 2 0v2a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1v-2a1 1 0 0 1 1-1Z"/></svg>
                <span<?= ed('hero_btn_pkg') ?>><?= e($t['hero_btn_pkg']) ?></span> <span class="btn__ext">.pkg</span>
              </a>
            <?php endif; ?>
            <?php if ($dmg): ?>
              <a class="btn btn--ghost" href="<?= e($dmgUrl) ?>" download><span<?= ed('hero_btn_dmg') ?>><?= e($t['hero_btn_dmg']) ?></span></a>
            <?php endif; ?>
            <?php if ($showCounter): ?>
              <p class="counter<?= $counter['enabled'] ? '' : ' counter--off' ?>" data-counter<?= $editor && !$counter['enabled'] ? ' title="Скрыт от посетителей"' : '' ?>>
                <b data-counter-num><?= e($counter['number']) ?></b> <span data-counter-label><?= e($counter['label']) ?></span>
              </p>
            <?php endif; ?>
          </div>
          <div class="hero__info">
            <?php if ($pkg): ?>
              <p class="meta"><?= e(sprintf($t['hero_meta'], $pkg['version'], $pkg['kb'])) ?></p>
            <?php endif; ?>
            <p class="meta meta--dim"<?= ed('hero_caption') ?>><?= e($t['hero_caption']) ?></p>
          </div>
        </div>
      </div>
    </section>

    <section class="section">
      <div class="wrap about">
        <div class="about__side">
          <h2<?= ed('about_title') ?>><?= e($t['about_title']) ?></h2>
          <img class="about__img" src="<?= e($aboutImg['src']) ?>" alt="Logic Palette" width="<?= $aboutImg['w'] ?>" height="<?= $aboutImg['h'] ?>" loading="lazy"<?= edImg('about', $aboutImg['custom']) ?>>
        </div>
        <div class="about__text">
          <?php foreach ($t['about_text'] as $i => $p): ?>
            <p<?= ed("about_text.$i") ?>><?= e($p) ?></p>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <section class="section section--tight">
      <div class="wrap">
        <div class="space__head">
          <h2<?= ed('space_title') ?>><?= e($t['space_title']) ?></h2>
          <p class="space__lead"<?= ed('space_lead') ?>><?= e($t['space_lead']) ?></p>
        </div>
        <figure class="space__shot"<?= edImg('macbook', $macImg['custom']) ?>>
          <a href="<?= e($macImg['full']) ?>" data-lightbox="macbook">
            <img src="<?= e($macImg['src']) ?>"<?php if ($macImg['srcset']): ?> srcset="<?= e($macImg['srcset']) ?>"
                 sizes="(max-width: 1160px) 100vw, 1120px"<?php endif; ?> width="<?= $macImg['w'] ?>" height="<?= $macImg['h'] ?>" alt="<?= e($t['space_caption']) ?>" loading="lazy">
          </a>
          <figcaption<?= ed('space_caption') ?>><?= e($t['space_caption']) ?></figcaption>
        </figure>
        <div class="space__points">
          <?php foreach ($t['space_points'] as $i => [$kind, $title, $text]): ?>
            <div class="space__point space__point--<?= e($kind) ?>">
              <h3><span<?= ed("space_points.$i.1") ?>><?= e($title) ?></span></h3>
              <p<?= ed("space_points.$i.2") ?>><?= e($text) ?></p>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <section class="section" id="features">
      <div class="wrap">
        <h2<?= ed('features_title') ?>><?= e($t['features_title']) ?></h2>
        <div class="features">
          <?php foreach ($t['features'] as $i => [$title, $text]): ?>
            <article class="card">
              <span class="card__dot card__dot--<?= $i ?>" aria-hidden="true"></span>
              <h3<?= ed("features.$i.0") ?>><?= e($title) ?></h3>
              <p<?= ed("features.$i.1") ?>><?= e($text) ?></p>
            </article>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <section class="section" id="screens">
      <div class="wrap">
        <h2<?= ed('screens_title') ?>><?= e($t['screens_title']) ?></h2>
        <div class="screens"<?= $editor ? ' data-gallery' : '' ?>>
          <?php foreach ($gallery as $shot): ?>
            <figure class="screen<?= $shot['wide'] ? ' screen--wide' : '' ?>"<?= $editor ? ' data-gallery-id="' . $shot['id'] . '"' : '' ?>>
              <a href="<?= e($shot['src']) ?>" data-lightbox>
                <img src="<?= e($shot['src']) ?>" alt="<?= e($shot['caption']) ?>"<?= $shot['w'] ? ' width="' . $shot['w'] . '" height="' . $shot['h'] . '"' : '' ?> loading="lazy">
              </a>
              <?php if ($shot['caption'] !== '' || $editor): ?>
                <figcaption<?= ed($shot['field'], 'Подпись к скриншоту') ?>><?= e($shot['caption']) ?></figcaption>
              <?php endif; ?>
            </figure>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <section class="section">
      <div class="wrap">
        <h2<?= ed('how_title') ?>><?= e($t['how_title']) ?></h2>
        <ol class="steps">
          <?php foreach ($t['how'] as $i => [$title, $text]): ?>
            <li class="step">
              <span class="step__num"><?= $i + 1 ?></span>
              <b<?= ed("how.$i.0") ?>><?= e($title) ?></b> <span<?= ed("how.$i.1") ?>><?= e($text) ?></span>
            </li>
          <?php endforeach; ?>
        </ol>
      </div>
    </section>

    <section class="section" id="install">
      <div class="wrap install">
        <div>
          <h2<?= ed('install_title') ?>><?= e($t['install_title']) ?></h2>
          <ol class="install__list">
            <?php foreach ($t['install'] as $i => $item): ?>
              <li<?= ed("install.$i") ?>><?= $item /* lang.php defaults or sanitizeText(): only <b> allowed */ ?></li>
            <?php endforeach; ?>
          </ol>
          <p class="install__note"<?= ed('install_terminal') ?>><?= e($t['install_terminal']) ?></p>
          <div class="code">
            <pre><code id="terminal-cmd"><?= e($terminalCmd) ?></code></pre>
            <button class="code__copy" type="button" data-copy="#terminal-cmd" aria-label="Copy">⧉</button>
          </div>
        </div>
        <aside class="panel">
          <h3<?= ed('requirements_title') ?>><?= e($t['requirements_title']) ?></h3>
          <ul class="checks">
            <?php foreach ($t['requirements'] as $i => $r): ?>
              <li<?= ed("requirements.$i") ?>><?= e($r) ?></li>
            <?php endforeach; ?>
          </ul>
          <?php if ($pkg): ?>
            <a class="btn btn--block" href="<?= e($pkgUrl) ?>" download><span<?= ed('hero_btn_pkg') ?>><?= e($t['hero_btn_pkg']) ?></span> <span class="btn__ext">.pkg</span></a>
            <p class="meta meta--center"><?= e(sprintf($t['hero_meta'], $pkg['version'], $pkg['kb'])) ?></p>
          <?php endif; ?>
          <a class="gh-link" href="<?= e(GITHUB_URL) ?>" target="_blank" rel="noopener">
            <svg viewBox="0 0 16 16" width="16" height="16" aria-hidden="true"><path fill="currentColor" d="<?= GITHUB_ICON ?>"/></svg>
            <span<?= ed('github_link') ?>><?= e($t['github_link']) ?></span>
          </a>
        </aside>
      </div>
    </section>

    <section class="section">
      <div class="wrap">
        <h2<?= ed('faq_title') ?>><?= e($t['faq_title']) ?></h2>
        <div class="faq">
          <?php foreach ($t['faq'] as $i => [$q, $a]): ?>
            <details<?= $editor ? ' open' : '' ?>>
              <summary><span<?= ed("faq.$i.0") ?>><?= e($q) ?></span></summary>
              <p<?= ed("faq.$i.1") ?>><?= e($a) ?></p>
            </details>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
  </main>

  <footer class="footer">
    <div class="wrap footer__inner">
      <span class="footer__copy">
        © <?= date('Y') ?> Logic Palette<?= $version ? ' · v' . e($version) : '' ?> ·
        <a class="footer__dev" href="<?= e(GITHUB_URL) ?>" target="_blank" rel="noopener">GitHub</a>
        <?php if (!$editor): ?>
          <a class="footer__admin" href="admin.php?next=site" rel="nofollow" title="Admin" aria-label="Admin">
            <svg viewBox="0 0 24 24" width="15" height="15" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4 11.5-11.5Z"/></svg>
          </a>
        <?php endif; ?>
      </span>
      <span><span<?= ed('developer') ?>><?= e($t['developer']) ?></span>: <a class="footer__dev" href="https://stm-project.ru" target="_blank" rel="noopener">STM Webcode Systems</a></span>
      <span class="footer__note"<?= ed('footer_note') ?>><?= e($t['footer_note']) ?></span>
    </div>
  </footer>

  <div class="lightbox" hidden role="dialog" aria-modal="true" aria-label="<?= e($t['screens_title']) ?>">
    <button class="lightbox__btn lightbox__close" type="button" data-lb="close" aria-label="<?= e($t['lb_close']) ?>">×</button>
    <button class="lightbox__btn lightbox__nav lightbox__nav--prev" type="button" data-lb="prev" aria-label="<?= e($t['lb_prev']) ?>">‹</button>
    <figure class="lightbox__figure">
      <img alt="">
      <figcaption class="lightbox__caption">
        <span class="lightbox__text"></span>
        <span class="lightbox__count"></span>
      </figcaption>
    </figure>
    <button class="lightbox__btn lightbox__nav lightbox__nav--next" type="button" data-lb="next" aria-label="<?= e($t['lb_next']) ?>">›</button>
  </div>
  <script src="<?= e($asset('assets/js/main.js')) ?>"></script>
  <?php if ($editor): ?>
    <script src="<?= e($asset('assets/js/editor.js')) ?>"></script>
  <?php endif; ?>
</body>
</html>
