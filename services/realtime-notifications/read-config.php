<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

$internalSecret = trim((string) bugcatcher_config('REALTIME_NOTIFICATIONS_INTERNAL_SHARED_SECRET', ''));
if ($internalSecret === '') {
    $internalSecret = trim((string) bugcatcher_config('OPENCLAW_INTERNAL_SHARED_SECRET', ''));
}
if ($internalSecret === '' || $internalSecret === 'replace-me-too') {
    $internalSecret = 'bugcatcher-realtime-dev-secret';
}

$socketSecret = trim((string) bugcatcher_config('REALTIME_NOTIFICATIONS_SOCKET_SECRET', ''));
if ($socketSecret === '') {
    $socketSecret = trim((string) bugcatcher_config('OPENCLAW_ENCRYPTION_KEY', ''));
}
if ($socketSecret === '' || $socketSecret === 'replace-with-32-byte-secret') {
    $socketSecret = trim((string) bugcatcher_config('OPENCLAW_INTERNAL_SHARED_SECRET', ''));
}
if ($socketSecret === '' || $socketSecret === 'replace-me-too') {
    $socketSecret = 'bugcatcher-realtime-dev-secret';
}

echo json_encode([
    'enabled' => (bool) bugcatcher_config('REALTIME_NOTIFICATIONS_ENABLED', true),
    'host' => (string) bugcatcher_config('REALTIME_NOTIFICATIONS_HOST', '127.0.0.1'),
    'port' => (int) bugcatcher_config('REALTIME_NOTIFICATIONS_PORT', 8090),
    'path' => (string) bugcatcher_config('REALTIME_NOTIFICATIONS_PATH', '/ws/notifications'),
    'internal_shared_secret' => $internalSecret,
    'socket_secret' => $socketSecret,
], JSON_UNESCAPED_SLASHES);
