<?php

require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/app/openclaw_lib.php';
require_once dirname(__DIR__) . '/app/ai_admin_lib.php';
require_once dirname(__DIR__) . '/app/checklist_shell.php';

bugcatcher_require_super_admin($current_role);

function ai_post_int(string $key): int
{
    $value = $_POST[$key] ?? '';
    return ctype_digit((string) $value) ? (int) $value : 0;
}

$flash = '';
$error = '';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'save_runtime') {
            bugcatcher_ai_admin_save_runtime_config(
                $conn,
                $current_user_id,
                isset($_POST['is_enabled']),
                ai_post_int('default_provider_config_id'),
                ai_post_int('default_model_id'),
                trim((string) ($_POST['assistant_name'] ?? '')),
                trim((string) ($_POST['system_prompt'] ?? ''))
            );
            $flash = 'AI runtime settings saved.';
        } elseif ($action === 'save_provider') {
            bugcatcher_openclaw_save_provider(
                $conn,
                $current_user_id,
                ai_post_int('provider_id'),
                trim((string) ($_POST['provider_key'] ?? '')),
                trim((string) ($_POST['display_name'] ?? '')),
                trim((string) ($_POST['provider_type'] ?? '')),
                trim((string) ($_POST['base_url'] ?? '')),
                trim((string) ($_POST['api_key'] ?? '')),
                isset($_POST['is_enabled']),
                isset($_POST['supports_model_sync'])
            );
            $flash = 'AI provider saved.';
        } elseif ($action === 'delete_provider') {
            bugcatcher_openclaw_delete_provider($conn, ai_post_int('provider_id'), $current_user_id);
            $flash = 'AI provider deleted.';
        } elseif ($action === 'save_model') {
            bugcatcher_openclaw_save_model(
                $conn,
                ai_post_int('provider_config_id'),
                ai_post_int('model_id'),
                trim((string) ($_POST['remote_model_id'] ?? '')),
                trim((string) ($_POST['display_name'] ?? '')),
                isset($_POST['supports_vision']),
                isset($_POST['supports_json_output']),
                isset($_POST['is_enabled']),
                isset($_POST['is_default']),
                $current_user_id
            );
            $flash = 'AI model saved.';
        } elseif ($action === 'delete_model') {
            bugcatcher_openclaw_delete_model($conn, ai_post_int('model_id'), $current_user_id);
            $flash = 'AI model deleted.';
        } else {
            $error = 'Unknown action.';
        }
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$snapshot = bugcatcher_ai_admin_runtime_snapshot($conn);
$runtime = $snapshot['runtime'];
$providers = $snapshot['providers'];
$models = $snapshot['models'];
$enabledProviders = array_values(array_filter($providers, static function (array $provider): bool {
    return (int) ($provider['is_enabled'] ?? 0) === 1;
}));
$enabledModels = array_values(array_filter($models, static function (array $model): bool {
    return (int) ($model['is_enabled'] ?? 0) === 1;
}));

$context = [
    'current_username' => $current_username,
    'current_role' => $current_role,
    'org_role' => null,
    'org_name' => 'Built-in AI Configuration',
];

bugcatcher_shell_start('AI Setup', 'super_admin', $context);
?>

<?php if ($flash): ?>
    <div class="bc-alert success"><?= bugcatcher_html($flash) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="bc-alert error"><?= bugcatcher_html($error) ?></div>
<?php endif; ?>

<div class="bc-grid cols-3">
    <div class="bc-stat"><span>AI enabled</span><strong><?= !empty($runtime['is_enabled']) ? 'Yes' : 'No' ?></strong></div>
    <div class="bc-stat"><span>Providers</span><strong><?= count($enabledProviders) ?>/<?= count($providers) ?></strong></div>
    <div class="bc-stat"><span>Models</span><strong><?= count($enabledModels) ?>/<?= count($models) ?></strong></div>
</div>

