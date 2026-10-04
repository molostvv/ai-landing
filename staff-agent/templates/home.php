<?php /** Главная. Переменные: $h1, $intro, $articles */ ?>
    <section class="hero">
      <h1><?= e($h1) ?></h1>
      <div class="prose lead">
<?= $intro ?>
      </div>
    </section>

    <section class="articles" id="articles" aria-labelledby="articles-title">
      <h2 id="articles-title">Статьи</h2>
<?php if (!$articles): ?>
      <p class="muted">Первые статьи готовятся.</p>
<?php else: ?>
      <ul class="cards">
<?php foreach ($articles as $a): ?>
        <li class="card">
          <h3 class="card__title"><a href="/<?= e($a['slug']) ?>/"><?= e($a['meta']['title']) ?></a></h3>
          <p class="card__text"><?= e($a['meta']['description']) ?></p>
          <time class="muted" datetime="<?= e($a['meta']['date']) ?>"><?= ru_date($a['meta']['date']) ?></time>
        </li>
<?php endforeach ?>
      </ul>
<?php endif ?>
    </section>
