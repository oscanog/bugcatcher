<?php

require_once dirname(__DIR__, 2) . '/db.php';
require_once dirname(__DIR__, 2) . '/app/openclaw_lib.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    bugcatcher_openclaw_json_response(405, ['error' => 'POST required.']);
}

$code = bugcatcher_openclaw_generate_link_code();
bugcatcher_openclaw_store_link_code($conn, $current_user_id, $code);

bugcatcher_openclaw_json_response(200, [
    'code' => $code,
    'expires_in_seconds' => 600,
]);
