<?php /** Статья. Переменные: $a (шапка), $html, $toc, $minutes, $related */ ?>
    <nav class="crumbs" aria-label="Навигационная цепочка">
      <a href="/">Главная</a> <span aria-hidden="true">›</span> <span><?= e($a['title']) ?></span>
    </nav>

    <article class="article">
      <header class="article__head">
        <h1><?= e($a['title']) ?></h1>
        <p class="meta">
          <time datetime="<?= e($a['date']) ?>"><?= ru_date($a['date']) ?></time>
<?php if (!empty($a['updated']) && $a['updated'] !== $a['date']): ?>
          · обновлено <time datetime="<?= e($a['updated']) ?>"><?= ru_date($a['updated']) ?></time>
<?php endif ?>
          · <?= $minutes ?> мин чтения
        </p>
      </header>
<?php if ($toc): ?>

      <nav class="toc" aria-labelledby="toc-title">
        <p class="toc__title" id="toc-title">Содержание</p>
        <ol>
<?php foreach ($toc as $item): ?>
          <li><a href="#<?= e($item['id']) ?>"><?= e($item['text']) ?></a></li>
<?php endforeach ?>
        </ol>
      </nav>
<?php endif ?>

      <div class="prose">
<?= $html ?>
      </div>
    </article>
<?php if ($related): ?>

    <aside class="related" aria-labelledby="related-title">
      <h2 id="related-title">Читайте также</h2>
      <ul>
<?php foreach ($related as $r): ?>
        <li><a href="/<?= e($r['slug']) ?>/"><?= e($r['meta']['title']) ?></a></li>
<?php endforeach ?>
      </ul>
    </aside>
<?php endif ?>
