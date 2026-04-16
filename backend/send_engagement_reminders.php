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

$jobKey = 'engagement_reminders_dispatch';
$periodKey = 'global';
if (!commerza_notifications_acquire_job_lock($con, $jobKey, $periodKey, 0)) {
    echo json_encode([
        'ok' => true,
        'busy' => true,
        'threshold_minutes' => $minutes,
        'processed' => 0,
        'sent' => 0,
        'failed' => 0,
    ], JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit;
}

try {
    $result = commerza_send_pending_engagement_reminders($con, $minutes);
} finally {
    commerza_notifications_release_job_lock($con, $jobKey, $periodKey);
}

echo json_encode([
    'ok' => true,
    'threshold_minutes' => $minutes,
    'processed' => (int)($result['processed'] ?? 0),
    'sent' => (int)($result['sent'] ?? 0),
    'failed' => (int)($result['failed'] ?? 0),
], JSON_UNESCAPED_SLASHES) . PHP_EOL;
