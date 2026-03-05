<?php

require_once __DIR__ . '/_shared.php';

checklist_api_require_methods(['DELETE']);
checklist_api_require_manager($context);

$attachmentId = checklist_api_require_id_from_query('id');
$attachment = checklist_api_find_batch_attachment_or_404($conn, (int) $context['org_id'], $attachmentId);
checklist_api_delete_batch_attachment($conn, $attachment);

checklist_api_json_response(200, [
    'deleted' => true,
    'id' => $attachmentId,
]);

