#!/usr/bin/env bash
set -euo pipefail

BUGCATCHER_ROOT="${BUGCATCHER_ROOT:-/var/www/bugcatcher}"
CONFIG_PATH="${CONFIG_PATH:-}"
DEEPSEEK_API_KEY="${DEEPSEEK_API_KEY:-}"
DEEPSEEK_MODEL_ID="${DEEPSEEK_MODEL_ID:-deepseek-chat}"
DEEPSEEK_BASE_URL="${DEEPSEEK_BASE_URL:-https://api.deepseek.com/v1}"
ADMIN_USER_ID="${ADMIN_USER_ID:-}"

resolve_config_path() {
    local root="$1"
    local explicit="$2"
    local candidate=""

    if [[ -n "$explicit" ]]; then
        if [[ -f "$explicit" ]]; then
            printf '%s\n' "$explicit"
            return 0
        fi
        echo "Config file not found: $explicit" >&2
        return 1
    fi

    for candidate in \
        "$root/shared/config.php" \
        "$root/infra/config/local.php" \
        "$root/config/local.php"
    do
        if [[ -f "$candidate" ]]; then
            printf '%s\n' "$candidate"
            return 0
        fi
    done

    echo "No config file found. Checked: $root/shared/config.php, $root/infra/config/local.php, $root/config/local.php" >&2
    return 1
}

if [[ -z "$DEEPSEEK_API_KEY" ]]; then
    echo "DEEPSEEK_API_KEY is required." >&2
    exit 1
fi

CONFIG_PATH="$(resolve_config_path "$BUGCATCHER_ROOT" "$CONFIG_PATH")"

if [[ ! -f "$BUGCATCHER_ROOT/app/openclaw_lib.php" ]]; then
    echo "OpenClaw library not found: $BUGCATCHER_ROOT/app/openclaw_lib.php" >&2
    exit 1
fi

if [[ -z "$DEEPSEEK_MODEL_ID" ]]; then
    echo "DEEPSEEK_MODEL_ID must not be empty." >&2
    exit 1
fi

if [[ -n "$ADMIN_USER_ID" && ! "$ADMIN_USER_ID" =~ ^[0-9]+$ ]]; then
    echo "ADMIN_USER_ID must be a positive integer when provided." >&2
    exit 1
fi

env \
    BUGCATCHER_ROOT="$BUGCATCHER_ROOT" \
    BUGCATCHER_CONFIG_PATH="$CONFIG_PATH" \
    DEEPSEEK_API_KEY="$DEEPSEEK_API_KEY" \
    DEEPSEEK_MODEL_ID="$DEEPSEEK_MODEL_ID" \
    DEEPSEEK_BASE_URL="$DEEPSEEK_BASE_URL" \
    ADMIN_USER_ID="$ADMIN_USER_ID" \
    php <<'PHP'
<?php
$bugcatcherRoot = rtrim((string) getenv('BUGCATCHER_ROOT'), "/\\");
$deepseekApiKey = trim((string) getenv('DEEPSEEK_API_KEY'));
$deepseekModelId = trim((string) getenv('DEEPSEEK_MODEL_ID')) ?: 'deepseek-chat';
$deepseekBaseUrl = trim((string) getenv('DEEPSEEK_BASE_URL')) ?: 'https://api.deepseek.com/v1';
$adminUserIdRaw = trim((string) getenv('ADMIN_USER_ID'));

if ($bugcatcherRoot === '') {
    fwrite(STDERR, "BUGCATCHER_ROOT must not be empty.\n");
    exit(1);
}
if ($deepseekApiKey === '') {
    fwrite(STDERR, "DEEPSEEK_API_KEY must not be empty.\n");
    exit(1);
}

require $bugcatcherRoot . '/app/openclaw_lib.php';

