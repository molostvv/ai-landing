<?php
/**
 * Приём заявки с лендинга → e-mail (mail()) и Telegram (Bot API).
 *
 * POST /send.php                 — заявка (name, contact, task, consent, website — honeypot), ответ JSON {ok, message, errors?}
 * GET  /send.php?action=config   — публичные настройки для страницы: {metrikaId}
 *
 * Настройки — в config.php рядом с этим файлом (образец: config.example.php).
 */
declare(strict_types=1);

const RATE_LIMIT  = 5      // заявок с одного IP
const RATE_WINDOW = 3600;  // за столько секунд

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function respond(int $status, array $data): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/** config.php или null, если файла нет или он сломан. */
function load_config(): ?array
{
    $file = __DIR__ . '/config.php';
    if (!is_file($file)) {
        return null;
    }
    try {
        $config = require $file;
    } catch (Throwable $e) {
        error_log('send.php: config.php не загружается: ' . $e->getMessage());
        return null;
    }
    return is_array($config) ? $config : null;
}

/** Значение настройки; незаполненный плейсхолдер {{...}} считается пустым. */
function setting(?array $config, string $key): string
{
    $value = trim((string) ($config[$key] ?? ''));
    return str_contains($value, '{{') ? '' : $value;
}

/** Убирает управляющие символы; переносы строк оставляет только там, где они разрешены. */
function clean(string $value, bool $multiline = false): string
{
    $value = str_replace(["\r\n", "\r"], "\n", $value);
    $pattern = $multiline ? '/[\x00-\x09\x0B-\x1F\x7F]/u' : '/[\x00-\x1F\x7F]/u';
    return trim((string) preg_replace($pattern, ' ', $value));
}

function validate(array $input): array
{
    $errors = [];
    $len = static fn(string $s): int => mb_strlen($s, 'UTF-8');

    if ($input['name'] === '') {
        $errors['name'] = 'Укажите, как к вам обращаться.';
    } elseif ($len($input['name']) < 2 || $len($input['name']) > 100) {
        $errors['name'] = 'Имя должно быть от 2 до 100 символов.';
    }

    $contact = $input['contact'];
    $digits = preg_replace('/\D/', '', $contact);
    $isPhone = preg_match('/^[\d\s()+\-.]+$/', $contact) === 1 && strlen($digits) >= 10 && strlen($digits) <= 15;
    $isTelegram = preg_match('~^(@|(https?://)?t\.me/)?[A-Za-z][A-Za-z0-9_]{4,31}$~', $contact) === 1;
    if ($contact === '') {
        $errors['contact'] = 'Укажите телефон или ник в Telegram.';
    } elseif (!$isPhone && !$isTelegram) {
        $errors['contact'] = 'Укажите телефон (например, +7 900 000-00-00) или ник в Telegram (@username).';
    }

    if ($input['task'] === '') {
        $errors['task'] = 'Опишите задачу хотя бы в двух словах.';
    } elseif ($len($input['task']) < 5 || $len($input['task']) > 1000) {
        $errors['task'] = 'Описание задачи должно быть от 5 до 1000 символов.';
    }

    if (!$input['consent']) {
        $errors['consent'] = 'Нужно согласие на обработку персональных данных.';
    }

    return $errors;
}

/** true, если с этого IP за последний час уже было RATE_LIMIT заявок. Иначе засчитывает текущую. */
function rate_limited(string $ip): bool
{
    $dir = sys_get_temp_dir() . '/landing-leads-ratelimit';
    if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
        error_log('send.php: не удалось создать ' . $dir . ', лимит заявок не проверяется');
        return false;
    }
    $handle = @fopen($dir . '/' . hash('sha256', $ip) . '.json', 'c+');
    if ($handle === false) {
        return false;
    }
    flock($handle, LOCK_EX);
    $now = time();
    $stamps = json_decode((string) stream_get_contents($handle), true);
    $stamps = array_values(array_filter(
        is_array($stamps) ? $stamps : [],
        static fn($t): bool => is_int($t) && $t > $now - RATE_WINDOW
    ));
    $limited = count($stamps) >= RATE_LIMIT;
    if (!$limited) {
        $stamps[] = $now;
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode($stamps));
    }
    flock($handle, LOCK_UN);
    fclose($handle);
    return $limited;
}

function send_email(string $to, string $from, string $text): bool
{
    $subject = '=?UTF-8?B?' . base64_encode('Новая заявка с сайта') . '?=';
    $headers = implode("\r\n", [
        'From: ' . $from,
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
    ]);
    return mail($to, $subject, $text, $headers);
}

