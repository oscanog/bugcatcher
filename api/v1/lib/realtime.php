<?php

declare(strict_types=1);

function bc_v1_realtime_socket_token_post(mysqli $conn, array $params): void
{
    bc_v1_require_method(['POST']);
    $actor = bc_v1_actor($conn, true);

    bc_v1_json_success([
        'connection' => bc_v1_issue_socket_token(
            $actor['user'],
            (int) ($actor['active_org_id'] ?? 0),
            (string) ($actor['active_scope'] ?? 'none')
        ),
    ]);
}
