<?php

declare(strict_types=1);

require_once __DIR__ . '/../data.php';

header('Content-Type: application/json; charset=UTF-8');
http_response_code(410);

echo json_encode([
    'ok' => false,
    'message' => 'Card payments are currently disabled. Please place your order with Cash on Delivery.',
    'code' => 'stripe_disabled',
]);

exit;