function send_telegram(string $token, string $chatId, string $text): bool
{
    $url = 'https://api.telegram.org/bot' . $token . '/sendMessage';
    $payload = http_build_query(['chat_id' => $chatId, 'text' => $text, 'disable_web_page_preview' => 'true']);

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    } else {
        $context = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => $payload,
            'timeout' => 10,
            'ignore_errors' => true,
        ]]);
        $body = @file_get_contents($url, false, $context);
        $code = 200;
    }

    $result = is_string($body) ? json_decode($body, true) : null;
    $ok = $code === 200 && is_array($result) && ($result['ok'] ?? false) === true;
    if (!$ok) {
        error_log('send.php: Telegram не принял заявку: ' . (is_string($body) ? mb_substr($body, 0, 300) : 'нет ответа'));
    }
    return $ok;
}

/* ---------- Публичные настройки для страницы ---------- */

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET' && ($_GET['action'] ?? '') === 'config') {
    $metrikaId = setting(load_config(), 'METRIKA_ID');
    respond(200, ['metrikaId' => ctype_digit($metrikaId) ? $metrikaId : null]);
}

if ($method !== 'POST') {
    header('Allow: POST');
    respond(405, ['ok' => false, 'message' => 'Заявки принимаются только через форму на сайте.']);
}

/* ---------- Заявка ---------- */

// Honeypot: поле скрыто от людей. Боту отвечаем «успехом», но ничего не отправляем.
if (trim((string) ($_POST['website'] ?? '')) !== '') {
    respond(200, ['ok' => true, 'message' => 'Спасибо! Заявка отправлена — скоро свяжемся с вами.']);
}

$input = [
    'name'    => clean((string) ($_POST['name'] ?? '')),
    'contact' => clean((string) ($_POST['contact'] ?? '')),
    'task'    => clean((string) ($_POST['task'] ?? ''), true),
    'consent' => in_array((string) ($_POST['consent'] ?? ''), ['1', 'on', 'true'], true),
];

$errors = validate($input);
if ($errors) {
    respond(422, ['ok' => false, 'message' => 'Проверьте, пожалуйста, поля формы.', 'errors' => $errors]);
}

$config = load_config();
if ($config === null) {
    error_log('send.php: нет config.php — скопируйте config.example.php в config.php и заполните');
    respond(503, ['ok' => false, 'message' => 'Приём заявок временно не работает: сайт ещё не настроен. Пожалуйста, свяжитесь с нами по контактам внизу страницы.']);
}

$emailTo = setting($config, 'EMAIL_TO');
$tgToken = setting($config, 'TG_BOT_TOKEN');
$tgChat  = setting($config, 'TG_CHAT_ID');
$useEmail = $emailTo !== '' && filter_var($emailTo, FILTER_VALIDATE_EMAIL) !== false;
$useTelegram = $tgToken !== '' && $tgChat !== '';

if (!$useEmail && !$useTelegram) {
    error_log('send.php: в config.php не задан ни EMAIL_TO, ни TG_BOT_TOKEN + TG_CHAT_ID');
    respond(503, ['ok' => false, 'message' => 'Приём заявок временно не работает: не настроена доставка. Пожалуйста, свяжитесь с нами по контактам внизу страницы.']);
}

$ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
if (rate_limited($ip)) {
    respond(429, ['ok' => false, 'message' => 'С вашего адреса уже отправлено несколько заявок. Попробуйте через час или напишите нам в Telegram.']);
}

$text = implode("\n", [
    'Новая заявка с сайта',
    '',
    'Имя: ' . $input['name'],
    'Контакт: ' . $input['contact'],
    'Задача: ' . $input['task'],
    '',
    'Время: ' . date('d.m.Y H:i'),
    'IP: ' . $ip,
]);

$delivered = false;
if ($useEmail) {
    $from = setting($config, 'EMAIL_FROM');
    if (filter_var($from, FILTER_VALIDATE_EMAIL) === false) {
        $host = preg_replace('/[^a-z0-9.-]/i', '', (string) ($_SERVER['SERVER_NAME'] ?? ''));
        $from = filter_var('noreply@' . $host, FILTER_VALIDATE_EMAIL) !== false ? 'noreply@' . $host : 'noreply@localhost.localdomain';
    }
    $sent = send_email($emailTo, $from, $text);
    if (!$sent) {
        error_log('send.php: mail() вернул false — проверьте почтовый сервер (sendmail/postfix)');
    }
    $delivered = $sent || $delivered;
}
if ($useTelegram) {
    $delivered = send_telegram($tgToken, $tgChat, $text) || $delivered;
}

if (!$delivered) {
    respond(502, ['ok' => false, 'message' => 'Не удалось отправить заявку. Попробуйте ещё раз чуть позже или свяжитесь с нами по контактам внизу страницы.']);
}

respond(200, ['ok' => true, 'message' => 'Спасибо! Заявка отправлена — скоро свяжемся с вами.']);