function deepseek_fetch_provider(mysqli $conn, string $providerKey): ?array
{
    $stmt = $conn->prepare("
        SELECT *
        FROM ai_provider_configs
        WHERE provider_key = ?
        LIMIT 1
    ");
    $stmt->bind_param('s', $providerKey);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();

    return $row;
}

function deepseek_fetch_model(mysqli $conn, int $providerId, string $modelId): ?array
{
    $stmt = $conn->prepare("
        SELECT *
        FROM ai_models
        WHERE provider_config_id = ?
          AND model_id = ?
        LIMIT 1
    ");
    $stmt->bind_param('is', $providerId, $modelId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();

    return $row;
}

function deepseek_resolve_actor_user_id(mysqli $conn, string $adminUserIdRaw): int
{
    if ($adminUserIdRaw !== '') {
        if (!ctype_digit($adminUserIdRaw) || (int) $adminUserIdRaw <= 0) {
            throw new RuntimeException('ADMIN_USER_ID must be a positive integer.');
        }
        $candidateId = (int) $adminUserIdRaw;
        $stmt = $conn->prepare("
            SELECT id
            FROM users
            WHERE id = ?
              AND role IN ('super_admin', 'admin')
            LIMIT 1
        ");
        $stmt->bind_param('i', $candidateId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();
        if (!$row) {
            throw new RuntimeException('ADMIN_USER_ID was provided but no matching super_admin/admin user was found.');
        }
        return (int) $row['id'];
    }

    $result = $conn->query("
        SELECT id
        FROM users
        WHERE role IN ('super_admin', 'admin')
        ORDER BY CASE role WHEN 'super_admin' THEN 0 ELSE 1 END, id ASC
        LIMIT 1
    ");
    $row = $result ? $result->fetch_assoc() : null;
    if (!$row) {
        throw new RuntimeException('No super_admin/admin user is available. Set ADMIN_USER_ID explicitly.');
    }

    return (int) $row['id'];
}

$conn = bugcatcher_db_connection();
$actorUserId = deepseek_resolve_actor_user_id($conn, $adminUserIdRaw);

$providerKey = 'deepseek';
$providerDisplayName = 'DeepSeek';
$providerType = 'openai-compatible';
$providerShouldEnable = 1;
$providerSupportsModelSync = 0;

$provider = deepseek_fetch_provider($conn, $providerKey);
$providerId = (int) ($provider['id'] ?? 0);
$providerNeedsSave = false;

if ($provider === null) {
    $providerNeedsSave = true;
} else {
    $currentApiKey = '';
    try {
        $currentApiKey = bugcatcher_openclaw_decrypt_secret($provider['encrypted_api_key'] ?? '');
    } catch (Throwable $e) {
        $currentApiKey = '';
    }

    $providerNeedsSave =
        trim((string) ($provider['display_name'] ?? '')) !== $providerDisplayName
        || trim((string) ($provider['provider_type'] ?? '')) !== $providerType
        || trim((string) ($provider['base_url'] ?? '')) !== $deepseekBaseUrl
        || (int) ($provider['is_enabled'] ?? 0) !== $providerShouldEnable
        || (int) ($provider['supports_model_sync'] ?? 0) !== $providerSupportsModelSync
        || $currentApiKey !== $deepseekApiKey;
}

if ($providerNeedsSave) {
    bugcatcher_openclaw_save_provider(
        $conn,
        $actorUserId,
        $providerId,
        $providerKey,
        $providerDisplayName,
        $providerType,
        $deepseekBaseUrl,
        $deepseekApiKey,
        true,
        false
    );
    $provider = deepseek_fetch_provider($conn, $providerKey);
    $providerId = (int) ($provider['id'] ?? 0);
}

if ($providerId <= 0) {
    throw new RuntimeException('Failed to create or load the DeepSeek provider.');
}

$modelDisplayName = 'DeepSeek Chat';
$modelShouldEnable = 1;
$modelShouldSupportVision = 0;
$modelShouldSupportJson = 1;
$modelShouldBeDefault = 1;

$model = deepseek_fetch_model($conn, $providerId, $deepseekModelId);
$modelId = (int) ($model['id'] ?? 0);
$modelNeedsSave = false;

if ($model === null) {
    $modelNeedsSave = true;
} else {
    $modelNeedsSave =
        trim((string) ($model['display_name'] ?? '')) !== $modelDisplayName
        || (int) ($model['supports_vision'] ?? 0) !== $modelShouldSupportVision
        || (int) ($model['supports_json_output'] ?? 0) !== $modelShouldSupportJson
        || (int) ($model['is_enabled'] ?? 0) !== $modelShouldEnable
        || (int) ($model['is_default'] ?? 0) !== $modelShouldBeDefault;
}

$stmt = $conn->prepare("
    SELECT COUNT(*) AS default_count
    FROM ai_models
    WHERE provider_config_id = ?
      AND is_default = 1
      AND id <> ?
");
$stmt->bind_param('ii', $providerId, $modelId);
$stmt->execute();
$otherDefaultRow = $stmt->get_result()->fetch_assoc() ?: ['default_count' => 0];
$stmt->close();
$hasOtherProviderDefaults = (int) ($otherDefaultRow['default_count'] ?? 0) > 0;

if ($modelNeedsSave || $hasOtherProviderDefaults) {
    bugcatcher_openclaw_save_model(
        $conn,
        $providerId,
        $modelId,
        $deepseekModelId,
        $modelDisplayName,
        false,
        true,
        true,
        true,
        $actorUserId
    );
    $model = deepseek_fetch_model($conn, $providerId, $deepseekModelId);
    $modelId = (int) ($model['id'] ?? 0);
}

if ($modelId <= 0) {
    throw new RuntimeException('Failed to create or load the DeepSeek model.');
}

$runtime = bugcatcher_openclaw_fetch_runtime_config($conn) ?: [];
$runtimeNeedsSave =
    !isset($runtime['id'])
    || (int) ($runtime['default_provider_config_id'] ?? 0) !== $providerId
    || (int) ($runtime['default_model_id'] ?? 0) !== $modelId;

if ($runtimeNeedsSave) {
    $runtimeEnabled = (int) ($runtime['is_enabled'] ?? 0) === 1;
    $runtimeNotes = (string) ($runtime['notes'] ?? '');
    bugcatcher_openclaw_save_runtime_config(
        $conn,
        $actorUserId,
        $runtimeEnabled,
        '',
        $providerId,
        $modelId,
        $runtimeNotes
    );
}

$runtime = bugcatcher_openclaw_fetch_runtime_config($conn) ?: [];
$pendingReload = bugcatcher_openclaw_fetch_pending_reload_request($conn);
$controlPlane = bugcatcher_openclaw_fetch_control_plane_state($conn);

echo json_encode(
    [
        'ok' => true,
        'changed' => $providerNeedsSave || $modelNeedsSave || $hasOtherProviderDefaults || $runtimeNeedsSave,
        'provider' => [
            'id' => $providerId,
            'provider_key' => $providerKey,
            'display_name' => $providerDisplayName,
            'base_url' => $deepseekBaseUrl,
            'changed' => $providerNeedsSave,
        ],
        'model' => [
            'id' => $modelId,
            'model_id' => $deepseekModelId,
            'display_name' => $modelDisplayName,
            'changed' => $modelNeedsSave || $hasOtherProviderDefaults,
        ],
        'runtime' => [
            'default_provider_config_id' => (int) ($runtime['default_provider_config_id'] ?? 0),
            'default_model_id' => (int) ($runtime['default_model_id'] ?? 0),
            'changed' => $runtimeNeedsSave,
        ],
        'control_plane' => [
            'config_version' => (string) ($controlPlane['config_version'] ?? ''),
            'pending_reload_request_id' => (int) ($pendingReload['id'] ?? 0),
            'pending_reload_status' => (string) ($pendingReload['status'] ?? ''),
        ],
    ],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
) . PHP_EOL;
PHP
