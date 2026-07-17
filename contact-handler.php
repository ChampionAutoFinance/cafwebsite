<?php

const TO_EMAIL = 'info@championautofinance.com';
const FROM_EMAIL = 'no-reply@championautofinance.com';
const DEFAULT_RETURN_URL = 'https://championautofinance.github.io/contact-us/';
const MAX_MESSAGE_LENGTH = 600;
const MAX_POST_BYTES = 12000;
const MAX_SUBMISSIONS_PER_HOUR_PER_IP = 20;

function redirect_with_status($url)
{
    header('Location: ' . $url, true, 303);
    exit;
}

function status_url($sent)
{
    $returnPath = field('return_path');
    $allowed = [
        'https://championautofinance.github.io/contact-us/',
        'https://championautofinance.github.io/dealer-partners/',
    ];

    if (!in_array($returnPath, $allowed, true)) {
        $returnPath = DEFAULT_RETURN_URL;
    }

    return $returnPath . '?sent=' . ($sent ? '1' : '0');
}

function field($name)
{
    $fallbackName = str_replace('.', '_', $name);
    return trim((string)($_POST[$name] ?? $_POST[$fallbackName] ?? ''));
}

function clean_text($value, $maxLength = 200)
{
    $value = preg_replace('/[^\P{C}\t\r\n]/u', '', $value) ?? '';
    $value = preg_replace("/\r\n|\r/", "\n", $value) ?? '';
    $value = trim($value);

    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $maxLength);
    }

    return substr($value, 0, $maxLength);
}

function csv_value($value)
{
    if (preg_match('/^[=+\-@]/', $value) === 1) {
        return "'" . $value;
    }

    return $value;
}

function client_ip()
{
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
        $value = (string)($_SERVER[$key] ?? '');
        if ($value === '') {
            continue;
        }

        $ip = trim(explode(',', $value)[0]);
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }

    return 'unknown';
}

function ensure_storage_dir()
{
    $dir = __DIR__ . '/form-submissions';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    return $dir;
}

function rate_limited($ip)
{
    $dir = ensure_storage_dir();
    $file = $dir . '/rate-limit.json';
    $now = time();
    $windowStart = $now - 3600;
    $key = hash('sha256', $ip);
    $data = [];

    $handle = fopen($file, 'c+');
    if ($handle === false) {
        return false;
    }

    flock($handle, LOCK_EX);
    $contents = stream_get_contents($handle);
    if (is_string($contents) && $contents !== '') {
        $decoded = json_decode($contents, true);
        if (is_array($decoded)) {
            $data = $decoded;
        }
    }

    foreach ($data as $storedKey => $timestamps) {
        if (!is_array($timestamps)) {
            unset($data[$storedKey]);
            continue;
        }

        $data[$storedKey] = array_values(array_filter($timestamps, function ($ts) use ($windowStart) {
            return is_int($ts) && $ts >= $windowStart;
        }));
        if ($data[$storedKey] === []) {
            unset($data[$storedKey]);
        }
    }

    $hits = $data[$key] ?? [];
    $limited = count($hits) >= MAX_SUBMISSIONS_PER_HOUR_PER_IP;
    if (!$limited) {
        $hits[] = $now;
        $data[$key] = $hits;
    }

    ftruncate($handle, 0);
    rewind($handle);
    fwrite($handle, json_encode($data, JSON_PRETTY_PRINT));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);

    return $limited;
}

function save_submission($lead)
{
    $dir = ensure_storage_dir();
    $file = $dir . '/leads-' . gmdate('Y-m') . '.csv';
    $isNew = !file_exists($file);
    $handle = fopen($file, 'a');

    if ($handle === false) {
        return;
    }

    flock($handle, LOCK_EX);
    if ($isNew) {
        fputcsv($handle, ['submitted_at', 'first_name', 'last_name', 'email', 'message', 'ip', 'user_agent']);
    }

    fputcsv($handle, [
        $lead['submitted_at'],
        csv_value($lead['first_name']),
        csv_value($lead['last_name']),
        csv_value($lead['email']),
        csv_value($lead['message']),
        $lead['ip'],
        csv_value($lead['user_agent']),
    ]);

    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
}

