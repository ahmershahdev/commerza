<?php

declare(strict_types=1);

function commerza_get_site_setting(mysqli $con, string $key, string $fallback = ''): string
{
    $stmt = $con->prepare(
        'SELECT setting_val
         FROM site_settings
         WHERE setting_key = ?
         LIMIT 1'
    );

    if (!$stmt) {
        return $fallback;
    }

    $stmt->bind_param('s', $key);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$row) {
        return $fallback;
    }

    $value = trim((string)($row['setting_val'] ?? ''));
    return $value !== '' ? $value : $fallback;
}

function commerza_get_stripe_secret_key(mysqli $con): string
{
    $stored = commerza_get_site_setting($con, 'stripe_secret_key', '');
    if ($stored !== '') {
        return $stored;
    }

    $envCandidates = [
        'STRIPE_SECRET_KEY',
        'COMMERZA_STRIPE_SECRET_KEY',
    ];

    foreach ($envCandidates as $envKey) {
        $value = trim((string)getenv($envKey));
        if ($value !== '') {
            return $value;
        }
    }

    return '';
}

function commerza_get_stripe_publishable_key(mysqli $con): string
{
    $stored = commerza_get_site_setting($con, 'stripe_publishable_key', '');
    if ($stored !== '') {
        return $stored;
    }

    $envCandidates = [
        'STRIPE_PUBLISHABLE_KEY',
        'COMMERZA_STRIPE_PUBLISHABLE_KEY',
    ];

    foreach ($envCandidates as $envKey) {
        $value = trim((string)getenv($envKey));
        if ($value !== '') {
            return $value;
        }
    }

    return '';
}

function commerza_stripe_api_post(string $secretKey, string $url, array $payload): array
{
    $ch = curl_init($url);
    if ($ch === false) {
        return [
            'ok' => false,
            'status' => 0,
            'data' => [],
            'error' => 'Unable to initialize payment request.',
        ];
    }

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $secretKey,
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json',
        ],
    ]);

    $response = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if (!is_string($response) || $response === '') {
        return [
            'ok' => false,
            'status' => $status,
            'data' => [],
            'error' => $curlError !== '' ? $curlError : 'Empty response from payment gateway.',
        ];
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        return [
            'ok' => false,
            'status' => $status,
            'data' => [],
            'error' => 'Invalid response from payment gateway.',
        ];
    }

    $ok = $status >= 200 && $status < 300 && empty($decoded['error']);

    return [
        'ok' => $ok,
        'status' => $status,
        'data' => $decoded,
        'error' => $ok
            ? ''
            : (string)($decoded['error']['message'] ?? 'Payment gateway request failed.'),
    ];
}

function commerza_stripe_api_get(string $secretKey, string $url): array
{
    $ch = curl_init($url);
    if ($ch === false) {
        return [
            'ok' => false,
            'status' => 0,
            'data' => [],
            'error' => 'Unable to initialize payment request.',
        ];
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $secretKey,
            'Accept: application/json',
        ],
    ]);

    $response = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if (!is_string($response) || $response === '') {
        return [
            'ok' => false,
            'status' => $status,
            'data' => [],
            'error' => $curlError !== '' ? $curlError : 'Empty response from payment gateway.',
        ];
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        return [
            'ok' => false,
            'status' => $status,
            'data' => [],
            'error' => 'Invalid response from payment gateway.',
        ];
    }

    $ok = $status >= 200 && $status < 300 && empty($decoded['error']);

    return [
        'ok' => $ok,
        'status' => $status,
        'data' => $decoded,
        'error' => $ok
            ? ''
            : (string)($decoded['error']['message'] ?? 'Payment gateway request failed.'),
    ];
}

function commerza_fetch_stripe_payment_intent(string $secretKey, string $intentId): array
{
    if (!preg_match('/^pi_[A-Za-z0-9]+$/', $intentId)) {
        return [
            'ok' => false,
            'status' => 422,
            'data' => [],
            'error' => 'Invalid Stripe payment intent id.',
        ];
    }

    return commerza_stripe_api_get(
        $secretKey,
        'https://api.stripe.com/v1/payment_intents/' . rawurlencode($intentId)
    );
}
