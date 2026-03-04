<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/app/openclaw_lib.php';
require_once __DIR__ . '/app/checklist_shell.php';

$flash = '';
$error = '';
$generatedCode = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'generate_code') {
        $generatedCode = bugcatcher_openclaw_generate_link_code();
        bugcatcher_openclaw_store_link_code($conn, $current_user_id, $generatedCode);
        $flash = 'A new Discord link code was generated. It will expire in 10 minutes.';
    } elseif ($action === 'unlink') {
        bugcatcher_openclaw_unlink_user($conn, $current_user_id);
        $flash = 'Your Discord account link was removed.';
    } else {
        $error = 'Unknown action.';
    }
}

$link = bugcatcher_openclaw_fetch_user_link_by_user($conn, $current_user_id);
$context = [
    'current_username' => $current_username,
    'current_role' => $current_role,
    'org_role' => null,
    'org_name' => 'Account Integrations',
];

bugcatcher_shell_start('Discord Link', 'discord_link', $context);
?>

<?php if ($flash): ?>
    <div class="bc-alert success"><?= bugcatcher_html($flash) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="bc-alert error"><?= bugcatcher_html($error) ?></div>
<?php endif; ?>

<div class="bc-grid cols-2">
    <div class="bc-panel">
        <h2>Link Your Discord Account</h2>
        <p class="bc-meta">OpenClaw requires a linked BugCatcher account before she can create checklist batches from Discord.</p>
        <div class="bc-kv">
            <div class="bc-kv-row">
                <strong>Status</strong>
                <span><?= !empty($link['discord_user_id']) && (int) ($link['is_active'] ?? 0) === 1 ? 'Linked' : 'Not linked' ?></span>
            </div>
            <div class="bc-kv-row">
                <strong>Discord user</strong>
                <span><?= bugcatcher_html($link['discord_username'] ?? $link['discord_global_name'] ?? 'None') ?></span>
            </div>
            <div class="bc-kv-row">
                <strong>Linked at</strong>
                <span><?= bugcatcher_html(bugcatcher_checklist_format_datetime($link['linked_at'] ?? null)) ?></span>
            </div>
            <div class="bc-kv-row">
                <strong>Last seen</strong>
                <span><?= bugcatcher_html(bugcatcher_checklist_format_datetime($link['last_seen_at'] ?? null)) ?></span>
            </div>
        </div>
    </div>

    <div class="bc-panel">
        <h2>How To Link</h2>
        <ol>
            <li>Click <span class="bc-code">Generate Link Code</span>.</li>
            <li>Open Discord and send OpenClaw a DM like <span class="bc-code">link YOURCODE</span>.</li>
            <li>Wait for OpenClaw to confirm the connection.</li>
            <li>Return here if you need to unlink or generate a replacement code.</li>
        </ol>

        <?php if ($generatedCode): ?>
            <div class="bc-alert info">
                Your one-time code is <strong class="bc-code"><?= bugcatcher_html($generatedCode) ?></strong>.
            </div>
        <?php endif; ?>

        <div class="bc-inline">
            <form method="post">
                <input type="hidden" name="action" value="generate_code">
                <button type="submit" class="bc-btn">Generate Link Code</button>
            </form>
            <form method="post">
                <input type="hidden" name="action" value="unlink">
                <button type="submit" class="bc-btn secondary">Unlink Discord</button>
            </form>
        </div>
    </div>
</div>

<div class="bc-card">
    <h2>OpenClaw Expectations</h2>
    <ul>
        <li>OpenClaw only works in approved Discord channels and DM follow-up flows.</li>
        <li>At least one image is required before a Discord checklist batch can be generated.</li>
        <li>You must select an organization and project that you actually belong to in BugCatcher.</li>
        <li>Checklist batches created from Discord are attributed to your BugCatcher account.</li>
    </ul>
</div>

<?php bugcatcher_shell_end(); ?>
