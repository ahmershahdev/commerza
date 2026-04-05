<?php

function commerza_mail_env_value(array $keys, string $fallback = ''): string
{
    foreach ($keys as $key) {
        $value = trim((string)getenv((string)$key));
        if ($value !== '') {
            return $value;
        }
    }

    return $fallback;
}

function commerza_mail_normalize_auth_mode(string $mode): string
{
    $normalized = strtolower(trim($mode));
    if (in_array($normalized, ['login', 'plain', 'auto'], true)) {
        return $normalized;
    }

    return 'login';
}

function commerza_mail_normalize_encryption(string $encryption): string
{
    $normalized = strtolower(trim($encryption));
    if (in_array($normalized, ['tls', 'ssl', 'none'], true)) {
        return $normalized;
    }

    return 'tls';
}

function commerza_mail_support_email(): string
{
    $configured = trim(commerza_mail_env_value([
        'COMMERZA_SUPPORT_EMAIL',
        'COMMERZA_FALLBACK_FROM_EMAIL',
    ], 'support@ahmershah.dev'));

    if (filter_var($configured, FILTER_VALIDATE_EMAIL)) {
        return $configured;
    }

    return 'support@ahmershah.dev';
}

function commerza_mail_primary_smtp_config(): array
{
    $port = (int)commerza_mail_env_value(['COMMERZA_SMTP_PRIMARY_PORT'], '587');
    if ($port <= 0) {
        $port = 587;
    }

    $timeout = (int)commerza_mail_env_value([
        'COMMERZA_SMTP_PRIMARY_TIMEOUT',
        'COMMERZA_SMTP_TIMEOUT',
    ], '20');
    if ($timeout < 5) {
        $timeout = 5;
    }

    return [
        'label' => 'sender_net_primary',
        'host' => trim(commerza_mail_env_value(['COMMERZA_SMTP_PRIMARY_HOST'], 'smtp.sender.net')),
        'port' => $port,
        'encryption' => commerza_mail_normalize_encryption(commerza_mail_env_value(['COMMERZA_SMTP_PRIMARY_ENCRYPTION'], 'tls')),
        'auth_mode' => commerza_mail_normalize_auth_mode(commerza_mail_env_value(['COMMERZA_SMTP_PRIMARY_AUTH'], 'login')),
        'username' => trim(commerza_mail_env_value(['COMMERZA_SMTP_PRIMARY_USERNAME'], 'commerza_ejYAl5')),
        'password' => trim(commerza_mail_env_value(['COMMERZA_SMTP_PRIMARY_PASSWORD'], 'd4vuLBgsaiI9FYpJ99F6WaX9hW0hi5uX')),
        'from_email' => trim(commerza_mail_env_value([
            'COMMERZA_SMTP_PRIMARY_FROM_EMAIL',
            'COMMERZA_SUPPORT_EMAIL',
        ], commerza_mail_support_email())),
        'from_name' => trim(commerza_mail_env_value([
            'COMMERZA_SMTP_PRIMARY_FROM_NAME',
            'COMMERZA_SMTP_FROM_NAME',
        ], 'Commerza')),
        'timeout' => $timeout,
    ];
}

