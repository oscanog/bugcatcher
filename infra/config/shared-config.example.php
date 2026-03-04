<?php

return [
    'APP_ENV' => 'production',
    'APP_BASE_URL' => 'https://bugcatcher.example.com',
    'DB_HOST' => '127.0.0.1',
    'DB_PORT' => 3306,
    'DB_NAME' => 'bug_catcher',
    'DB_USER' => 'bugcatcher_app',
    'DB_PASS' => 'replace-with-a-strong-random-password',
    'UPLOADS_DIR' => '/var/www/bugcatcher/shared/uploads/issues',
    'UPLOADS_URL' => 'uploads/issues',
    'CHECKLIST_UPLOADS_DIR' => '/var/www/bugcatcher/shared/uploads/checklists',
    'CHECKLIST_UPLOADS_URL' => 'uploads/checklists',
    'CHECKLIST_BOT_SHARED_SECRET' => 'replace-with-a-long-random-secret',
    'OPENCLAW_INTERNAL_SHARED_SECRET' => 'replace-with-a-second-long-random-secret',
    'OPENCLAW_ENCRYPTION_KEY' => 'replace-with-a-32-byte-secret',
    'OPENCLAW_TEMP_UPLOAD_DIR' => '/var/www/bugcatcher/shared/uploads/openclaw-tmp',
    'OPENCLAW_LOG_LEVEL' => 'info',
];
