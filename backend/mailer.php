<?php

function commerza_mail_transport_ready(): bool
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

    if (!commerza_mail_transport_ready()) {
        $errorMessage = 'Email service is not configured on this server.';
        return false;
    }

    $safeFromName = trim(preg_replace('/[\r\n]+/', ' ', $fromName));
    $safeFromEmail = trim($fromEmail);

    if (!filter_var($safeFromEmail, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = 'Invalid sender email configuration.';
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
