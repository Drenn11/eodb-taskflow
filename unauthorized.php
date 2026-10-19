<?php
/**
 * EODB-TaskFlow - 403 Access Denied page
 *
 * Shown by deny_access() when a user opens a page that their role is not
 * allowed to use. It can also be opened directly.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if (http_response_code() !== 403) {
    http_response_code(403);
}
send_no_cache_headers();

$viewer = current_user();
$homeUrl = $viewer ? url(dashboard_path($viewer['role'])) : url('login.php');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Access Denied | <?= e(app_name()) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= e(asset('images/logo.svg')) ?>">
    <link rel="stylesheet" href="<?= e(CDN_BOOTSTRAP_CSS) ?>">
    <link rel="stylesheet" href="<?= e(CDN_FONTAWESOME_CSS) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
</head>
<body class="login-page">
<main class="login-wrapper">
    <div class="card shadow-sm message-card">
        <div class="card-body p-4 text-center">
            <div class="message-icon message-icon-danger"><i class="fa-solid fa-lock" aria-hidden="true"></i></div>
            <div class="error-code">403</div>
            <h1 class="h5 mb-2">Access Denied</h1>
            <p class="text-muted small mb-4">
                You do not have permission to open this page.
                <?php if ($viewer): ?>
                    Your account role is <strong><?= e(role_label($viewer['role'])) ?></strong>.
                <?php endif; ?>
                This attempt has been recorded in the activity log.
            </p>
            <a href="<?= e($homeUrl) ?>" class="btn btn-primary">
                <i class="fa-solid fa-house me-1"></i>
                <?= $viewer ? 'Back to My Dashboard' : 'Go to Login' ?>
            </a>
        </div>
    </div>
</main>
</body>
</html>
