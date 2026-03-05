<?php

require_once __DIR__ . '/_shared.php';

$method = checklist_api_require_methods(['GET', 'PATCH', 'DELETE']);
$itemId = checklist_api_require_id_from_query('id');

if ($method === 'GET') {
    $item = checklist_api_find_item_or_404($conn, (int) $context['org_id'], $itemId);
    $attachments = bugcatcher_checklist_fetch_item_attachments($conn, $itemId);
    checklist_api_json_response(200, [
        'item' => $item,
        'attachments' => $attachments,
    ]);
}

checklist_api_require_manager($context);

if ($method === 'PATCH') {
    $payload = checklist_api_json_body(true);
    $item = checklist_api_update_item($conn, $context, $itemId, $payload);
    checklist_api_json_response(200, [
        'item' => $item,
    ]);
}

checklist_api_delete_item($conn, (int) $context['org_id'], $itemId);
checklist_api_json_response(200, [
    'deleted' => true,
    'id' => $itemId,
]);

