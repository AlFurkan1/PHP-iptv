<?php
require_once __DIR__ . '/../includes/db.php';
session_start();

// Redirect if already logged in
if (!empty($_SESSION['admin_id'])) {
    redirect('dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    if (!csrfVerify($_POST['_csrf'] ?? '')) {
        $error = 'Invalid session token. Please try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '' || $password === '') {
            $error = 'Please fill in all fields.';
        } else {
            $stmt = $pdo->prepare(
                'SELECT id, username, password, role FROM admins WHERE username = :u LIMIT 1'
            );
            $stmt->execute([':u' => $username]);
            $admin = $stmt->fetch();

            if ($admin && password_verify($password, $admin['password'])) {
                // Regenerate session to prevent fixation
                session_regenerate_id(true);
                $_SESSION['admin_id']  = (int)$admin['id'];
                $_SESSION['admin_user'] = $admin['username'];
                $_SESSION['admin_role'] = $admin['role'];

                logActivity($pdo, $admin['id'], 'Login', 'Admin logged in successfully.');

                redirect('dashboard.php');
            } else {
                $error = 'Invalid username or password.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login — IPTV</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css?v=<?= time() ?>" rel="stylesheet">
</head>
<body class="login-page">
    <div class="login-card">
        <h2>Panel Login</h2>
        <?php if ($error): ?>
        <div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="post">
            <input type="hidden" name="_csrf" value="<?= csrfToken() ?>">
            <div class="mb-3">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-control" required autocomplete="username">
            </div>
            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn-accent">Sign In</button>
        </form>
    </div>
<div class="admin-footer" style="margin-top:20px;text-align:center;color:#666;font-size:0.8rem">
    &copy; 2026 <a href="https://hub.docker.com/u/alfurkan1" style="color:#00bcd4;text-decoration:none" target="_blank">Al Furkan</a>. All rights reserved.
</div>
</body>
</html>
