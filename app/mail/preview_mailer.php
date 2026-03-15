<?php
require_once __DIR__ . '/config.php';

function bugcatcher_mail_preview_messages(): array
{
    bugcatcher_start_session();
    $messages = $_SESSION[BUGCATCHER_MAIL_PREVIEW_SESSION_KEY] ?? [];
    return is_array($messages) ? $messages : [];
}

function bugcatcher_mail_preview_store(array $message): void
{
    bugcatcher_start_session();

    $messages = bugcatcher_mail_preview_messages();
    $messages[] = $message + ['sent_at' => gmdate('c')];

    if (count($messages) > 20) {
        $messages = array_slice($messages, -20);
    }

    $_SESSION[BUGCATCHER_MAIL_PREVIEW_SESSION_KEY] = array_values($messages);
}

function bugcatcher_mail_preview_clear(string $tag = '', string $recipientEmail = ''): void
{
    bugcatcher_start_session();
    $messages = bugcatcher_mail_preview_messages();
    if ($messages === []) {
        return;
    }

    $normalizedRecipient = strtolower(trim($recipientEmail));
    $filtered = array_values(array_filter($messages, static function (array $message) use ($tag, $normalizedRecipient): bool {
        $messageTag = trim((string) ($message['tag'] ?? ''));
        $messageRecipient = strtolower(trim((string) ($message['to']['email'] ?? '')));

        if ($tag !== '' && $messageTag !== $tag) {
            return true;
        }

        if ($normalizedRecipient !== '' && $messageRecipient !== $normalizedRecipient) {
            return true;
        }

        return false;
    }));

    if ($filtered === []) {
        unset($_SESSION[BUGCATCHER_MAIL_PREVIEW_SESSION_KEY]);
        return;
    }

    $_SESSION[BUGCATCHER_MAIL_PREVIEW_SESSION_KEY] = $filtered;
}

function bugcatcher_mail_preview_latest_message(string $tag = '', string $recipientEmail = ''): ?array
{
    $messages = array_reverse(bugcatcher_mail_preview_messages());
    $normalizedRecipient = strtolower(trim($recipientEmail));

    foreach ($messages as $message) {
        if (!is_array($message)) {
            continue;
        }

        $messageTag = trim((string) ($message['tag'] ?? ''));
        if ($tag !== '' && $messageTag !== $tag) {
            continue;
        }

        $messageRecipient = strtolower(trim((string) ($message['to']['email'] ?? '')));
        if ($normalizedRecipient !== '' && $messageRecipient !== $normalizedRecipient) {
            continue;
        }

        return $message;
    }

    return null;
}