function commerza_mail_secondary_smtp_config(): array
{
    $port = (int)commerza_mail_env_value([
        'COMMERZA_SMTP_SECONDARY_PORT',
        'COMMERZA_SMTP_PORT',
    ], '587');
    if ($port <= 0) {
        $port = 587;
    }

    $timeout = (int)commerza_mail_env_value([
        'COMMERZA_SMTP_SECONDARY_TIMEOUT',
        'COMMERZA_SMTP_TIMEOUT',
    ], '20');
    if ($timeout < 5) {
        $timeout = 5;
    }

    return [
        'label' => 'gmail_fallback',
        'host' => trim(commerza_mail_env_value([
            'COMMERZA_SMTP_SECONDARY_HOST',
            'COMMERZA_SMTP_HOST',
        ], 'smtp.gmail.com')),
        'port' => $port,
        'encryption' => commerza_mail_normalize_encryption(commerza_mail_env_value([
            'COMMERZA_SMTP_SECONDARY_ENCRYPTION',
            'COMMERZA_SMTP_ENCRYPTION',
        ], 'tls')),
        'auth_mode' => commerza_mail_normalize_auth_mode(commerza_mail_env_value([
            'COMMERZA_SMTP_SECONDARY_AUTH',
            'COMMERZA_SMTP_AUTH',
        ], 'login')),
        'username' => trim(commerza_mail_env_value([
            'COMMERZA_SMTP_SECONDARY_USERNAME',
            'COMMERZA_SMTP_USERNAME',
        ], '')),
        'password' => trim(commerza_mail_env_value([
            'COMMERZA_SMTP_SECONDARY_PASSWORD',
            'COMMERZA_SMTP_PASSWORD',
        ], '')),
        'from_email' => trim(commerza_mail_env_value([
            'COMMERZA_SMTP_SECONDARY_FROM_EMAIL',
            'COMMERZA_SUPPORT_EMAIL',
        ], commerza_mail_support_email())),
        'from_name' => trim(commerza_mail_env_value([
            'COMMERZA_SMTP_SECONDARY_FROM_NAME',
            'COMMERZA_SMTP_FROM_NAME',
        ], 'Commerza')),
        'timeout' => $timeout,
    ];
}

function commerza_mail_smtp_configs(): array
{
    static $configs = null;

    if (is_array($configs)) {
        return $configs;
    }

    $configs = [];
    $primary = commerza_mail_primary_smtp_config();
    $secondary = commerza_mail_secondary_smtp_config();

    if (trim((string)($primary['host'] ?? '')) !== '') {
        $configs[] = $primary;
    }

    $secondaryHost = strtolower(trim((string)($secondary['host'] ?? '')));
    $primaryHost = strtolower(trim((string)($primary['host'] ?? '')));
    $secondaryUser = strtolower(trim((string)($secondary['username'] ?? '')));
    $primaryUser = strtolower(trim((string)($primary['username'] ?? '')));
    $isDuplicate =
        $secondaryHost !== '' &&
        $primaryHost !== '' &&
        $secondaryHost === $primaryHost &&
        $secondaryUser !== '' &&
        $primaryUser !== '' &&
        $secondaryUser === $primaryUser;

    if (!$isDuplicate && $secondaryHost !== '' && trim((string)($secondary['username'] ?? '')) !== '') {
        $configs[] = $secondary;
    }

    return $configs;
}

function commerza_mail_smtp_config(): array
{
    $configs = commerza_mail_smtp_configs();
    if (!empty($configs[0]) && is_array($configs[0])) {
        return $configs[0];
    }

    return [];
}

