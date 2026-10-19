<?php
/**
 * EODB-TaskFlow - Friendly error page for Apache 403 and 404 errors
 *
 * Used by "ErrorDocument" in the root .htaccess, e.g. when someone opens a
 * page that does not exist or a protected folder such as config/. It shows no
 * server details and does not need the database.
 */

declare(strict_types=1);

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/functions.php';

$status = (int) ($_SERVER['REDIRECT_STATUS'] ?? 404);
if (!in_array($status, [403, 404], true)) {
    $status = 404;
}
http_response_code($status);
send_security_headers();
send_no_cache_headers();

$title   = $status === 403 ? 'Access Denied' : 'Page Not Found';
$message = $status === 403
    ? 'You do not have permission to open this address.'
    : 'The page you are looking for does not exist or may have been moved.';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> | <?= e(APP_NAME) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= e(asset('images/logo.svg')) ?>">
    <link rel="stylesheet" href="<?= e(CDN_BOOTSTRAP_CSS) ?>">
    <link rel="stylesheet" href="<?= e(CDN_FONTAWESOME_CSS) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
</head>
<body class="login-page">
<main class="login-wrapper">
    <div class="card shadow-sm message-card">
        <div class="card-body p-4 text-center">
            <div class="message-icon<?= $status === 403 ? ' message-icon-danger' : '' ?>"><i class="fa-solid <?= $status === 403 ? 'fa-lock' : 'fa-compass' ?>" aria-hidden="true"></i></div>
            <div class="error-code"><?= $status ?></div>
            <h1 class="h5 mb-2"><?= e($title) ?></h1>
            <p class="text-muted small mb-4"><?= e($message) ?></p>
            <a href="<?= e(url('index.php')) ?>" class="btn btn-primary">
                <i class="fa-solid fa-house me-1"></i> Go to <?= e(APP_NAME) ?>
            </a>
        </div>
    </div>
</main>
</body>
</html>
