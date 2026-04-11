<?php

include __DIR__ . '/../data.php';
require_once __DIR__ . '/../helpers/notifications.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden.');
}

$targetMonth = null;
if (isset($argv[1]) && preg_match('/^\d{4}-\d{2}$/', (string)$argv[1])) {
    $targetMonth = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string)$argv[1] . '-01 00:00:00');
}

$sent = commerza_send_monthly_profit_email($con, $targetMonth ?: null);

echo json_encode([
    'ok' => $sent,
    'month' => $targetMonth ? $targetMonth->format('Y-m') : (new DateTimeImmutable('first day of last month'))->format('Y-m'),
], JSON_UNESCAPED_SLASHES) . PHP_EOL;
