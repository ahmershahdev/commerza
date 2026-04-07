<?php

declare(strict_types=1);

// Public OAuth entrypoint; keeps browser on /oauth.php so backend route protection remains intact.
$provider = strtolower(trim((string)($_GET['provider'] ?? 'google')));
$mode = strtolower(trim((string)($_GET['mode'] ?? 'login')));

if (!in_array($provider, ['google', 'facebook'], true)) {
    $provider = 'google';
}

if (!in_array($mode, ['login', 'signup'], true)) {
    $mode = 'login';
}

$_GET['provider'] = $provider;
$_GET['mode'] = $mode;
$_REQUEST['provider'] = $provider;
$_REQUEST['mode'] = $mode;

require __DIR__ . '/backend/oauth.php';
