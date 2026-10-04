<?php
// Сборка сайта: content/*.md → public/.
//   php build.php         предпросмотр по адресу http://localhost:8000, закрыт от индексации
//   php build.php --prod  боевая сборка по адресу из settings.php
// Если есть ошибки, public/ не трогается и скрипт завершается с кодом 1.
declare(strict_types=1);

require __DIR__ . '/lib/Parsedown.php';
require __DIR__ . '/lib/functions.php';

$root = __DIR__;
$config = require $root . '/settings.php';
$prod = in_array('--prod', $argv, true);
$errors = [];
$warnings = [];

function fail(array $errors): void
{
    fwrite(STDERR, "Сборка остановлена, public/ не изменён:\n");
    foreach ($errors as $err) {
        fwrite(STDERR, "  ✗ $err\n");
    }
    exit(1);
}

// ---------- Адрес сайта

if ($prod) {
    $base = rtrim($config['url'], '/');
    if (!preg_match('#^https://[a-z0-9.-]+\.[a-z0-9-]{2,}$#i', $base) || str_contains($base, 'localhost')) {
        fail(["для боевой сборки укажите в settings.php 'url' => 'https://домен' (сейчас: «{$config['url']}»)"]);
    }
} else {
    $base = 'http://localhost:8000';
}

// ---------- Чтение и проверка контента

/**
 * Проверяет шапку и текст документа. Ошибки останавливают сборку, предупреждения — нет.
 */
function check_doc(array $meta, string $body, string $label, bool $needDate, array &$errors, array &$warnings): void
{
    $required = $needDate ? ['title', 'description', 'status', 'date'] : ['title', 'description', 'status'];
    foreach ($required as $key) {
        if (($meta[$key] ?? '') === '') {
            $errors[] = "$label: в шапке нет поля $key";
        }
    }
    $status = $meta['status'] ?? '';
    if ($status !== '' && !in_array($status, ['draft', 'published'], true)) {
        $errors[] = "$label: status может быть только draft или published, сейчас «{$status}»";
    }
    foreach (['date', 'updated'] as $key) {
        if (($meta[$key] ?? '') !== '' && !valid_date($meta[$key])) {
            $errors[] = "$label: $key должна быть в формате ГГГГ-ММ-ДД, сейчас «{$meta[$key]}»";
        }
    }
    if (valid_date($meta['date'] ?? '') && valid_date($meta['updated'] ?? '') && $meta['updated'] < $meta['date']) {
        $errors[] = "$label: updated раньше date";
    }
    if ($status !== 'published') {
        return;
    }
    if (valid_date($meta['date'] ?? '') && $meta['date'] > date('Y-m-d')) {
        $warnings[] = "$label: дата публикации в будущем ({$meta['date']})";
    }
    $text = preg_replace('/^```.*?^```/ms', '', $body);
    if (preg_match('/^#\s/m', $text)) {
        $errors[] = "$label: в тексте есть заголовок «# …» — H1 берётся из title, в тексте начинайте с «## …»";
    }
    if (preg_match('/\[проверить/u', $body)) {
        $errors[] = "$label: осталась редакторская пометка [проверить …] — закройте её перед публикацией";
    }
    if (trim($body) === '') {
        $errors[] = "$label: опубликован пустой текст";
    }
    $titleLen = mb_len($meta['title'] ?? '');
    if ($titleLen > 70) {
        $warnings[] = "$label: title длиннее 70 символов ($titleLen) — в выдаче обрежется";
    }
    $descLen = mb_len($meta['description'] ?? '');
    if ($descLen && ($descLen < 70 || $descLen > 170)) {
        $warnings[] = "$label: description лучше 70–170 символов, сейчас $descLen";
    }
}

$parsedown = new Parsedown();
$reserved = ['assets', 'img', '404', 'sitemap', 'robots'];

$articles = [];
$drafts = [];
foreach (glob("$root/content/articles/*.md") ?: [] as $file) {
    $slug = basename($file, '.md');
    if ($slug[0] === '_') {
        continue; // образцы и служебные файлы не публикуются
    }
    $label = "content/articles/$slug.md";
    if (!preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug) || in_array($slug, $reserved, true)) {
        $errors[] = "$label: имя файла — это адрес страницы, только латиница, цифры и дефисы: kak-nastroit-agenta.md";
        continue;
    }
    $doc = parse_doc($file);
    if (is_string($doc)) {
        $errors[] = "$label: $doc";
        continue;
    }
    check_doc($doc['meta'], $doc['body'], $label, true, $errors, $warnings);
    if (($doc['meta']['status'] ?? '') !== 'published') {
        $drafts[] = $slug;
        continue;
    }
    $articles[$slug] = $doc + ['slug' => $slug];
}

