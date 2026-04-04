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

function commerza_mail_smtp_config(): array
{
    static $config = null;

    if (is_array($config)) {
        return $config;
    }

    $host = trim(commerza_mail_env_value(['COMMERZA_SMTP_HOST'], trim((string)ini_get('SMTP'))));
    $port = (int)commerza_mail_env_value(['COMMERZA_SMTP_PORT'], trim((string)ini_get('smtp_port')));
    if ($port <= 0) {
        $port = 587;
    }

    $encryption = strtolower(trim(commerza_mail_env_value(['COMMERZA_SMTP_ENCRYPTION'], 'tls')));
    if (!in_array($encryption, ['tls', 'ssl', 'none'], true)) {
        $encryption = 'tls';
    }

    $username = trim(commerza_mail_env_value(['COMMERZA_SMTP_USERNAME'], ''));
    $password = trim(commerza_mail_env_value(['COMMERZA_SMTP_PASSWORD'], ''));
    $fromEmail = trim(commerza_mail_env_value(['COMMERZA_SMTP_FROM_EMAIL', 'COMMERZA_SMTP_USERNAME'], ''));
    $fromName = trim(commerza_mail_env_value(['COMMERZA_SMTP_FROM_NAME'], 'Commerza'));
    $timeout = (int)commerza_mail_env_value(['COMMERZA_SMTP_TIMEOUT'], '20');
    if ($timeout < 5) {
        $timeout = 5;
    }

    $config = [
        'host' => $host,
        'port' => $port,
        'encryption' => $encryption,
        'username' => $username,
        'password' => $password,
        'from_email' => $fromEmail,
        'from_name' => $fromName,
        'timeout' => $timeout,
    ];

    return $config;
}

function commerza_mail_default_sender(): array
{
    $smtp = commerza_mail_smtp_config();

    return [
        'email' => trim((string)($smtp['from_email'] ?? '')),
        'name' => trim((string)($smtp['from_name'] ?? 'Commerza')),
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
    $smtp = commerza_mail_smtp_config();
    $hasSmtpHost = trim((string)($smtp['host'] ?? '')) !== '';
    $hasFrom = filter_var((string)($smtp['from_email'] ?? ''), FILTER_VALIDATE_EMAIL) !== false;

    if ($hasSmtpHost && $hasFrom) {
        return true;
    }

    return commerza_mail_native_transport_ready();
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
        if (!commerza_mail_smtp_write($socket, "AUTH LOGIN\r\n") || !commerza_mail_smtp_expect($socket, [334], $errorMessage)) {
            fclose($socket);
            return false;
        }

        if (!commerza_mail_smtp_write($socket, base64_encode($username) . "\r\n") || !commerza_mail_smtp_expect($socket, [334], $errorMessage)) {
            fclose($socket);
            return false;
        }

        if (!commerza_mail_smtp_write($socket, base64_encode($password) . "\r\n") || !commerza_mail_smtp_expect($socket, [235], $errorMessage)) {
            fclose($socket);
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

    $smtp = commerza_mail_smtp_config();

    $safeFromName = commerza_mail_clean_header_value($fromName);
    $safeFromEmail = trim($fromEmail);

    if (filter_var((string)($smtp['from_email'] ?? ''), FILTER_VALIDATE_EMAIL)) {
        $safeFromEmail = (string)$smtp['from_email'];
    }

    $smtpFromName = commerza_mail_clean_header_value((string)($smtp['from_name'] ?? ''));
    if ($smtpFromName !== '') {
        $safeFromName = $smtpFromName;
    }

    if (!filter_var($safeFromEmail, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = 'Invalid sender email configuration.';
        return false;
    }

    $hasSmtpHost = trim((string)($smtp['host'] ?? '')) !== '';
    if ($hasSmtpHost) {
        $smtpError = null;
        if (commerza_mail_smtp_send($smtp, $toEmail, $subject, $htmlBody, $safeFromEmail, $safeFromName, $smtpError)) {
            return true;
        }

        if (!commerza_mail_native_transport_ready()) {
            $errorMessage = $smtpError ?: 'SMTP delivery failed.';
            return false;
        }
    }

    if (!commerza_mail_native_transport_ready()) {
        $errorMessage = 'Email service is not configured on this server.';
        return false;
    }

    $headers = [];
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-type: text/html; charset=UTF-8';
    $headers[] = 'From: ' . $safeFromName . ' <' . $safeFromEmail . '>';
    $headers[] = 'Reply-To: ' . $safeFromEmail;
    $headers[] = 'X-Mailer: PHP/' . phpversion();

    $sent = @mail($toEmail, $subject, $htmlBody, implode("\r\n", $headers));

    if (!$sent) {
        $errorMessage = 'Mail delivery failed. Check SMTP/sendmail settings.';
        return false;
    }

    return true;
}
