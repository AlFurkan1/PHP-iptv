<?php
// Session-guard: include at the top of every admin page
session_start();

if (empty($_SESSION['admin_id'])) {
    redirect('login.php');
}

/**
 * Require a specific role. Usage: requireRole('superadmin');
 */
function requireRole(string $role): void
{
    if ($_SESSION['admin_role'] !== $role) {
        http_response_code(403);
        exit('Access denied.');
    }
}
