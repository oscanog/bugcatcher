<?php

require_once __DIR__ . '/_shared.php';

checklist_api_require_methods(['POST']);
checklist_api_require_manager($context);

$payload = checklist_api_json_body(true);
$item = checklist_api_create_item($conn, $context, $payload);

checklist_api_json_response(201, [
    'item' => $item,
]);