function send_notification($lead)
{
    $subject = 'New Champion Auto Finance contact form submission';
    $body = implode("\n", [
        'New contact form submission',
        '',
        'Name: ' . $lead['first_name'] . ' ' . $lead['last_name'],
        'Email: ' . $lead['email'],
        'Submitted: ' . $lead['submitted_at'],
        'IP: ' . $lead['ip'],
        '',
        'Message:',
        $lead['message'],
    ]);

    $headers = [
        'From: Champion Auto Finance <' . FROM_EMAIL . '>',
        'Reply-To: ' . $lead['email'],
        'Content-Type: text/plain; charset=UTF-8',
        'X-CAF-Form: contact-us',
    ];

    $smtpConfig = load_smtp_config();
    if ($smtpConfig !== null) {
        $smtpResult = send_smtp_message($smtpConfig, TO_EMAIL, $subject, $body, $lead['email']);
        if ($smtpResult['ok']) {
            return $smtpResult;
        }
    }

    return [
        'ok' => mail(TO_EMAIL, $subject, $body, implode("\r\n", $headers), '-f ' . FROM_EMAIL),
        'transport' => 'php_mail',
        'error' => null,
    ];
}

function load_smtp_config()
{
    $paths = [
        dirname(__DIR__, 2) . '/staging/contact-mail.config.php',
        dirname(__DIR__) . '/staging/contact-mail.config.php',
    ];

    foreach ($paths as $path) {
        if (is_readable($path)) {
            $config = require $path;
            return is_array($config) ? $config : null;
        }
    }

    return null;
}

function smtp_expect($socket, $codes)
{
    $response = '';
    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;
        if (strlen($line) >= 4 && $line[3] === ' ') {
            break;
        }
    }

    $code = (int)substr($response, 0, 3);
    if (!in_array($code, $codes, true)) {
        throw new RuntimeException('SMTP unexpected response: ' . trim($response));
    }

    return $response;
}

function smtp_command($socket, $command, $codes)
{
    fwrite($socket, $command . "\r\n");
    return smtp_expect($socket, $codes);
}

function smtp_data($value)
{
    $value = preg_replace("/\r\n|\r|\n/", "\r\n", $value) ?? '';
    return preg_replace('/^\./m', '..', $value) ?? $value;
}

function send_smtp_message($config, $to, $subject, $body, $replyTo)
{
    $host = (string)($config['host'] ?? '');
    $port = (int)($config['port'] ?? 587);
    $username = (string)($config['username'] ?? '');
    $password = (string)($config['password'] ?? '');
    $from = (string)($config['from'] ?? FROM_EMAIL);

    if ($host === '' || $username === '' || $password === '') {
        return ['ok' => false, 'transport' => 'smtp', 'error' => 'SMTP config missing required values.'];
    }

    try {
        $socket = stream_socket_client('tcp://' . $host . ':' . $port, $errno, $errstr, 20);
        if (!$socket) {
            throw new RuntimeException($errstr !== '' ? $errstr : 'SMTP connection failed.');
        }

        stream_set_timeout($socket, 20);
        smtp_expect($socket, [220]);
        smtp_command($socket, 'EHLO championautofinance.com', [250]);
        smtp_command($socket, 'STARTTLS', [220]);
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new RuntimeException('SMTP TLS negotiation failed.');
        }
        smtp_command($socket, 'EHLO championautofinance.com', [250]);
        smtp_command($socket, 'AUTH LOGIN', [334]);
        smtp_command($socket, base64_encode($username), [334]);
        smtp_command($socket, base64_encode($password), [235]);
        smtp_command($socket, 'MAIL FROM:<' . $from . '>', [250]);
        smtp_command($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
        smtp_command($socket, 'DATA', [354]);

        $message = implode("\r\n", [
            'From: Champion Auto Finance <' . $from . '>',
            'To: ' . $to,
            'Reply-To: ' . $replyTo,
            'Subject: ' . $subject,
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'X-CAF-Form: contact-us',
            '',
            smtp_data($body),
        ]);

        fwrite($socket, $message . "\r\n.\r\n");
        smtp_expect($socket, [250]);
        smtp_command($socket, 'QUIT', [221]);
        fclose($socket);

        return ['ok' => true, 'transport' => 'smtp', 'error' => null];
    } catch (Throwable $e) {
        if (isset($socket) && is_resource($socket)) {
            fclose($socket);
        }

        return ['ok' => false, 'transport' => 'smtp', 'error' => $e->getMessage()];
    }
}

