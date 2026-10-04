<?php
// Вспомогательные функции сборки: разбор файлов, транслит, даты, оглавление.

function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function mb_len(string $s): int
{
    return mb_strlen($s, 'UTF-8');
}

/**
 * Читает markdown-файл с шапкой вида
 * ---
 * key: value
 * ---
 * Возвращает ['meta' => [...], 'body' => '...'] или строку с ошибкой.
 */
function parse_doc(string $path)
{
    $raw = str_replace("\r\n", "\n", file_get_contents($path));
    $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
    if (!preg_match('/^---\n(.*?)\n---\n?(.*)$/s', $raw, $m)) {
        return 'нет шапки между строками ---';
    }
    $meta = [];
    foreach (explode("\n", $m[1]) as $n => $line) {
        if (trim($line) === '' || str_starts_with(ltrim($line), '#')) {
            continue;
        }
        if (!preg_match('/^([a-z][a-z0-9_]*):\s*(.*)$/', $line, $kv)) {
            return 'строка ' . ($n + 2) . ' шапки не в формате «ключ: значение»';
        }
        $meta[$kv[1]] = trim($kv[2], " \t\"'");
    }
    return ['meta' => $meta, 'body' => trim($m[2]) . "\n"];
}

function translit(string $s): string
{
    static $map = [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e', 'ж' => 'zh',
        'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o',
        'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'c',
        'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch', 'ъ' => '', 'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu',
        'я' => 'ya',
    ];
    return strtr(mb_strtolower($s, 'UTF-8'), $map);
}

function slugify(string $s): string
{
    $s = preg_replace('/[^a-z0-9]+/', '-', translit($s));
    return trim($s, '-') ?: 'section';
}

function ru_date(string $ymd): string
{
    static $months = ['января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля',
        'августа', 'сентября', 'октября', 'ноября', 'декабря'];
    [$y, $m, $d] = array_map('intval', explode('-', $ymd));
    return $d . ' ' . $months[$m - 1] . ' ' . $y;
}

function valid_date(string $s): bool
{
    $d = DateTime::createFromFormat('!Y-m-d', $s);
    return $d && $d->format('Y-m-d') === $s;
}

/** Минуты чтения при скорости 180 слов в минуту */
function reading_minutes(string $markdown): int
{
    $words = preg_split('/\s+/u', trim(strip_tags($markdown)), -1, PREG_SPLIT_NO_EMPTY);
    return max(1, (int) round(count($words) / 180));
}

/**
 * Проставляет id заголовкам h2/h3 и собирает оглавление по h2.
 * Возвращает [html, [['id' => ..., 'text' => ...], ...]].
 */
function heading_ids(string $html): array
{
    $used = [];
    $toc = [];
    $html = preg_replace_callback('#<(h[23])>(.*?)</\1>#s', function ($m) use (&$used, &$toc) {
        $text = html_entity_decode(strip_tags($m[2]), ENT_QUOTES, 'UTF-8');
        $id = $base = slugify($text);
        for ($i = 2; isset($used[$id]); $i++) {
            $id = $base . '-' . $i;
        }
        $used[$id] = true;
        if ($m[1] === 'h2') {
            $toc[] = ['id' => $id, 'text' => $text];
        }
        return '<' . $m[1] . ' id="' . $id . '">' . $m[2] . '</' . $m[1] . '>';
    }, $html);
    return [$html, $toc];
}

/** Подключает PHP-шаблон с переменными и возвращает результат строкой */
function render(string $template, array $vars): string
{
    extract($vars);
    ob_start();
    include __DIR__ . '/../templates/' . $template . '.php';
    return ob_get_clean();
}

function json_ld(array $data): string
{
    return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_PRETTY_PRINT);
}

function copy_dir(string $from, string $to): void
{
    if (!is_dir($from)) {
        return;
    }
    @mkdir($to, 0777, true);
    foreach (scandir($from) as $f) {
        if ($f === '.' || $f === '..') {
            continue;
        }
        is_dir("$from/$f") ? copy_dir("$from/$f", "$to/$f") : copy("$from/$f", "$to/$f");
    }
}

function remove_dir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    foreach (scandir($dir) as $f) {
        if ($f === '.' || $f === '..') {
            continue;
        }
        is_dir("$dir/$f") ? remove_dir("$dir/$f") : unlink("$dir/$f");
    }
    rmdir($dir);
}

function write_file(string $path, string $content): void
{
    @mkdir(dirname($path), 0777, true);
    file_put_contents($path, $content);
}
