<?php

declare(strict_types=1);

function bugcatcher_notification_normalize_path(string $path): string
{
    $trimmed = trim($path);
    if ($trimmed === '') {
        return '/app/notifications';
    }

    return '/' . ltrim($trimmed, '/');
}

function bugcatcher_notification_meta_json(?array $meta): ?string
{
    if (!$meta) {
        return null;
    }

    return json_encode($meta, JSON_UNESCAPED_SLASHES);
}

function bugcatcher_notification_parse_meta(?string $metaJson): ?array
{
    if (!is_string($metaJson) || trim($metaJson) === '') {
        return null;
    }

    $decoded = json_decode($metaJson, true);
    return is_array($decoded) ? $decoded : null;
}

function bugcatcher_notification_shape(array $row): array
{
    return [
        'id' => (int) $row['id'],
        'type' => (string) $row['type'],
        'event_key' => (string) $row['event_key'],
        'title' => (string) $row['title'],
        'body' => (string) ($row['body'] ?? ''),
        'severity' => (string) ($row['severity'] ?? 'default'),
        'link_path' => (string) ($row['link_path'] ?? '/app/notifications'),
        'read_at' => $row['read_at'] ?: null,
        'created_at' => (string) ($row['created_at'] ?? ''),
        'org_id' => isset($row['org_id']) ? (int) $row['org_id'] : null,
        'project_id' => isset($row['project_id']) ? (int) $row['project_id'] : null,
        'issue_id' => isset($row['issue_id']) ? (int) $row['issue_id'] : null,
        'checklist_batch_id' => isset($row['checklist_batch_id']) ? (int) $row['checklist_batch_id'] : null,
        'checklist_item_id' => isset($row['checklist_item_id']) ? (int) $row['checklist_item_id'] : null,
        'actor' => [
            'id' => isset($row['actor_user_id']) ? (int) $row['actor_user_id'] : 0,
            'username' => (string) ($row['actor_username'] ?? ''),
        ],
        'meta' => bugcatcher_notification_parse_meta($row['meta_json'] ?? null),
    ];
}

