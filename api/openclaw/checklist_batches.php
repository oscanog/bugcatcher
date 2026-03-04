<?php

require_once __DIR__ . '/_shared.php';

bugcatcher_openclaw_require_internal_request();
$payload = bugcatcher_openclaw_json_request_body();

try {
    $result = bugcatcher_openclaw_create_batch_from_payload($conn, $payload);
} catch (Throwable $e) {
    bugcatcher_openclaw_json_response(422, ['error' => $e->getMessage()]);
}

bugcatcher_openclaw_json_response(201, $result);
