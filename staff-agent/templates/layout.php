<?php /** Общий каркас страницы. Переменные: $site, $page */ ?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($page['title_tag']) ?></title>
  <meta name="description" content="<?= e($page['description']) ?>">
<?php if ($page['noindex'] || !$site['prod']): ?>
  <meta name="robots" content="noindex, nofollow">
<?php else: ?>
  <link rel="canonical" href="<?= e($site['base'] . $page['path']) ?>">
<?php endif ?>
  <meta property="og:type" content="<?= e($page['og_type']) ?>">
  <meta property="og:site_name" content="<?= e($site['name']) ?>">
  <meta property="og:title" content="<?= e($page['title_tag']) ?>">
  <meta property="og:description" content="<?= e($page['description']) ?>">
  <meta property="og:url" content="<?= e($site['base'] . $page['path']) ?>">
  <meta property="og:locale" content="ru_RU">
  <meta name="theme-color" content="#4F46E5">
<?php if ($site['yandex_verification'] !== ''): ?>
  <meta name="yandex-verification" content="<?= e($site['yandex_verification']) ?>">
<?php endif ?>
  <link rel="icon" href="/favicon.svg" type="image/svg+xml">
  <link rel="stylesheet" href="<?= e($site['css']) ?>">
<?php foreach ($page['jsonld'] as $ld): ?>
  <script type="application/ld+json"><?= json_ld($ld) ?></script>
<?php endforeach ?>
</head>
<body>
  <a class="skip" href="#main">Перейти к содержанию</a>
  <header class="site-header">
    <div class="container site-header__inner">
      <a class="logo" href="/"><img src="/favicon.svg" alt="" width="28" height="28"><span><?= e($site['name']) ?></span></a>
      <nav aria-label="Основное меню">
        <ul class="nav">
          <li><a href="/#articles">Статьи</a></li>
<?php if ($site['about']): ?>
          <li><a href="/about/">О проекте</a></li>
<?php endif ?>
        </ul>
      </nav>
    </div>
  </header>

  <main id="main" class="container">
<?= $page['content'] ?>
  </main>

  <footer class="site-footer">
    <div class="container site-footer__inner">
      <p>© <?= date('Y') ?> <?= e($site['name']) ?> — <?= e($site['tagline']) ?></p>
      <ul class="site-footer__links">
<?php if ($site['about']): ?>
        <li><a href="/about/">О проекте</a></li>
<?php endif ?>
<?php if ($site['privacy']): ?>
        <li><a href="/privacy/">Политика конфиденциальности</a></li>
<?php endif ?>
      </ul>
    </div>
  </footer>
<?php if ($site['metrika_id']): ?>
  <script>
    // Яндекс Метрика грузится после первого действия посетителя или через 3 секунды, чтобы не тормозить страницу
    (function () {
      var id = <?= (int) $site['metrika_id'] ?>, started = false;
      var triggers = ['scroll', 'pointerdown', 'keydown', 'touchstart', 'mousemove'];
      function start() {
        if (started) return;
        started = true;
        triggers.forEach(function (t) { window.removeEventListener(t, start); });
        window.ym = window.ym || function () { (window.ym.a = window.ym.a || []).push(arguments); };
        window.ym.l = Date.now();
        var s = document.createElement('script');
        s.async = true;
        s.src = 'https://mc.yandex.ru/metrika/tag.js?id=' + id;
        document.head.appendChild(s);
        // Вебвизор (запись действий посетителя) выключен: для SEO-отчётов не нужен, а данных собирает больше всего
        window.ym(id, 'init', { ssr: true, webvisor: false, clickmap: true, accurateTrackBounce: true, trackLinks: true });
      }
      triggers.forEach(function (t) { window.addEventListener(t, start, { passive: true }); });
      setTimeout(start, 3000);
    })();
  </script>
  <noscript><div><img src="https://mc.yandex.ru/watch/<?= (int) $site['metrika_id'] ?>" style="position:absolute; left:-9999px;" alt=""></div></noscript>
<?php endif ?>
</body>
</html>
