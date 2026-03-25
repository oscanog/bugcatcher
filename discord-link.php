<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/app/checklist_shell.php';

http_response_code(410);

$context = [
    'current_username' => $current_username,
    'current_role' => $current_role,
    'org_role' => null,
    'org_name' => 'Removed Integration',
];

bugcatcher_shell_start('Discord Link Removed', 'dashboard', $context);
?>

<div class="bc-alert error">Discord account linking has been retired. Use the built-in AI chat instead.</div>
<div class="bc-panel">
    <h2>What changed</h2>
    <p>The old Discord/OpenClaw link flow is no longer supported. New AI setup now lives under the super-admin AI configuration page.</p>
</div>

<?php bugcatcher_shell_end(); ?>