function commerza_mail_default_sender(): array
{
    $smtp = commerza_mail_smtp_config();
    $email = trim((string)($smtp['from_email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $email = commerza_mail_support_email();
    }

    $name = trim((string)($smtp['from_name'] ?? 'Commerza'));
    if ($name === '') {
        $name = 'Commerza';
    }

    return [
        'email' => $email,
        'name' => $name,
    ];
}

function commerza_mail_native_transport_ready(): bool
{
    $sendmailPath = trim((string)ini_get('sendmail_path'));
    if ($sendmailPath !== '') {
        return true;
    }

    $smtp = strtolower(trim((string)ini_get('SMTP')));
    if ($smtp === '' || $smtp === 'localhost' || $smtp === '127.0.0.1') {
        return false;
    }

    return true;
}

function commerza_mail_transport_ready(): bool
{
    foreach (commerza_mail_smtp_configs() as $smtp) {
        $hasSmtpHost = trim((string)($smtp['host'] ?? '')) !== '';
        $hasSmtpUser = trim((string)($smtp['username'] ?? '')) !== '';
        if ($hasSmtpHost && $hasSmtpUser) {
            return true;
        }
    }

    return false;
}

function commerza_mail_clean_header_value(string $value): string
{
    return trim((string)preg_replace('/[\r\n]+/', ' ', $value));
}

function commerza_mail_rfc2822_date(): string
{
    return gmdate('D, d M Y H:i:s') . ' +0000';
}

function commerza_mail_build_rfc_message(string $toEmail, string $fromEmail, string $fromName, string $subject, string $htmlBody): string
{
    $safeFromName = commerza_mail_clean_header_value($fromName);
    $safeSubject = commerza_mail_clean_header_value($subject);

    $encodedSubject = '=?UTF-8?B?' . base64_encode($safeSubject) . '?=';
    $encodedBody = chunk_split(base64_encode($htmlBody), 76, "\r\n");

    $headers = [
        'Date: ' . commerza_mail_rfc2822_date(),
        'From: ' . $safeFromName . ' <' . $fromEmail . '>',
        'To: <' . $toEmail . '>',
        'Subject: ' . $encodedSubject,
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'Content-Transfer-Encoding: base64',
        'Reply-To: ' . $fromEmail,
        'X-Mailer: Commerza SMTP Mailer',
    ];

    return implode("\r\n", $headers) . "\r\n\r\n" . $encodedBody;
}

function commerza_mail_smtp_read_line($socket): string
{
    $line = fgets($socket, 1024);
    return is_string($line) ? $line : '';
}

function commerza_mail_smtp_expect($socket, array $acceptedCodes, ?string &$errorMessage = null): bool
{
    $response = '';
    $code = 0;

    while (!feof($socket)) {
        $line = commerza_mail_smtp_read_line($socket);
        if ($line === '') {
            break;
        }

        $response .= $line;
        if (strlen($line) < 4) {
            continue;
        }

        $prefix = substr($line, 0, 3);
        if (!ctype_digit($prefix)) {
            continue;
        }

        $code = (int)$prefix;
        if ($line[3] === ' ') {
            break;
        }
    }

    if ($code > 0 && in_array($code, $acceptedCodes, true)) {
        return true;
    }

    $errorMessage = 'SMTP server rejected command.';
    if (trim($response) !== '') {
        $errorMessage .= ' Response: ' . trim($response);
    }

    return false;
}

function commerza_mail_smtp_write($socket, string $command): bool
{
    $bytes = fwrite($socket, $command);
    return is_int($bytes) && $bytes === strlen($command);
}

function commerza_mail_tls_crypto_method(): int
{
    $methods = 0;

    if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
        $methods |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
    }

    if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
        $methods |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
    }

    if ($methods !== 0) {
        return $methods;
    }

    return STREAM_CRYPTO_METHOD_TLS_CLIENT;
}

