<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

echo json_encode([
    'enabled' => (bool) bugcatcher_config('UPLOADTHING_ENABLED', false),
    'token' => (string) bugcatcher_config('UPLOADTHING_TOKEN', ''),
    'host' => (string) bugcatcher_config('UPLOADTHING_BRIDGE_HOST', '127.0.0.1'),
    'port' => (int) bugcatcher_config('UPLOADTHING_BRIDGE_PORT', 8091),
    'internal_shared_secret' => (string) bugcatcher_config('UPLOADTHING_BRIDGE_INTERNAL_SHARED_SECRET', ''),
], JSON_THROW_ON_ERROR);