function log_mail_attempt($lead, $result)
{
    $dir = ensure_storage_dir();
    $file = $dir . '/mail-log-' . gmdate('Y-m') . '.jsonl';
    $entry = [
        'submitted_at' => $lead['submitted_at'],
        'recipient' => TO_EMAIL,
        'from' => FROM_EMAIL,
        'sender_email' => $lead['email'],
        'transport' => is_array($result) ? ($result['transport'] ?? 'unknown') : 'php_mail',
        'mail_returned' => is_array($result) ? (bool)($result['ok'] ?? false) : (bool)$result,
        'transport_error' => is_array($result) ? ($result['error'] ?? null) : null,
        'last_error' => error_get_last(),
    ];

    file_put_contents($file, json_encode($entry, JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_with_status(status_url(false));
}

if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > MAX_POST_BYTES) {
    redirect_with_status(status_url(false));
}

$origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
$allowedOrigins = [
    'https://championautofinance.github.io',
    'https://championautofinance.com',
    'https://www.championautofinance.com',
];
if ($origin !== '' && !in_array($origin, $allowedOrigins, true)) {
    redirect_with_status(status_url(false));
}

if (field('website') !== '') {
    redirect_with_status(status_url(true));
}

$startedAt = (int)field('form_started_at');
if ($startedAt > 0) {
    $age = time() - $startedAt;
    if ($age < 3 || $age > 86400) {
        redirect_with_status(status_url(false));
    }
}

$ip = client_ip();
if (rate_limited($ip)) {
    redirect_with_status(status_url(false));
}

$formSource = clean_text(field('form_source'), 80);
$companyName = clean_text(field('input_4'), 120);
$contactPerson = clean_text(field('input_6'), 120);
$phone = clean_text(field('input_5'), 40);
$firstName = clean_text(field('input_1.3'), 80);
$lastName = clean_text(field('input_1.6'), 80);
$email = strtolower(clean_text(field('input_2'), 254));
$confirmEmail = strtolower(clean_text(field('input_2_2'), 254));
$message = clean_text(field('input_3'), MAX_MESSAGE_LENGTH);
$isDealerPartner = $formSource === 'dealer-partners' || $companyName !== '' || $contactPerson !== '' || $phone !== '';

if ($email === '' && $confirmEmail !== '') {
    $email = $confirmEmail;
}

if ($isDealerPartner) {
    $firstName = $contactPerson !== '' ? $contactPerson : $companyName;
    $lastName = $companyName;
    $message = clean_text(implode("\n", [
        'Dealer partner inquiry',
        '',
        'Company Name: ' . ($companyName !== '' ? $companyName : '(not provided)'),
        'Contact Name: ' . ($contactPerson !== '' ? $contactPerson : '(not provided)'),
        'Email: ' . ($email !== '' ? $email : '(not provided)'),
        'Phone: ' . ($phone !== '' ? $phone : '(not provided)'),
        '',
        'Message:',
        $message,
    ]), 1200);
}

if ($isDealerPartner) {
    if (
        $firstName === '' ||
        $message === '' ||
        ($email === '' && $phone === '') ||
        ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) ||
        ($confirmEmail !== '' && $email !== $confirmEmail)
    ) {
        redirect_with_status(status_url(false));
    }
} else {
    if (
        trim($firstName . ' ' . $lastName) === '' ||
        $email === '' ||
        $message === '' ||
        !filter_var($email, FILTER_VALIDATE_EMAIL) ||
        ($confirmEmail !== '' && $email !== $confirmEmail)
    ) {
        redirect_with_status(status_url(false));
    }
}

$lead = [
    'submitted_at' => gmdate('c'),
    'first_name' => $firstName,
    'last_name' => $lastName,
    'email' => $email,
    'message' => $message,
    'ip' => $ip,
    'user_agent' => clean_text((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 300),
];

save_submission($lead);
$result = send_notification($lead);
log_mail_attempt($lead, $result);

redirect_with_status(status_url(true));
