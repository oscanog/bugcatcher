<?php

require_once __DIR__ . '/_shared.php';

bugcatcher_openclaw_require_internal_request();
$payload = bugcatcher_openclaw_json_request_body();
$discordUserId = trim((string) ($payload['discord_user_id'] ?? ''));

if ($discordUserId === '') {
    bugcatcher_openclaw_json_response(422, ['error' => 'discord_user_id is required.']);
}

$context = bugcatcher_openclaw_context_for_discord_user($conn, $discordUserId);
if (!$context) {
    bugcatcher_openclaw_json_response(404, ['error' => 'Discord user is not linked to BugCatcher.']);
}

bugcatcher_openclaw_json_response(200, $context);
