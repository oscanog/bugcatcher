<?php
require_once __DIR__ . '/app/bootstrap.php';

bugcatcher_start_session();

try {
    $conn = bugcatcher_db_connection();
} catch (RuntimeException $e) {
    die($e->getMessage());
}

if (!isset($_SESSION['id'])) {
    $loginLocation = bugcatcher_is_known_user_browser()
        ? 'register-passed-by-maglaque/login.php?reason=expired'
        : 'register-passed-by-maglaque/login.php';
    header("Location: {$loginLocation}");
    exit();
}

$current_user_id = (int) $_SESSION['id'];
$current_username = $_SESSION['username'] ?? 'User';
$current_role = ($_SESSION['role'] ?? 'user') === 'admin' ? 'admin' : 'user';
