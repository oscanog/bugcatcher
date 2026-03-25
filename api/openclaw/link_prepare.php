<?php

require_once dirname(__DIR__, 2) . '/db.php';
require_once dirname(__DIR__, 2) . '/app/openclaw_lib.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    bugcatcher_openclaw_json_response(405, ['error' => 'POST required.']);
}

bugcatcher_openclaw_json_response(410, ['error' => 'Discord account linking has been removed.']);