$pages = [];
foreach (glob("$root/content/pages/*.md") ?: [] as $file) {
    $slug = basename($file, '.md');
    $label = "content/pages/$slug.md";
    if (!preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug) || in_array($slug, $reserved, true)) {
        $errors[] = "$label: недопустимое имя файла";
        continue;
    }
    if (isset($articles[$slug]) || in_array($slug, $drafts, true)) {
        $errors[] = "$label: адрес /$slug/ уже занят статьёй";
        continue;
    }
    $doc = parse_doc($file);
    if (is_string($doc)) {
        $errors[] = "$label: $doc";
        continue;
    }
    check_doc($doc['meta'], $doc['body'], $label, false, $errors, $warnings);
    if (($doc['meta']['status'] ?? '') === 'published') {
        $pages[$slug] = $doc + ['slug' => $slug];
    }
}

$home = parse_doc("$root/content/home.md");
if (is_string($home)) {
    $errors[] = "content/home.md: $home";
} else {
    $home['meta']['status'] = 'published';
    check_doc($home['meta'], $home['body'], 'content/home.md', false, $errors, $warnings);
}

$metrikaId = trim((string) $config['metrika_id']);
if ($metrikaId !== '' && !ctype_digit($metrikaId)) {
    $errors[] = "settings.php: metrika_id должен состоять из цифр";
}
if ($prod && $metrikaId !== '' && !isset($pages['privacy'])) {
    if (!empty($config['metrika_without_privacy'])) {
        $warnings[] = 'Метрика подключена без политики конфиденциальности (metrika_without_privacy в settings.php) — опубликуйте content/pages/privacy.md';
    } else {
        $errors[] = 'Метрика ставит cookie: перед её подключением опубликуйте content/pages/privacy.md';
    }
}

if ($errors) {
    fail($errors);
}

// ---------- Сборка

uasort($articles, fn($a, $b) => [$b['meta']['date'], $a['meta']['title']] <=> [$a['meta']['date'], $b['meta']['title']]);

$site = [
    'name'       => $config['name'],
    'tagline'    => $config['tagline'],
    'base'       => $base,
    'prod'       => $prod,
    'metrika_id' => $prod ? $metrikaId : '',
    'yandex_verification' => (string) ($config['yandex_verification'] ?? ''),
    'author'     => $config['author'],
    'css'        => '/assets/css/style.css?v=' . substr(md5_file("$root/assets/css/style.css"), 0, 8),
    'about'      => isset($pages['about']),
    'privacy'    => isset($pages['privacy']),
];
$publisher = ['@type' => 'Organization', 'name' => $site['name'], 'url' => $base . '/'];

$out = "$root/public.tmp";
remove_dir($out);
mkdir($out);

$page = function (array $p) use ($site): string {
    return render('layout', ['site' => $site, 'page' => $p + ['noindex' => false, 'og_type' => 'website', 'jsonld' => []]]);
};
// « — Staff Agent» дописываем, только если весь <title> укладывается в 70 символов: длиннее Яндекс обрежет
$titleTag = function (string $title) use ($site): string {
    $full = $title . ' — ' . $site['name'];
    return mb_len($full) <= 70 ? $full : $title;
};
$crumbs = function (string $title, string $path) use ($base, $site): array {
    return [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => $site['name'], 'item' => $base . '/'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => $title, 'item' => $base . $path],
        ],
    ];
};

// Статьи
$list = array_values($articles);
foreach ($list as $i => $a) {
    $m = $a['meta'];
    $path = "/{$a['slug']}/";
    [$html, $toc] = heading_ids($parsedown->text($a['body']));
    $related = array_slice(array_values(array_filter($list, fn($x) => $x['slug'] !== $a['slug'])), 0, (int) $config['related_count']);
    $author = $site['author'] !== '' ? ['@type' => 'Person', 'name' => $site['author']] : $publisher;
    write_file("$out$path/index.html", $page([
        'title_tag'   => $titleTag($m['title']),
        'description' => $m['description'],
        'path'        => $path,
        'og_type'     => 'article',
        'jsonld'      => [[
            '@context'         => 'https://schema.org',
            '@type'            => 'BlogPosting',
            'headline'         => $m['title'],
            'description'      => $m['description'],
            'datePublished'    => $m['date'],
            'dateModified'     => $m['updated'] ?? $m['date'],
            'mainEntityOfPage' => $base . $path,
            'inLanguage'       => 'ru',
            'author'           => $author,
            'publisher'        => $publisher,
        ], $crumbs($m['title'], $path)],
        'content' => render('article', [
            'a'       => $m,
            'html'    => $html,
            'toc'     => count($toc) >= 3 ? $toc : [],
            'minutes' => reading_minutes($a['body']),
            'related' => $related,
            'site'    => $site,
        ]),
    ]));
}

