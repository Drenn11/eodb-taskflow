<?php
/**
 * EODB-TaskFlow - Secure logout
 *
 * Logout only happens through a POST request with a valid CSRF token, so other
 * websites cannot log users out with a hidden link or image.
 * Opening this page directly (GET) shows a confirmation button instead.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if (!is_logged_in()) {
    redirect('login.php');
}

if (is_post()) {
    require_csrf();
    logout_user();
    redirect('login.php?reason=logged_out');
}

send_no_cache_headers();
$user = current_user();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Log Out | <?= e(app_name()) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= e(asset('images/logo.svg')) ?>">
    <link rel="stylesheet" href="<?= e(CDN_BOOTSTRAP_CSS) ?>">
    <link rel="stylesheet" href="<?= e(CDN_FONTAWESOME_CSS) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
</head>
<body class="login-page">
<main class="login-wrapper">
    <div class="card shadow-sm message-card">
        <div class="card-body p-4 text-center">
            <div class="message-icon"><i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i></div>
            <h1 class="h5">Log out of <?= e(app_name()) ?>?</h1>
            <p class="text-muted small">You are signed in as <strong><?= e($user['full_name']) ?></strong>.</p>
            <form method="post" action="<?= e(url('logout.php')) ?>" class="d-flex gap-2 justify-content-center">
                <?= csrf_field() ?>
                <a href="<?= e(url(dashboard_path($user['role']))) ?>" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Log Out</button>
            </form>
        </div>
    </div>
</main>
</body>
</html>