function bugcatcher_notification_create(mysqli $conn, array $payload): void
{
    $recipientUserId = (int) ($payload['recipient_user_id'] ?? 0);
    if ($recipientUserId <= 0) {
        return;
    }

    $type = trim((string) ($payload['type'] ?? 'system'));
    $eventKey = trim((string) ($payload['event_key'] ?? 'notification'));
    $title = trim((string) ($payload['title'] ?? 'Notification'));
    $body = trim((string) ($payload['body'] ?? ''));
    $linkPath = bugcatcher_notification_normalize_path((string) ($payload['link_path'] ?? '/app/notifications'));
    $severity = trim((string) ($payload['severity'] ?? 'default'));
    if (!in_array($severity, ['default', 'success', 'alert'], true)) {
        $severity = 'default';
    }

    $actorUserId = max(0, (int) ($payload['actor_user_id'] ?? 0));
    $orgId = max(0, (int) ($payload['org_id'] ?? 0));
    $projectId = max(0, (int) ($payload['project_id'] ?? 0));
    $issueId = max(0, (int) ($payload['issue_id'] ?? 0));
    $checklistBatchId = max(0, (int) ($payload['checklist_batch_id'] ?? 0));
    $checklistItemId = max(0, (int) ($payload['checklist_item_id'] ?? 0));
    $metaJson = bugcatcher_notification_meta_json($payload['meta'] ?? null);

    $stmt = $conn->prepare("
        INSERT INTO notifications
            (recipient_user_id, actor_user_id, org_id, project_id, issue_id, checklist_batch_id, checklist_item_id,
             type, event_key, title, body, link_path, severity, meta_json)
        VALUES
            (?, NULLIF(?, 0), NULLIF(?, 0), NULLIF(?, 0), NULLIF(?, 0), NULLIF(?, 0), NULLIF(?, 0),
             ?, ?, ?, NULLIF(?, ''), ?, ?, NULLIF(?, ''))
    ");
    $stmt->bind_param(
        'iiiiiiisssssss',
        $recipientUserId,
        $actorUserId,
        $orgId,
        $projectId,
        $issueId,
        $checklistBatchId,
        $checklistItemId,
        $type,
        $eventKey,
        $title,
        $body,
        $linkPath,
        $severity,
        $metaJson
    );
    $stmt->execute();
    $stmt->close();
}

function bugcatcher_notifications_send(mysqli $conn, array $recipientUserIds, array $payload): void
{
    $unique = [];
    foreach ($recipientUserIds as $recipientUserId) {
        $recipientUserId = (int) $recipientUserId;
        if ($recipientUserId > 0) {
            $unique[$recipientUserId] = true;
        }
    }

    foreach (array_keys($unique) as $recipientUserId) {
        bugcatcher_notification_create($conn, array_merge($payload, [
            'recipient_user_id' => $recipientUserId,
        ]));
    }
}

function bugcatcher_notification_user_ids_for_org_roles(mysqli $conn, int $orgId, array $roles): array
{
    if ($orgId <= 0 || !$roles) {
        return [];
    }

    $roles = array_values(array_unique(array_filter(array_map('strval', $roles))));
    if (!$roles) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($roles), '?'));
    $types = 'i' . str_repeat('s', count($roles));
    $params = array_merge([$orgId], $roles);
    $stmt = $conn->prepare("
        SELECT user_id
        FROM org_members
        WHERE org_id = ? AND role IN ({$placeholders})
    ");
    bc_v1_stmt_bind($stmt, $types, $params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return array_values(array_map(static function (array $row): int {
        return (int) $row['user_id'];
    }, $rows));
}

function bugcatcher_notification_org_owner_ids(mysqli $conn, int $orgId): array
{
    return bugcatcher_notification_user_ids_for_org_roles($conn, $orgId, ['owner']);
}

function bugcatcher_notification_org_manager_ids(mysqli $conn, int $orgId): array
{
    return bugcatcher_notification_user_ids_for_org_roles($conn, $orgId, ['owner', 'Project Manager', 'QA Lead']);
}

function bugcatcher_notifications_list(
    mysqli $conn,
    int $userId,
    string $state = 'all',
    int $limit = 25
): array {
    $limit = max(1, min(100, $limit));
    $readClause = '';
    if ($state === 'read') {
        $readClause = ' AND n.read_at IS NOT NULL';
    } elseif ($state === 'unread') {
        $readClause = ' AND n.read_at IS NULL';
    }

    $stmt = $conn->prepare("
        SELECT n.*,
               actor.username AS actor_username
        FROM notifications n
        LEFT JOIN users actor ON actor.id = n.actor_user_id
        WHERE n.recipient_user_id = ?{$readClause}
        ORDER BY n.created_at DESC, n.id DESC
        LIMIT ?
    ");
    $stmt->bind_param('ii', $userId, $limit);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $countStmt = $conn->prepare("
        SELECT
            SUM(CASE WHEN read_at IS NULL THEN 1 ELSE 0 END) AS unread_count,
            COUNT(*) AS total_count
        FROM notifications
        WHERE recipient_user_id = ?
    ");
    $countStmt->bind_param('i', $userId);
    $countStmt->execute();
    $counts = $countStmt->get_result()->fetch_assoc() ?: ['unread_count' => 0, 'total_count' => 0];
    $countStmt->close();

    return [
        'items' => array_map('bugcatcher_notification_shape', $rows),
        'unread_count' => (int) ($counts['unread_count'] ?? 0),
        'total_count' => (int) ($counts['total_count'] ?? 0),
    ];
}

function bugcatcher_notification_mark_read(mysqli $conn, int $userId, int $notificationId): ?array
{
    if ($notificationId <= 0) {
        return null;
    }

    $stmt = $conn->prepare("
        UPDATE notifications
        SET read_at = COALESCE(read_at, NOW())
        WHERE id = ? AND recipient_user_id = ?
    ");
    $stmt->bind_param('ii', $notificationId, $userId);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("
        SELECT n.*, actor.username AS actor_username
        FROM notifications n
        LEFT JOIN users actor ON actor.id = n.actor_user_id
        WHERE n.id = ? AND n.recipient_user_id = ?
        LIMIT 1
    ");
    $stmt->bind_param('ii', $notificationId, $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ? bugcatcher_notification_shape($row) : null;
}

function bugcatcher_notifications_mark_all_read(mysqli $conn, int $userId): int
{
    $stmt = $conn->prepare("
        UPDATE notifications
        SET read_at = COALESCE(read_at, NOW())
        WHERE recipient_user_id = ? AND read_at IS NULL
    ");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $affected = (int) $stmt->affected_rows;
    $stmt->close();

    return $affected;
}
