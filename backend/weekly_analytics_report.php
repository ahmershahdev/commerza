<?php

include __DIR__ . '/data.php';
require_once __DIR__ . '/notifications.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden.');
}

$targetDay = null;
if (isset($argv[1]) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$argv[1])) {
    $targetDay = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string)$argv[1] . ' 23:59:59');
}

$sent = commerza_send_weekly_analytics_email($con, $targetDay ?: null);

echo json_encode([
    'ok' => $sent,
    'week_ending' => $targetDay ? $targetDay->format('Y-m-d') : (new DateTimeImmutable('today'))->format('Y-m-d'),
], JSON_UNESCAPED_SLASHES) . PHP_EOL;
