<?php

require_once __DIR__ . '/_shared.php';

bugcatcher_openclaw_require_internal_request();
$payload = bugcatcher_openclaw_json_request_body();
$code = strtoupper(trim((string) ($payload['code'] ?? '')));
$discordUserId = trim((string) ($payload['discord_user_id'] ?? ''));
$discordUsername = trim((string) ($payload['discord_username'] ?? ''));
$discordGlobalName = trim((string) ($payload['discord_global_name'] ?? ''));

if ($code === '' || $discordUserId === '') {
    bugcatcher_openclaw_json_response(422, ['error' => 'code and discord_user_id are required.']);
}

$link = bugcatcher_openclaw_fetch_user_link_by_valid_code($conn, $code);
if (!$link) {
    bugcatcher_openclaw_json_response(404, ['error' => 'The link code is invalid or expired.']);
}

bugcatcher_openclaw_confirm_link($conn, (int) $link['user_id'], $discordUserId, $discordUsername, $discordGlobalName);
bugcatcher_openclaw_json_response(200, [
    'linked' => true,
    'user' => [
        'id' => (int) $link['user_id'],
        'username' => $link['username'],
        'email' => $link['email'],
    ],
]);
