<?php /** Страница 404. Переменные: $articles — последние статьи */ ?>
    <section class="hero">
      <h1>Страница не найдена</h1>
      <p class="lead">Возможно, в адресе опечатка или страница переехала. Начните с <a href="/">главной</a>.</p>
    </section>
<?php if ($articles): ?>

    <section class="related" aria-labelledby="latest-title">
      <h2 id="latest-title">Последние статьи</h2>
      <ul>
<?php foreach ($articles as $a): ?>
        <li><a href="/<?= e($a['slug']) ?>/"><?= e($a['meta']['title']) ?></a></li>
<?php endforeach ?>
      </ul>
    </section>
<?php endif ?>
