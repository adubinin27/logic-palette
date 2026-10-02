<?php
/**
 * Logic Palette landing — admin: real download statistics and the public counter.
 *
 * @author STM Webcode Systems (stm-project.ru)
 * @copyright 2026
 */

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/db.php';

sendSecurityHeaders();
header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');

startSecureSession();

$configured = env('ADMIN_LOGIN') !== '' && env('ADMIN_PASSWORD_HASH') !== '';
$authed = !empty($_SESSION['admin']);
$error = '';
$toSite = (($_POST['next'] ?? $_GET['next'] ?? '') === 'site');

function redirectSelf(string $flash = '', bool $toSite = false): never
{
    if ($flash !== '') {
        $_SESSION['flash'] = $flash;
    }
    header('Location: ' . ($toSite ? './' : 'admin.php'), true, 303);
    exit;
}

if ($authed && $toSite && $_SERVER['REQUEST_METHOD'] === 'GET') {
    redirectSelf('', true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    if (!csrfValid($_POST['csrf'] ?? null)) {
        $error = 'Сессия устарела, обновите страницу и повторите.';
    } elseif ($action === 'login' && $configured && !$authed) {
        if (!rateLimitHit('login', 5, 900)) {
            $error = 'Слишком много попыток. Попробуйте через 15 минут.';
        } elseif (hash_equals(env('ADMIN_LOGIN'), (string)($_POST['login'] ?? ''))
            && password_verify((string)($_POST['password'] ?? ''), env('ADMIN_PASSWORD_HASH'))) {
            session_regenerate_id(true);
            $_SESSION['admin'] = true;
            unset($_SESSION['csrf']);
            rateLimitReset('login');
            audit('login');
            redirectSelf('', $toSite);
        } else {
            audit('login_failed', mb_substr((string)($_POST['login'] ?? ''), 0, 60));
            $error = 'Неверный логин или пароль.';
        }
    } elseif ($action === 'logout' && $authed) {
        audit('logout');
        $_SESSION = [];
        session_regenerate_id(true);
        redirectSelf('', $toSite);
    }
}

$flash = (string)($_SESSION['flash'] ?? '');
unset($_SESSION['flash']);
$csrf = csrfToken();

if ($authed) {
    $s = downloadStats();
    $base = max(0, (int)setting('display_base', '0'));
    $enabled = setting('display_enabled', '1') === '1';
    $public = $base + $s['total'];
    $maxDay = max(1, ...array_values($s['days']));
}

$n = fn(int $v): string => number_format($v, 0, '', "\u{202F}");
?><!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>Админка · Logic Palette</title>
  <link rel="icon" type="image/png" href="assets/img/favicon.png">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body class="admin">
  <div class="spectrum" aria-hidden="true"></div>
  <header class="header">
    <div class="wrap header__inner">
      <a class="brand" href="./">
        <img src="assets/img/icon.png" alt="" width="32" height="32">
        <span>Logic Palette · админка</span>
      </a>
      <?php if ($authed): ?>
        <div class="admin__actions">
          <a class="btn btn--small" href="./">✎ Редактировать сайт</a>
          <form method="post" class="admin__logout">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <button class="lang" name="action" value="logout">Выйти</button>
          </form>
        </div>
      <?php endif; ?>
    </div>
  </header>

  <main class="wrap admin__main">
  <?php if (!$configured): ?>
    <div class="panel admin__login">
      <h1>Админка не настроена</h1>
      <p class="meta">Заполните <code>ADMIN_LOGIN</code> и <code>ADMIN_PASSWORD_HASH</code> в файле <code>site/.env</code> (образец — <code>.env.example</code>).</p>
    </div>

  <?php elseif (!$authed): ?>
    <form method="post" class="panel admin__login">
      <h1>Вход</h1>
      <?php if ($error): ?><p class="admin__error"><?= e($error) ?></p><?php endif; ?>
      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
      <?php if ($toSite): ?><input type="hidden" name="next" value="site"><?php endif; ?>
      <label>Логин <input name="login" autocomplete="username" required autofocus></label>
      <label>Пароль <input type="password" name="password" autocomplete="current-password" required></label>
      <button class="btn btn--block" name="action" value="login">Войти</button>
    </form>

  <?php else: ?>
    <?php if ($flash): ?><p class="admin__flash"><?= e($flash) ?></p><?php endif; ?>
    <?php if ($error): ?><p class="admin__error"><?= e($error) ?></p><?php endif; ?>

    <h1>Реальные скачивания</h1>
    <div class="stats">
      <div class="stat"><b><?= $n($s['total']) ?></b><span>всего</span></div>
      <div class="stat"><b><?= $n($s['unique']) ?></b><span>уникальных IP</span></div>
      <div class="stat"><b><?= $n($s['today']) ?></b><span>сегодня</span></div>
      <div class="stat"><b><?= $n($s['week']) ?></b><span>за 7 дней</span></div>
      <div class="stat"><b><?= $n($s['month']) ?></b><span>за 30 дней</span></div>
      <div class="stat"><b><?= $n($s['pkg']) ?> / <?= $n($s['dmg']) ?></b><span>.pkg / .dmg</span></div>
    </div>

    <section class="panel">
      <h2>За 30 дней</h2>
      <svg class="chart" viewBox="0 0 600 140" preserveAspectRatio="none" role="img" aria-label="Скачивания по дням">
        <?php $i = 0; foreach ($s['days'] as $day => $c):
            $h = $c > 0 ? max(3, round($c / $maxDay * 120)) : 1; ?>
          <rect x="<?= $i * 20 + 2 ?>" y="<?= 130 - $h ?>" width="16" height="<?= $h ?>" rx="2" class="<?= $c > 0 ? 'chart__bar' : 'chart__empty' ?>">
            <title><?= e(date('d.m', strtotime($day))) ?>: <?= $c ?></title>
          </rect>
        <?php $i++; endforeach; ?>
      </svg>
      <div class="chart__axis">
        <span><?= e(date('d.m', strtotime(array_key_first($s['days'])))) ?></span>
        <span>макс. <?= $maxDay ?>/день</span>
        <span>сегодня</span>
      </div>
    </section>

    <section class="panel">
      <h2>Счётчик на сайте</h2>
      <p class="meta">На лендинге: <b>стартовое число <?= $n($base) ?> + реальные <?= $n($s['total']) ?></b> = <b class="admin__public"><?= $enabled ? $n($public) : 'скрыт' ?></b></p>
      <p class="meta">Стартовое число и показ счётчика меняются в верхней панели редактора прямо на сайте.</p>
      <a class="btn" href="./">✎ Открыть сайт в режиме редактора</a>
    </section>

    <div class="admin__cols">
      <section class="panel">
        <h2>Версии</h2>
        <table class="table">
          <?php foreach ($s['versions'] as $r): ?>
            <tr><td><?= e($r['version'] ?: '—') ?></td><td>.<?= e($r['kind']) ?></td><td><?= $n((int)$r['c']) ?></td></tr>
          <?php endforeach; ?>
          <?php if (!$s['versions']): ?><tr><td class="meta">пока пусто</td></tr><?php endif; ?>
        </table>
      </section>
      <section class="panel">
        <h2>Язык сайта</h2>
        <table class="table">
          <?php foreach ($s['langs'] as $r): ?>
            <tr><td><?= e(strtoupper($r['lang'] ?: '—')) ?></td><td><?= $n((int)$r['c']) ?></td></tr>
          <?php endforeach; ?>
          <?php if (!$s['langs']): ?><tr><td class="meta">пока пусто</td></tr><?php endif; ?>
        </table>
      </section>
      <section class="panel">
        <h2>Источники</h2>
        <table class="table">
          <?php foreach ($s['referers'] as $r): ?>
            <tr><td><?= e($r['referer']) ?></td><td><?= $n((int)$r['c']) ?></td></tr>
          <?php endforeach; ?>
          <?php if (!$s['referers']): ?><tr><td class="meta">пока пусто</td></tr><?php endif; ?>
        </table>
      </section>
    </div>

    <section class="panel">
      <h2>Последние 30 скачиваний</h2>
      <div class="table__scroll">
        <table class="table table--log">
          <tr><th>Дата</th><th>Файл</th><th>Версия</th><th>Язык</th><th>IP (хеш)</th><th>Источник</th><th>Браузер</th></tr>
          <?php foreach ($s['recent'] as $r): ?>
            <tr>
              <td><?= e(date('d.m.Y H:i', strtotime($r['created_at']))) ?></td>
              <td>.<?= e($r['kind']) ?></td>
              <td><?= e($r['version']) ?></td>
              <td><?= e(strtoupper($r['lang'])) ?></td>
              <td><code><?= e($r['ip_hash']) ?></code></td>
              <td><?= e($r['referer'] ?: '—') ?></td>
              <td class="table__ua" title="<?= e($r['user_agent']) ?>"><?= e(mb_strimwidth($r['user_agent'], 0, 60, '…')) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$s['recent']): ?><tr><td colspan="7" class="meta">пока пусто</td></tr><?php endif; ?>
        </table>
      </div>
    </section>
  <?php endif; ?>
  </main>
</body>
</html>
