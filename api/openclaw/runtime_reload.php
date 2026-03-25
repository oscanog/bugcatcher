<?php

require_once __DIR__ . '/_shared.php';

bugcatcher_openclaw_require_internal_request();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    bugcatcher_openclaw_json_response(405, ['error' => 'Method not allowed.']);
}

bugcatcher_openclaw_json_response(410, ['error' => 'OpenClaw Discord runtime reload has been retired.']);
