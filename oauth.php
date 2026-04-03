<?php
declare(strict_types=1);

// Friendly OAuth entrypoint for localhost/commerza/oauth.php usage.
$provider = strtolower(trim((string)($_GET['provider'] ?? 'google')));
$mode = strtolower(trim((string)($_GET['mode'] ?? 'login')));

if (!in_array($provider, ['google', 'facebook'], true)) {
    $provider = 'google';
}

if (!in_array($mode, ['login', 'signup'], true)) {
    $mode = 'login';
}

$query = $_GET;
$query['provider'] = $provider;
$query['mode'] = $mode;

header('Location: backend/oauth.php?' . http_build_query($query));
exit;
