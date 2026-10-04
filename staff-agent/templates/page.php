<?php /** Обычная страница («О проекте», политика). Переменные: $p (шапка), $html */ ?>
    <nav class="crumbs" aria-label="Навигационная цепочка">
      <a href="/">Главная</a> <span aria-hidden="true">›</span> <span><?= e($p['title']) ?></span>
    </nav>

    <article class="article">
      <header class="article__head">
        <h1><?= e($p['title']) ?></h1>
<?php if (!empty($p['updated'])): ?>
        <p class="meta">Обновлено <time datetime="<?= e($p['updated']) ?>"><?= ru_date($p['updated']) ?></time></p>
<?php endif ?>
      </header>
      <div class="prose">
<?= $html ?>
      </div>
    </article>