function commerza_mail_smtp_send(
    array $smtp,
    string $toEmail,
    string $subject,
    string $htmlBody,
    string $fromEmail,
    string $fromName,
    ?string &$errorMessage = null
): bool {
    $host = trim((string)($smtp['host'] ?? ''));
    $port = (int)($smtp['port'] ?? 0);
    $encryption = strtolower(trim((string)($smtp['encryption'] ?? 'tls')));
    $username = trim((string)($smtp['username'] ?? ''));
    $password = trim((string)($smtp['password'] ?? ''));
    $timeout = (int)($smtp['timeout'] ?? 20);
    $authMode = commerza_mail_normalize_auth_mode((string)($smtp['auth_mode'] ?? 'login'));

    if ($host === '' || $port <= 0) {
        $errorMessage = 'SMTP host/port configuration is missing.';
        return false;
    }

    $remote = ($encryption === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
    $errno = 0;
    $errstr = '';

    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'allow_self_signed' => false,
            'SNI_enabled' => true,
            'peer_name' => $host,
        ],
    ]);

    $socket = @stream_socket_client($remote, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
    if (!is_resource($socket)) {
        $errorMessage = 'Unable to connect to SMTP server (' . $host . ':' . $port . ').';
        return false;
    }

    stream_set_timeout($socket, $timeout);

    if (!commerza_mail_smtp_expect($socket, [220], $errorMessage)) {
        fclose($socket);
        return false;
    }

    $localHost = trim((string)($_SERVER['SERVER_NAME'] ?? 'localhost'));
    if ($localHost === '') {
        $localHost = 'localhost';
    }

    if (!commerza_mail_smtp_write($socket, 'EHLO ' . $localHost . "\r\n") || !commerza_mail_smtp_expect($socket, [250], $errorMessage)) {
        fclose($socket);
        return false;
    }

    if ($encryption === 'tls') {
        if (!commerza_mail_smtp_write($socket, "STARTTLS\r\n") || !commerza_mail_smtp_expect($socket, [220], $errorMessage)) {
            fclose($socket);
            return false;
        }

        $cryptoEnabled = stream_socket_enable_crypto($socket, true, commerza_mail_tls_crypto_method());
        if ($cryptoEnabled !== true) {
            fclose($socket);
            $errorMessage = 'Unable to enable SMTP TLS encryption.';
            return false;
        }

        if (!commerza_mail_smtp_write($socket, 'EHLO ' . $localHost . "\r\n") || !commerza_mail_smtp_expect($socket, [250], $errorMessage)) {
            fclose($socket);
            return false;
        }
    }

    if ($username !== '' || $password !== '') {
        $authed = false;

        $tryLogin = static function () use ($socket, $username, $password, &$errorMessage): bool {
            if (!commerza_mail_smtp_write($socket, "AUTH LOGIN\r\n") || !commerza_mail_smtp_expect($socket, [334], $errorMessage)) {
                return false;
            }

            if (!commerza_mail_smtp_write($socket, base64_encode($username) . "\r\n") || !commerza_mail_smtp_expect($socket, [334], $errorMessage)) {
                return false;
            }

            return commerza_mail_smtp_write($socket, base64_encode($password) . "\r\n")
                && commerza_mail_smtp_expect($socket, [235], $errorMessage);
        };

        $tryPlain = static function () use ($socket, $username, $password, &$errorMessage): bool {
            $payload = base64_encode("\0" . $username . "\0" . $password);
            return commerza_mail_smtp_write($socket, "AUTH PLAIN " . $payload . "\r\n")
                && commerza_mail_smtp_expect($socket, [235], $errorMessage);
        };

        if ($authMode === 'plain') {
            $authed = $tryPlain();
        } elseif ($authMode === 'auto') {
            $authed = $tryLogin();
            if (!$authed) {
                $authed = $tryPlain();
            }
        } else {
            $authed = $tryLogin();
        }

        if (!$authed) {
            fclose($socket);
            $errorMessage = ($errorMessage ? $errorMessage . ' ' : '') . 'SMTP authentication failed.';
            return false;
        }
    }

    if (!commerza_mail_smtp_write($socket, 'MAIL FROM:<' . $fromEmail . ">\r\n") || !commerza_mail_smtp_expect($socket, [250], $errorMessage)) {
        fclose($socket);
        return false;
    }

    if (!commerza_mail_smtp_write($socket, 'RCPT TO:<' . $toEmail . ">\r\n") || !commerza_mail_smtp_expect($socket, [250, 251], $errorMessage)) {
        fclose($socket);
        return false;
    }

    if (!commerza_mail_smtp_write($socket, "DATA\r\n") || !commerza_mail_smtp_expect($socket, [354], $errorMessage)) {
        fclose($socket);
        return false;
    }

    $message = commerza_mail_build_rfc_message($toEmail, $fromEmail, $fromName, $subject, $htmlBody);
    $message = str_replace("\n.", "\n..", str_replace("\r\n", "\n", $message));
    $message = str_replace("\n", "\r\n", $message);

    if (!commerza_mail_smtp_write($socket, $message . "\r\n.\r\n") || !commerza_mail_smtp_expect($socket, [250], $errorMessage)) {
        fclose($socket);
        return false;
    }

    commerza_mail_smtp_write($socket, "QUIT\r\n");
    fclose($socket);

    return true;
}