// Страницы
foreach ($pages as $p) {
    $path = "/{$p['slug']}/";
    write_file("$out$path/index.html", $page([
        'title_tag'   => $titleTag($p['meta']['title']),
        'description' => $p['meta']['description'],
        'path'        => $path,
        'jsonld'      => [$crumbs($p['meta']['title'], $path)],
        'content'     => render('page', ['p' => $p['meta'], 'html' => $parsedown->text($p['body']), 'site' => $site]),
    ]));
}

// Главная и 404
write_file("$out/index.html", $page([
    'title_tag'   => $home['meta']['title'],
    'description' => $home['meta']['description'],
    'path'        => '/',
    'jsonld'      => [[
        '@context'    => 'https://schema.org',
        '@type'       => 'WebSite',
        'name'        => $site['name'],
        'url'         => $base . '/',
        'description' => $home['meta']['description'],
        'inLanguage'  => 'ru',
    ]],
    'content' => render('home', [
        'h1'       => $home['meta']['h1'] ?? $site['name'],
        'intro'    => $parsedown->text($home['body']),
        'articles' => $list,
    ]),
]));
write_file("$out/404.html", $page([
    'title_tag'   => 'Страница не найдена — ' . $site['name'],
    'description' => 'Такой страницы на сайте нет.',
    'path'        => '/404.html',
    'noindex'     => true,
    'content'     => render('404', ['articles' => array_slice($list, 0, 5)]),
]));

// sitemap.xml и robots.txt
$lastmod = fn(array $m) => $m['updated'] ?? $m['date'] ?? null;
// Главная меняется с каждой новой статьёй; пока статей нет — дата из updated в content/home.md
$homeMod = max(array_filter(array_merge([$home['meta']['updated'] ?? null], array_map(fn($a) => $lastmod($a['meta']), $list))) ?: [null]);
$urls = [['/', $homeMod]];
foreach ($list as $a) {
    $urls[] = ["/{$a['slug']}/", $lastmod($a['meta'])];
}
foreach ($pages as $p) {
    $urls[] = ["/{$p['slug']}/", $lastmod($p['meta'])];
}
$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as [$path, $mod]) {
    $xml .= '  <url><loc>' . e($base . $path) . '</loc>' . ($mod ? "<lastmod>$mod</lastmod>" : '') . "</url>\n";
}
write_file("$out/sitemap.xml", $xml . "</urlset>\n");
write_file("$out/robots.txt", $prod
    ? "User-agent: *\nAllow: /\n\nSitemap: $base/sitemap.xml\n"
    : "# Предпросмотр: сайт закрыт от индексации\nUser-agent: *\nDisallow: /\n");

copy_dir("$root/assets", "$out/assets");
copy_dir("$root/content/img", "$out/img");
copy("$root/assets/img/favicon.svg", "$out/favicon.svg");

// Файлы, которые должны лежать в корне сайта как есть (подтверждение Вебмастера и т. п.)
foreach (glob("$root/static/*") ?: [] as $file) {
    $name = basename($file);
    if (file_exists("$out/$name")) {
        $errors[] = "static/$name: файл с таким именем сайт уже создаёт";
        continue;
    }
    copy($file, "$out/$name");
}

// ---------- Проверки готового сайта

$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($out, FilesystemIterator::SKIP_DOTS));
foreach ($files as $f) {
    if (!preg_match('/\.(html|xml|txt)$/', $f->getFilename())) {
        continue;
    }
    $rel = str_replace([$out, '\\'], ['', '/'], $f->getPathname());
    $text = file_get_contents($f->getPathname());
    if (str_contains($text, '{{')) {
        $errors[] = "$rel: осталась заглушка {{…}}";
    }
    preg_match_all('/(?:href|src)="(\/[^"#?]*)/', $text, $links);
    foreach (array_unique($links[1]) as $link) {
        $target = $out . (str_ends_with($link, '/') ? $link . 'index.html' : $link);
        if (!is_file($target)) {
            $errors[] = "$rel: ссылка на несуществующую страницу $link";
        }
    }
}
if ($errors) {
    remove_dir($out);
    fail($errors);
}

remove_dir("$root/public");
rename($out, "$root/public");

// ---------- Итог

echo 'Собрано в public/ — ' . ($prod ? "боевая сборка для $base" : "предпросмотр (закрыт от индексации): php -S localhost:8000 -t public") . "\n";
echo '  статей: ' . count($list) . ', черновиков: ' . count($drafts) . ', страниц: ' . count($pages) . "\n";
foreach ($warnings as $w) {
    echo "  ! $w\n";
}
