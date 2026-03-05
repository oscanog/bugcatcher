<?php

require_once __DIR__ . '/_shared.php';

checklist_api_require_methods(['POST']);
$payload = checklist_api_json_body(true);

$itemId = checklist_api_get_int($payload, 'item_id');
if ($itemId <= 0) {
    checklist_api_json_error(422, 'invalid_item', 'item_id is required.');
}

$status = trim((string) ($payload['status'] ?? ''));
if ($status === '') {
    checklist_api_json_error(422, 'validation_error', 'status is required.');
}

$item = checklist_api_change_item_status($conn, $context, $itemId, $status);
checklist_api_json_response(200, [
    'item' => $item,
]);

