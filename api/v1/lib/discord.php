<?php

declare(strict_types=1);

function bc_v1_discord_link_get(mysqli $conn, array $params): void
{
    bc_v1_require_method(['GET']);
    bc_v1_actor($conn, true);
    bc_v1_json_error(410, 'discord_link_retired', 'Discord linking has been removed.');
}

function bc_v1_discord_link_code_post(mysqli $conn, array $params): void
{
    bc_v1_require_method(['POST']);
    bc_v1_actor($conn, true);
    bc_v1_json_error(410, 'discord_link_retired', 'Discord linking has been removed.');
}

function bc_v1_discord_link_delete(mysqli $conn, array $params): void
{
    bc_v1_require_method(['DELETE']);
    bc_v1_actor($conn, true);
    bc_v1_json_error(410, 'discord_link_retired', 'Discord linking has been removed.');
}
