<?php
http_response_code(403);
header('Content-Type: text/plain; charset=UTF-8');

echo "setup.php is disabled.\n";
echo "Use infra/database/schema.sql, infra/database/seed_reference_data.sql, and infra/database/seed_admin.sql instead.\n";
