<?php
// ─── Login ───────────────────────────────────────────────────────────────────
require_once __DIR__ . '/auth.php';

if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    if (password_verify($password, APP_PASSWORD_HASH)) {
        session_regenerate_id(true);
        $_SESSION['authenticated'] = true;
        header('Location: index.php');
        exit;
    } else {
        $error = 'Invalid password. Please try again.';
    }
}
?><!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bookmarks – Login</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🔖</text></svg>">
    <link rel="stylesheet" href="assets/vendor/bootstrap.min.css">
    <link rel="stylesheet" href="assets/vendor/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="d-flex align-items-center justify-content-center min-vh-100 bm-bg">
    <div class="card bm-login-card shadow-lg" style="width: 100%; max-width: 380px;">
        <div class="card-body p-4 p-md-5">
            <div class="text-center mb-4">
                <span style="font-size: 2.5rem;">🔖</span>
                <h1 class="h4 mt-2 fw-bold">Bookmarks</h1>
                <p class="text-muted small">Private bookmark manager</p>
            </div>
            <?php if ($error): ?>
            <div class="alert alert-danger py-2 small" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-1"></i><?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>
            <form method="post" autocomplete="off">
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                        <input type="password" class="form-control" id="password" name="password"
                               placeholder="Enter password" autofocus required>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-box-arrow-in-right me-1"></i>Sign In
                </button>
            </form>
        </div>
    </div>
</body>
</html>
