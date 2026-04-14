<?php

declare(strict_types=1);

require_once __DIR__ . '/../data.php';

header('Content-Type: application/json; charset=UTF-8');
http_response_code(410);

echo json_encode([
    'ok' => false,
    'message' => 'Direct intent endpoint is disabled. Stripe intents are created through checkout (cart.php action=create_stripe_intent).',
    'code' => 'stripe_endpoint_deprecated',
]);

exit;