<div class="bc-panel">
    <h2>Built-in AI Runtime</h2>
    <form method="post" class="bc-form-grid">
        <input type="hidden" name="action" value="save_runtime">
        <div class="bc-field">
            <label><input type="checkbox" name="is_enabled" <?= !empty($runtime['is_enabled']) ? 'checked' : '' ?>> Enable built-in AI chat</label>
        </div>
        <div class="bc-field">
            <label for="assistant_name">Assistant name</label>
            <input class="bc-input" id="assistant_name" name="assistant_name" value="<?= bugcatcher_html((string) ($runtime['assistant_name'] ?? 'BugCatcher AI')) ?>">
        </div>
        <div class="bc-field">
            <label for="default_provider_config_id">Default provider</label>
            <select class="bc-select" id="default_provider_config_id" name="default_provider_config_id">
                <option value="0">Select provider</option>
                <?php foreach ($providers as $provider): ?>
                    <option value="<?= (int) $provider['id'] ?>" <?= (int) ($runtime['default_provider_config_id'] ?? 0) === (int) $provider['id'] ? 'selected' : '' ?>>
                        <?= bugcatcher_html($provider['display_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="bc-field">
            <label for="default_model_id">Default model</label>
            <select class="bc-select" id="default_model_id" name="default_model_id">
                <option value="0">Select model</option>
                <?php foreach ($models as $model): ?>
                    <option value="<?= (int) $model['id'] ?>" <?= (int) ($runtime['default_model_id'] ?? 0) === (int) $model['id'] ? 'selected' : '' ?>>
                        <?= bugcatcher_html(($model['provider_name'] ?? 'Provider') . ' - ' . $model['display_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="bc-field full">
            <label for="system_prompt">System prompt</label>
            <textarea class="bc-textarea" id="system_prompt" name="system_prompt" placeholder="Optional instructions for the built-in assistant."><?= bugcatcher_html((string) ($runtime['system_prompt'] ?? '')) ?></textarea>
        </div>
        <div class="bc-field full">
            <button type="submit" class="bc-btn">Save AI Runtime</button>
        </div>
    </form>
</div>

<div class="bc-grid cols-2">
    <div class="bc-panel">
        <h2>Add Provider</h2>
        <form method="post" class="bc-form-grid">
            <input type="hidden" name="action" value="save_provider">
            <input type="hidden" name="provider_id" value="0">
            <div class="bc-field"><label>Provider key</label><input class="bc-input" name="provider_key" placeholder="openai"></div>
            <div class="bc-field"><label>Display name</label><input class="bc-input" name="display_name" placeholder="OpenAI"></div>
            <div class="bc-field"><label>Provider type</label><input class="bc-input" name="provider_type" placeholder="openai-compatible"></div>
            <div class="bc-field"><label>Base URL</label><input class="bc-input" name="base_url" placeholder="https://api.openai.com/v1"></div>
            <div class="bc-field full"><label>API key</label><input class="bc-input" name="api_key" placeholder="sk-..." type="password"></div>
            <div class="bc-field"><label><input type="checkbox" name="is_enabled" checked> Enabled</label></div>
            <div class="bc-field"><label><input type="checkbox" name="supports_model_sync"> Supports model sync</label></div>
            <div class="bc-field full"><button type="submit" class="bc-btn">Save Provider</button></div>
        </form>
    </div>
    <div class="bc-table-wrap">
        <table class="bc-table">
            <thead><tr><th>Name</th><th>Type</th><th>Base URL</th><th>Key</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($providers as $provider): ?>
                <tr>
                    <td><?= bugcatcher_html($provider['display_name']) ?><br><span class="bc-meta"><?= bugcatcher_html($provider['provider_key']) ?></span></td>
                    <td><?= bugcatcher_html($provider['provider_type']) ?></td>
                    <td><?= bugcatcher_html($provider['base_url'] ?: 'Default') ?></td>
                    <td><?= bugcatcher_html((string) ($provider['api_key'] ?? 'Not set')) ?></td>
                    <td>
                        <form method="post">
                            <input type="hidden" name="action" value="delete_provider">
                            <input type="hidden" name="provider_id" value="<?= (int) $provider['id'] ?>">
                            <button class="bc-btn secondary" type="submit">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="bc-grid cols-2">
    <div class="bc-panel">
        <h2>Add Model</h2>
        <form method="post" class="bc-form-grid">
            <input type="hidden" name="action" value="save_model">
            <input type="hidden" name="model_id" value="0">
            <div class="bc-field">
                <label>Provider</label>
                <select class="bc-select" name="provider_config_id">
                    <option value="0">Select provider</option>
                    <?php foreach ($providers as $provider): ?>
                        <option value="<?= (int) $provider['id'] ?>"><?= bugcatcher_html($provider['display_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="bc-field"><label>Remote model id</label><input class="bc-input" name="remote_model_id" placeholder="gpt-4.1-mini"></div>
            <div class="bc-field full"><label>Display name</label><input class="bc-input" name="display_name" placeholder="GPT-4.1 Mini"></div>
            <div class="bc-field"><label><input type="checkbox" name="supports_vision" checked> Supports vision</label></div>
            <div class="bc-field"><label><input type="checkbox" name="supports_json_output" checked> Supports JSON</label></div>
            <div class="bc-field"><label><input type="checkbox" name="is_enabled" checked> Enabled</label></div>
            <div class="bc-field"><label><input type="checkbox" name="is_default"> Default for provider</label></div>
            <div class="bc-field full"><button type="submit" class="bc-btn">Save Model</button></div>
        </form>
    </div>
    <div class="bc-table-wrap">
        <table class="bc-table">
            <thead><tr><th>Provider</th><th>Model</th><th>Capabilities</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($models as $model): ?>
                <tr>
                    <td><?= bugcatcher_html($model['provider_name'] ?? 'Provider') ?></td>
                    <td><?= bugcatcher_html($model['display_name']) ?><?= (int) $model['is_default'] === 1 ? ' (default)' : '' ?><br><span class="bc-meta"><?= bugcatcher_html($model['model_id']) ?></span></td>
                    <td><?= $model['supports_vision'] ? 'Vision ' : '' ?><?= $model['supports_json_output'] ? 'JSON' : '' ?></td>
                    <td>
                        <form method="post">
                            <input type="hidden" name="action" value="delete_model">
                            <input type="hidden" name="model_id" value="<?= (int) $model['id'] ?>">
                            <button class="bc-btn secondary" type="submit">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php bugcatcher_shell_end(); ?>