function commerza_mail_error_is_rate_limited(string $error): bool
{
    $needle = strtolower($error);
    return preg_match('/\b(421|429|450|451|452|454|4\.7\.0|5\.7\.1)\b/', $needle) === 1
        || strpos($needle, 'rate limit') !== false
        || strpos($needle, 'too many') !== false
        || strpos($needle, 'quota') !== false
        || strpos($needle, 'limit exceeded') !== false;
}

function commerza_mail_error_is_auth(string $error): bool
{
    $needle = strtolower($error);
    return strpos($needle, 'auth') !== false
        || strpos($needle, 'username and password not accepted') !== false
        || strpos($needle, '535') !== false
        || strpos($needle, 'invalid login') !== false;
}

function commerza_send_html_mail(string $toEmail, string $subject, string $htmlBody, string $fromEmail, string $fromName, ?string &$errorMessage = null): bool
{
    $toEmail = trim($toEmail);
    $subject = trim($subject);

    if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = 'Invalid recipient email.';
        return false;
    }

    if ($subject === '') {
        $errorMessage = 'Email subject is required.';
        return false;
    }

    $smtpConfigs = commerza_mail_smtp_configs();

    $safeFromName = commerza_mail_clean_header_value($fromName);
    $safeFromEmail = trim($fromEmail);

    if (empty($smtpConfigs)) {
        $errorMessage = 'Email service is not configured on this server.';
        return false;
    }

    $providerErrors = [];
    $hasRateLimitError = false;
    $hasAuthError = false;

    foreach ($smtpConfigs as $smtp) {
        if (!is_array($smtp)) {
            continue;
        }

        $providerFromEmail = trim((string)($smtp['from_email'] ?? ''));
        $providerFromName = commerza_mail_clean_header_value((string)($smtp['from_name'] ?? ''));

        if (filter_var($providerFromEmail, FILTER_VALIDATE_EMAIL)) {
            $safeFromEmail = $providerFromEmail;
        }

        if ($providerFromName !== '') {
            $safeFromName = $providerFromName;
        }

        if (!filter_var($safeFromEmail, FILTER_VALIDATE_EMAIL)) {
            $providerErrors[] = [
                'provider' => (string)($smtp['label'] ?? 'smtp_provider'),
                'error' => 'Invalid sender email configuration.',
            ];
            continue;
        }

        $smtpError = null;
        if (commerza_mail_smtp_send($smtp, $toEmail, $subject, $htmlBody, $safeFromEmail, $safeFromName, $smtpError)) {
            return true;
        }

        $smtpError = trim((string)$smtpError);
        if ($smtpError === '') {
            $smtpError = 'SMTP delivery failed.';
        }

        $providerErrors[] = [
            'provider' => (string)($smtp['label'] ?? 'smtp_provider'),
            'error' => $smtpError,
        ];

        if (commerza_mail_error_is_rate_limited($smtpError)) {
            $hasRateLimitError = true;
        }

        if (commerza_mail_error_is_auth($smtpError)) {
            $hasAuthError = true;
        }
    }

    if ($hasRateLimitError) {
        $errorMessage = 'Email API limit is reached. Please try another method or retry later.';
        return false;
    }

    if ($hasAuthError) {
        $errorMessage = 'Email service authentication failed. Please contact support.';
        return false;
    }

    if (!empty($providerErrors)) {
        $errorMessage = 'Unable to send email right now. Please try another method.';
        return false;
    }

    $errorMessage = 'Unable to send email right now. Please try another method.';
    return false;
}
