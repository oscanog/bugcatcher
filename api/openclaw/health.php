<?php

require_once __DIR__ . '/_shared.php';

bugcatcher_openclaw_require_internal_request();
bugcatcher_openclaw_json_response(410, ['error' => 'OpenClaw Discord runtime health has been retired.']);
