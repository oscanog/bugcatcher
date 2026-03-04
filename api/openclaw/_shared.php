<?php

require_once dirname(__DIR__, 2) . '/app/openclaw_lib.php';

header('Content-Type: application/json');

try {
    $conn = bugcatcher_db_connection();
} catch (RuntimeException $e) {
    bugcatcher_openclaw_json_response(500, ['error' => $e->getMessage()]);
}
