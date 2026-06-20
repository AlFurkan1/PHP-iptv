<?php
require_once __DIR__ . '/../includes/db.php';
session_start();

if (!empty($_SESSION['admin_id'])) {
    logActivity($pdo, (int)$_SESSION['admin_id'], 'Logout', 'Admin logged out.');
}

$_SESSION = [];
session_destroy();

// Expire session cookie
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}

redirect('login.php');
