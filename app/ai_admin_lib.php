<?php

require_once __DIR__ . '/bootstrap.php';

function bugcatcher_ai_admin_runtime_ensure_schema(mysqli $conn): void
{
    static $done = false;
    if ($done) {
        return;
    }

    $conn->query("
        CREATE TABLE IF NOT EXISTS ai_runtime_config (
            id INT(11) NOT NULL AUTO_INCREMENT,
            is_enabled TINYINT(1) NOT NULL DEFAULT 1,
            default_provider_config_id INT(11) DEFAULT NULL,
            default_model_id INT(11) DEFAULT NULL,
            assistant_name VARCHAR(120) DEFAULT NULL,
            system_prompt TEXT DEFAULT NULL,
            created_by INT(11) NOT NULL,
            updated_by INT(11) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_ai_runtime_config_created_by (created_by),
            KEY idx_ai_runtime_config_updated_by (updated_by),
            KEY idx_ai_runtime_config_provider (default_provider_config_id),
            KEY idx_ai_runtime_config_model (default_model_id),
            CONSTRAINT fk_ai_runtime_config_created_by
                FOREIGN KEY (created_by) REFERENCES users(id),
            CONSTRAINT fk_ai_runtime_config_updated_by
                FOREIGN KEY (updated_by) REFERENCES users(id)
                ON DELETE SET NULL,
            CONSTRAINT fk_ai_runtime_config_provider
                FOREIGN KEY (default_provider_config_id) REFERENCES ai_provider_configs(id)
                ON DELETE SET NULL,
            CONSTRAINT fk_ai_runtime_config_model
                FOREIGN KEY (default_model_id) REFERENCES ai_models(id)
                ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    bugcatcher_ai_admin_backfill_runtime_from_openclaw($conn);

    $done = true;
}

function bugcatcher_ai_admin_backfill_runtime_from_openclaw(mysqli $conn): void
{
    if (!bugcatcher_db_has_table($conn, 'openclaw_runtime_config')) {
        return;
    }

    $result = $conn->query("SELECT id FROM ai_runtime_config ORDER BY id DESC LIMIT 1");
    $existing = $result ? $result->fetch_assoc() : null;
    if ($existing) {
        return;
    }

    $requiredColumns = [
        'ai_chat_enabled',
        'ai_chat_default_provider_config_id',
        'ai_chat_default_model_id',
        'ai_chat_assistant_name',
        'ai_chat_system_prompt',
    ];

    foreach ($requiredColumns as $column) {
        if (!bugcatcher_db_has_column($conn, 'openclaw_runtime_config', $column)) {
            return;
        }
    }

    $sourceResult = $conn->query("
        SELECT ai_chat_enabled,
               ai_chat_default_provider_config_id,
               ai_chat_default_model_id,
               ai_chat_assistant_name,
               ai_chat_system_prompt,
               created_by,
               updated_by,
               created_at,
               updated_at
        FROM openclaw_runtime_config
        ORDER BY id DESC
        LIMIT 1
    ");
    $source = $sourceResult ? $sourceResult->fetch_assoc() : null;
    if (!$source) {
        return;
    }

    $stmt = $conn->prepare("
        INSERT INTO ai_runtime_config
            (
                is_enabled,
                default_provider_config_id,
                default_model_id,
                assistant_name,
                system_prompt,
                created_by,
                updated_by,
                created_at,
                updated_at
            )
        VALUES (?, NULLIF(?, 0), NULLIF(?, 0), NULLIF(?, ''), NULLIF(?, ''), ?, NULLIF(?, 0), ?, ?)
    ");
    $enabled = (int) (!empty($source['ai_chat_enabled']) ? 1 : 0);
    $providerId = (int) ($source['ai_chat_default_provider_config_id'] ?? 0);
    $modelId = (int) ($source['ai_chat_default_model_id'] ?? 0);
    $assistantName = trim((string) ($source['ai_chat_assistant_name'] ?? ''));
    $systemPrompt = trim((string) ($source['ai_chat_system_prompt'] ?? ''));
    $createdBy = max(1, (int) ($source['created_by'] ?? 1));
    $updatedBy = max(0, (int) ($source['updated_by'] ?? 0));
    $createdAt = (string) ($source['created_at'] ?? date('Y-m-d H:i:s'));
    $updatedAt = (string) ($source['updated_at'] ?? $createdAt);
    $stmt->bind_param(
        'iiissiiss',
        $enabled,
        $providerId,
        $modelId,
        $assistantName,
        $systemPrompt,
        $createdBy,
        $updatedBy,
        $createdAt,
        $updatedAt
    );
    $stmt->execute();
    $stmt->close();
}

function bugcatcher_ai_admin_fetch_runtime_config(mysqli $conn): ?array
{
    bugcatcher_ai_admin_runtime_ensure_schema($conn);

    $result = $conn->query("
        SELECT arc.*,
               creator.username AS created_by_name,
               updater.username AS updated_by_name,
               provider.display_name AS default_provider_name,
               model.display_name AS default_model_name
        FROM ai_runtime_config arc
        LEFT JOIN users creator ON creator.id = arc.created_by
        LEFT JOIN users updater ON updater.id = arc.updated_by
        LEFT JOIN ai_provider_configs provider ON provider.id = arc.default_provider_config_id
        LEFT JOIN ai_models model ON model.id = arc.default_model_id
        ORDER BY arc.id DESC
        LIMIT 1
    ");
    $row = $result ? $result->fetch_assoc() : null;

    return $row ?: null;
}

function bugcatcher_ai_admin_validate_runtime_model(mysqli $conn, int $providerId, int $modelId): void
{
    if ($providerId <= 0 || $modelId <= 0) {
        return;
    }

    $stmt = $conn->prepare("
        SELECT provider_config_id
        FROM ai_models
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->bind_param('i', $modelId);
    $stmt->execute();
    $model = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$model) {
        throw new RuntimeException('The selected AI model does not exist.');
    }

    if ((int) ($model['provider_config_id'] ?? 0) !== $providerId) {
        throw new RuntimeException('The selected AI model does not belong to the chosen provider.');
    }
}

function bugcatcher_ai_admin_save_runtime_config(
    mysqli $conn,
    int $actorUserId,
    bool $isEnabled,
    int $defaultProviderId,
    int $defaultModelId,
    string $assistantName,
    string $systemPrompt
): void {
    bugcatcher_ai_admin_runtime_ensure_schema($conn);
    bugcatcher_ai_admin_validate_runtime_model($conn, $defaultProviderId, $defaultModelId);

    $assistantName = trim($assistantName);
    if ($assistantName === '') {
        $assistantName = trim((string) bugcatcher_config('AI_CHAT_DEFAULT_ASSISTANT_NAME', 'BugCatcher AI'));
    }
    $systemPrompt = trim($systemPrompt);

    $existing = bugcatcher_ai_admin_fetch_runtime_config($conn);
    if ($existing) {
        $stmt = $conn->prepare("
            UPDATE ai_runtime_config
            SET is_enabled = ?,
                default_provider_config_id = NULLIF(?, 0),
                default_model_id = NULLIF(?, 0),
                assistant_name = NULLIF(?, ''),
                system_prompt = NULLIF(?, ''),
                updated_by = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $enabled = $isEnabled ? 1 : 0;
        $runtimeId = (int) $existing['id'];
        $stmt->bind_param('iiissii', $enabled, $defaultProviderId, $defaultModelId, $assistantName, $systemPrompt, $actorUserId, $runtimeId);
        $stmt->execute();
        $stmt->close();
        return;
    }

    $stmt = $conn->prepare("
        INSERT INTO ai_runtime_config
            (
                is_enabled,
                default_provider_config_id,
                default_model_id,
                assistant_name,
                system_prompt,
                created_by,
                updated_by,
                updated_at
            )
        VALUES (?, NULLIF(?, 0), NULLIF(?, 0), NULLIF(?, ''), NULLIF(?, ''), ?, ?, NOW())
    ");
    $enabled = $isEnabled ? 1 : 0;
    $stmt->bind_param('iiissii', $enabled, $defaultProviderId, $defaultModelId, $assistantName, $systemPrompt, $actorUserId, $actorUserId);
    $stmt->execute();
    $stmt->close();
}

function bugcatcher_ai_admin_seed_default_config(mysqli $conn): void
{
    static $done = false;
    if ($done) {
        return;
    }

    bugcatcher_ai_admin_runtime_ensure_schema($conn);
    $providerKey = trim((string) bugcatcher_config('AI_CHAT_DEMO_PROVIDER_KEY', 'deepseek'));
    $providerName = trim((string) bugcatcher_config('AI_CHAT_DEMO_PROVIDER_NAME', 'DeepSeek'));
    $providerType = trim((string) bugcatcher_config('AI_CHAT_DEMO_PROVIDER_TYPE', 'openai-compatible'));
    $baseUrl = trim((string) bugcatcher_config('AI_CHAT_DEMO_PROVIDER_BASE_URL', 'https://api.deepseek.com'));
    $apiKey = trim((string) bugcatcher_config('AI_CHAT_DEMO_API_KEY', ''));
    $modelId = trim((string) bugcatcher_config('AI_CHAT_DEMO_MODEL_ID', 'deepseek-chat'));
    $modelName = trim((string) bugcatcher_config('AI_CHAT_DEMO_MODEL_NAME', 'DeepSeek Chat'));
    $supportsVision = (bool) bugcatcher_config('AI_CHAT_DEMO_MODEL_SUPPORTS_VISION', false);
    $assistantName = trim((string) bugcatcher_config('AI_CHAT_DEFAULT_ASSISTANT_NAME', 'BugCatcher AI'));
    $systemPrompt = trim((string) bugcatcher_config('AI_CHAT_DEFAULT_SYSTEM_PROMPT', ''));

    if (
        $providerKey === ''
        || $providerName === ''
        || $providerType === ''
        || $modelId === ''
        || $modelName === ''
    ) {
        $done = true;
        return;
    }

    $actorId = 1;
    $provider = bugcatcher_openclaw_find_provider_by_key($conn, $providerKey);
    if (!$provider) {
        bugcatcher_openclaw_save_provider(
            $conn,
            $actorId,
            0,
            $providerKey,
            $providerName,
            $providerType,
            $baseUrl,
            $apiKey,
            true,
            false
        );
        $provider = bugcatcher_openclaw_find_provider_by_key($conn, $providerKey);
    } elseif (
        trim((string) ($provider['display_name'] ?? '')) !== $providerName
        || trim((string) ($provider['provider_type'] ?? '')) !== $providerType
        || trim((string) ($provider['base_url'] ?? '')) !== $baseUrl
        || !(bool) ($provider['is_enabled'] ?? false)
        || ($apiKey !== '' && bugcatcher_openclaw_decrypt_secret($provider['encrypted_api_key'] ?? '') !== $apiKey)
    ) {
        bugcatcher_openclaw_save_provider(
            $conn,
            $actorId,
            (int) $provider['id'],
            $providerKey,
            $providerName,
            $providerType,
            $baseUrl,
            $apiKey,
            true,
            false
        );
        $provider = bugcatcher_openclaw_find_provider_by_key($conn, $providerKey);
    }

    if (!$provider) {
        $done = true;
        return;
    }

    $model = bugcatcher_openclaw_find_model_by_provider_and_remote_id($conn, (int) $provider['id'], $modelId);
    if (!$model) {
        bugcatcher_openclaw_save_model(
            $conn,
            (int) $provider['id'],
            0,
            $modelId,
            $modelName,
            $supportsVision,
            false,
            true,
            true,
            $actorId
        );
        $model = bugcatcher_openclaw_find_model_by_provider_and_remote_id($conn, (int) $provider['id'], $modelId);
    } elseif (
        trim((string) ($model['display_name'] ?? '')) !== $modelName
        || (bool) ($model['supports_vision'] ?? false) !== $supportsVision
        || !(bool) ($model['is_enabled'] ?? false)
        || !(bool) ($model['is_default'] ?? false)
    ) {
        bugcatcher_openclaw_save_model(
            $conn,
            (int) $provider['id'],
            (int) $model['id'],
            $modelId,
            $modelName,
            $supportsVision,
            (bool) ($model['supports_json_output'] ?? false),
            true,
            true,
            $actorId
        );
        $model = bugcatcher_openclaw_find_model_by_provider_and_remote_id($conn, (int) $provider['id'], $modelId);
    }

    $runtime = bugcatcher_ai_admin_fetch_runtime_config($conn);
    if (!$runtime) {
        bugcatcher_ai_admin_save_runtime_config(
            $conn,
            $actorId,
            (bool) bugcatcher_config('AI_CHAT_DEMO_ENABLED', true),
            (int) $provider['id'],
            (int) ($model['id'] ?? 0),
            $assistantName,
            $systemPrompt
        );
    } elseif (
        (int) ($runtime['default_provider_config_id'] ?? 0) <= 0
        || (int) ($runtime['default_model_id'] ?? 0) <= 0
    ) {
        bugcatcher_ai_admin_save_runtime_config(
            $conn,
            $actorId,
            (bool) ($runtime['is_enabled'] ?? bugcatcher_config('AI_CHAT_DEMO_ENABLED', true)),
            (int) $provider['id'],
            (int) ($model['id'] ?? 0),
            (string) ($runtime['assistant_name'] ?? $assistantName),
            (string) ($runtime['system_prompt'] ?? $systemPrompt)
        );
    }

    $done = true;
}

function bugcatcher_ai_admin_format_provider(array $provider): array
{
    return [
        'id' => (int) $provider['id'],
        'provider_key' => (string) $provider['provider_key'],
        'display_name' => (string) $provider['display_name'],
        'provider_type' => (string) $provider['provider_type'],
        'base_url' => (string) ($provider['base_url'] ?? ''),
        'api_key' => bugcatcher_openclaw_mask_secret($provider['encrypted_api_key'] ?? ''),
        'is_enabled' => (bool) ($provider['is_enabled'] ?? false),
        'supports_model_sync' => (bool) ($provider['supports_model_sync'] ?? false),
    ];
}

function bugcatcher_ai_admin_format_model(array $model): array
{
    return [
        'id' => (int) $model['id'],
        'provider_config_id' => (int) $model['provider_config_id'],
        'provider_name' => (string) ($model['provider_name'] ?? ''),
        'display_name' => (string) $model['display_name'],
        'model_id' => (string) $model['model_id'],
        'supports_vision' => (bool) ($model['supports_vision'] ?? false),
        'supports_json_output' => (bool) ($model['supports_json_output'] ?? false),
        'is_enabled' => (bool) ($model['is_enabled'] ?? false),
        'is_default' => (bool) ($model['is_default'] ?? false),
    ];
}

function bugcatcher_ai_admin_providers_for_display(mysqli $conn): array
{
    return array_map('bugcatcher_ai_admin_format_provider', bugcatcher_openclaw_fetch_providers($conn));
}

function bugcatcher_ai_admin_models_for_display(mysqli $conn): array
{
    return array_map('bugcatcher_ai_admin_format_model', bugcatcher_openclaw_fetch_models($conn));
}

function bugcatcher_ai_admin_runtime_snapshot(mysqli $conn): array
{
    bugcatcher_ai_admin_seed_default_config($conn);
    $runtime = bugcatcher_ai_admin_fetch_runtime_config($conn);

    return [
        'runtime' => [
            'is_enabled' => (bool) ($runtime['is_enabled'] ?? bugcatcher_config('AI_CHAT_DEMO_ENABLED', true)),
            'default_provider_config_id' => isset($runtime['default_provider_config_id']) ? (int) $runtime['default_provider_config_id'] : null,
            'default_model_id' => isset($runtime['default_model_id']) ? (int) $runtime['default_model_id'] : null,
            'assistant_name' => (string) ($runtime['assistant_name'] ?? bugcatcher_config('AI_CHAT_DEFAULT_ASSISTANT_NAME', 'BugCatcher AI')),
            'system_prompt' => (string) ($runtime['system_prompt'] ?? bugcatcher_config('AI_CHAT_DEFAULT_SYSTEM_PROMPT', '')),
        ],
        'providers' => bugcatcher_ai_admin_providers_for_display($conn),
        'models' => bugcatcher_ai_admin_models_for_display($conn),
    ];
}

function bugcatcher_ai_admin_resolve_runtime(mysqli $conn): array
{
    bugcatcher_ai_admin_seed_default_config($conn);
    $runtime = bugcatcher_ai_admin_fetch_runtime_config($conn);
    if (!$runtime || !(bool) ($runtime['is_enabled'] ?? false)) {
        throw new RuntimeException('AI chat is disabled right now.');
    }

    $providerId = (int) ($runtime['default_provider_config_id'] ?? 0);
    $modelId = (int) ($runtime['default_model_id'] ?? 0);
    if ($providerId <= 0 || $modelId <= 0) {
        throw new RuntimeException('AI chat is not configured correctly. Go to Super Admin > AI.');
    }

    $stmt = $conn->prepare("
        SELECT *
        FROM ai_provider_configs
        WHERE id = ? AND is_enabled = 1
        LIMIT 1
    ");
    $stmt->bind_param('i', $providerId);
    $stmt->execute();
    $provider = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $stmt = $conn->prepare("
        SELECT *
        FROM ai_models
        WHERE id = ? AND is_enabled = 1
        LIMIT 1
    ");
    $stmt->bind_param('i', $modelId);
    $stmt->execute();
    $model = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $apiKey = bugcatcher_openclaw_decrypt_secret($provider['encrypted_api_key'] ?? '');
    if (!$provider || !$model || trim($apiKey) === '') {
        throw new RuntimeException('AI chat is not configured correctly. Go to Super Admin > AI.');
    }
    if (!(bool) ($model['supports_vision'] ?? false)) {
        throw new RuntimeException('The configured AI model must support image analysis for checklist drafting.');
    }

    return [
        'runtime' => $runtime,
        'provider' => $provider,
        'model' => $model,
        'api_key' => $apiKey,
        'assistant_name' => trim((string) ($runtime['assistant_name'] ?? bugcatcher_config('AI_CHAT_DEFAULT_ASSISTANT_NAME', 'BugCatcher AI'))),
        'system_prompt' => trim((string) ($runtime['system_prompt'] ?? bugcatcher_config('AI_CHAT_DEFAULT_SYSTEM_PROMPT', ''))),
    ];
}
