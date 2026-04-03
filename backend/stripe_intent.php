<?php

declare(strict_types=1);

require_once __DIR__ . '/data.php';
require_once __DIR__ . '/payment_helpers.php';

header('Content-Type: application/json; charset=UTF-8');

function stripe_intent_json(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    stripe_intent_json(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

if (
    empty($_POST['csrf_token']) ||
    empty($_SESSION['csrf_token']) ||
    !hash_equals((string)$_SESSION['csrf_token'], (string)$_POST['csrf_token'])
) {
    stripe_intent_json(['ok' => false, 'message' => 'Forbidden.'], 403);
}

if (!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    stripe_intent_json(['ok' => false, 'message' => 'Please login before checkout.'], 401);
}

$amountPkr = (int)($_POST['amount_pkr'] ?? 0);
$currency = strtolower(trim((string)($_POST['currency'] ?? 'pkr')));

if ($currency !== 'pkr') {
    stripe_intent_json(['ok' => false, 'message' => 'Unsupported currency.'], 422);
}

if ($amountPkr <= 0 || $amountPkr > 10000000) {
    stripe_intent_json(['ok' => false, 'message' => 'Invalid payment amount.'], 422);
}

$secretKey = commerza_get_stripe_secret_key($con);
if ($secretKey === '') {
    stripe_intent_json(['ok' => false, 'message' => 'Stripe is not configured yet.'], 500);
}

$amountSmallest = $amountPkr * 100;
$userId = (int)$_SESSION['user_id'];

$response = commerza_stripe_api_post(
    $secretKey,
    'https://api.stripe.com/v1/payment_intents',
    [
        'amount' => (string)$amountSmallest,
        'currency' => $currency,
        'payment_method_types[]' => 'card',
        'description' => 'Commerza order payment',
        'metadata[user_id]' => (string)$userId,
        'metadata[source]' => 'commerza_cart',
    ]
);

if (!$response['ok']) {
    stripe_intent_json([
        'ok' => false,
        'message' => $response['error'] !== ''
            ? $response['error']
            : 'Unable to create Stripe payment intent.',
    ], 502);
}

$intent = $response['data'];
$intentId = (string)($intent['id'] ?? '');
$clientSecret = (string)($intent['client_secret'] ?? '');

if ($intentId === '' || $clientSecret === '') {
    stripe_intent_json(['ok' => false, 'message' => 'Stripe intent is invalid.'], 502);
}

stripe_intent_json([
    'ok' => true,
    'intent_id' => $intentId,
    'client_secret' => $clientSecret,
]);
