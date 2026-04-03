<?php

include __DIR__ . '/data.php';
require_once __DIR__ . '/notifications.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden.');
}

$minutes = 180;
if (isset($argv[1])) {
    $candidate = (int)$argv[1];
    if ($candidate >= 30 && $candidate <= 10080) {
        $minutes = $candidate;
    }
}

$result = commerza_send_pending_engagement_reminders($con, $minutes);

echo json_encode([
    'ok' => true,
    'threshold_minutes' => $minutes,
    'processed' => (int)($result['processed'] ?? 0),
    'sent' => (int)($result['sent'] ?? 0),
    'failed' => (int)($result['failed'] ?? 0),
], JSON_UNESCAPED_SLASHES) . PHP_EOL;
