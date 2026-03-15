<?php
require_once __DIR__ . '/mail/config.php';
require_once __DIR__ . '/mail/messages.php';
require_once __DIR__ . '/mail/preview_mailer.php';
require_once __DIR__ . '/mail/symfony_mailer.php';

function bugcatcher_mail_send_message(array $message): void
{
    $configError = bugcatcher_mail_validate_config();
    if ($configError !== null) {
        throw new RuntimeException($configError);
    }

    $toEmail = trim((string) ($message['to']['email'] ?? ''));
    if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Recipient email is invalid.');
    }

    $config = bugcatcher_mail_normalized_config();
    if ($config['mailer'] === 'preview') {
        bugcatcher_mail_preview_store($message);
        return;
    }

    bugcatcher_mail_send_via_symfony($message, $config);
}

function bugcatcher_mail_send(string $toEmail, string $toName, string $subject, string $htmlBody, string $textBody = ''): void
{
    bugcatcher_mail_send_message(
        bugcatcher_mail_message($toEmail, $toName, $subject, $htmlBody, $textBody)
    );
}
