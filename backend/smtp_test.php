<?php

declare(strict_types=1);

require __DIR__ . '/data.php';
require_once __DIR__ . '/mailer.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden.');
}

$recipient = isset($argv[1]) ? trim((string)$argv[1]) : '';
if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
    $recipient = trim((string)(getenv('COMMERZA_SMTP_FROM_EMAIL') ?: getenv('COMMERZA_SMTP_USERNAME') ?: ''));
}

if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        'ok' => false,
        'message' => 'Provide a recipient email as first argument or set COMMERZA_SMTP_FROM_EMAIL.',
    ], JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(1);
}

$error = null;
$ok = commerza_send_html_mail(
    $recipient,
    'Commerza SMTP test',
    '<p>SMTP test message from Commerza at ' . htmlspecialchars(date('Y-m-d H:i:s'), ENT_QUOTES, 'UTF-8') . '</p>',
    $recipient,
    'Commerza SMTP Test',
    $error
);

echo json_encode([
    'ok' => (bool)$ok,
    'recipient' => $recipient,
    'error' => $error,
], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
