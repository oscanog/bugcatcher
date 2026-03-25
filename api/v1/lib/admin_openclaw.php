<?php

declare(strict_types=1);

function bc_v1_admin_openclaw_guard(mysqli $conn): array
{
    $actor = bc_v1_actor($conn, true);
    bc_v1_require_super_admin($actor);
    return $actor;
}

function bc_v1_admin_openclaw_gone(string $message): void
{
    bc_v1_json_error(410, 'openclaw_admin_retired', $message);
}

function bc_v1_admin_openclaw_runtime_get(mysqli $conn, array $params): void
{
    bc_v1_admin_ai_runtime_get($conn, $params);
}

function bc_v1_admin_openclaw_runtime_put(mysqli $conn, array $params): void
{
    bc_v1_admin_ai_runtime_put($conn, $params);
}

function bc_v1_admin_openclaw_runtime_reload_post(mysqli $conn, array $params): void
{
    bc_v1_require_method(['POST']);
    bc_v1_admin_openclaw_guard($conn);
    bc_v1_admin_openclaw_gone('OpenClaw runtime reload has been retired with the Discord cleanup.');
}

function bc_v1_admin_openclaw_snapshot_post(mysqli $conn, array $params): void
{
    bc_v1_require_method(['POST']);
    bc_v1_admin_openclaw_guard($conn);
    bc_v1_admin_openclaw_gone('OpenClaw runtime snapshots have been retired with the Discord cleanup.');
}

function bc_v1_admin_openclaw_providers_get(mysqli $conn, array $params): void
{
    bc_v1_admin_ai_providers_get($conn, $params);
}

function bc_v1_admin_openclaw_providers_post(mysqli $conn, array $params): void
{
    bc_v1_admin_ai_providers_post($conn, $params);
}

function bc_v1_admin_openclaw_providers_delete(mysqli $conn, array $params): void
{
    bc_v1_admin_ai_providers_delete($conn, $params);
}

function bc_v1_admin_openclaw_models_get(mysqli $conn, array $params): void
{
    bc_v1_admin_ai_models_get($conn, $params);
}

function bc_v1_admin_openclaw_models_post(mysqli $conn, array $params): void
{
    bc_v1_admin_ai_models_post($conn, $params);
}

function bc_v1_admin_openclaw_models_delete(mysqli $conn, array $params): void
{
    bc_v1_admin_ai_models_delete($conn, $params);
}

function bc_v1_admin_openclaw_channels_get(mysqli $conn, array $params): void
{
    bc_v1_require_method(['GET']);
    bc_v1_admin_openclaw_guard($conn);
    bc_v1_admin_openclaw_gone('Discord channel management has been removed.');
}

function bc_v1_admin_openclaw_channels_post(mysqli $conn, array $params): void
{
    bc_v1_require_method(['POST']);
    bc_v1_admin_openclaw_guard($conn);
    bc_v1_admin_openclaw_gone('Discord channel management has been removed.');
}

function bc_v1_admin_openclaw_channels_delete(mysqli $conn, array $params): void
{
    bc_v1_require_method(['DELETE']);
    bc_v1_admin_openclaw_guard($conn);
    bc_v1_admin_openclaw_gone('Discord channel management has been removed.');
}

function bc_v1_admin_openclaw_users_get(mysqli $conn, array $params): void
{
    bc_v1_require_method(['GET']);
    bc_v1_admin_openclaw_guard($conn);
    bc_v1_admin_openclaw_gone('Discord-linked user management has been removed.');
}

function bc_v1_admin_openclaw_requests_get(mysqli $conn, array $params): void
{
    bc_v1_require_method(['GET']);
    bc_v1_admin_openclaw_guard($conn);
    bc_v1_admin_openclaw_gone('Discord request monitoring has been removed.');
}
