<?php
/**
 * EODB-TaskFlow - Login page (Algorithm 1: Login Authentication)
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

// Already logged in (or restored from "Remember me"): go to the dashboard
if (is_logged_in()) {
    redirect(dashboard_path(current_user()['role']));
}

$error = '';
$login = '';
$remember = false;

$notices = [
    'logged_out' => ['success', 'You have been logged out successfully.'],
    'timeout'    => ['warning', 'Your session expired due to inactivity. Please log in again.'],
    'inactive'   => ['warning', 'Your account is no longer active. Please contact the system administrator.'],
    'session'    => ['warning', 'Your session ended for security reasons. Please log in again.'],
];
$reason = get_str('reason', 20);
$notice = $notices[$reason] ?? null;

if (is_post()) {
    // Step 1: the user submits the username/email and password
    $login    = post_str('login', 150);
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $remember = ($_POST['remember'] ?? '') === '1';

    if (!csrf_valid($_POST['csrf_token'] ?? null)) {
        $error = 'Your login form has expired. Please try again.';
        regenerate_csrf_token();
    } else {
        // Steps 2-6 are performed by attempt_login() in includes/auth.php
        $result = attempt_login($login, $password, $remember);

        if ($result === true) {
            // Step 7: redirect the user to the dashboard of their role
            redirect(dashboard_path(current_user()['role']));
        }

        // Step 8: invalid login - show the error message
        $error = (string) $result;
    }
    $notice = null;
}

$officeName = office_name();

// Presentation only: icon for each kind of status message
$noticeIcons = [
    'success' => 'fa-circle-info',
    'warning' => 'fa-triangle-exclamation',
    'danger'  => 'fa-circle-exclamation',
    'info'    => 'fa-circle-info',
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login | <?= e(app_name()) ?> - <?= e($officeName) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= e(asset('images/logo.svg')) ?>">
    <link rel="stylesheet" href="<?= e(CDN_BOOTSTRAP_CSS) ?>">
    <link rel="stylesheet" href="<?= e(CDN_FONTAWESOME_CSS) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/login.css')) ?>">
</head>
<body class="login-page">

<main class="login-wrapper">
    <div class="login-card">

        <!-- Branding panel -->
        <section class="login-brand">
            <span class="login-logo-wrap">
                <img src="<?= e(asset('images/mho-logo.png')) ?>" alt="Municipal Health Office, Mulanay, Quezon"
                     class="login-logo" width="112" height="112">
            </span>
            <p class="login-office text-uppercase"><?= e($officeName) ?></p>
            <h1 class="login-title"><?= e(app_name()) ?></h1>
            <p class="login-subtitle"><?= e(APP_FULL_NAME) ?></p>
            <ul class="login-points list-unstyled d-none d-md-block">
                <li><i class="fa-solid fa-list-check" aria-hidden="true"></i> Task assignment and monitoring</li>
                <li><i class="fa-solid fa-bars-progress" aria-hidden="true"></i> Progress and deadline tracking</li>
                <li><i class="fa-solid fa-chart-column" aria-hidden="true"></i> Productivity summaries and reports</li>
            </ul>
        </section>

        <!-- Login form -->
        <section class="login-form-panel">
            <h2 class="h4 mb-1">Sign in to your account</h2>
            <p class="text-muted small mb-4">Use your EODB-TaskFlow account to continue.</p>

            <?php if ($notice !== null): ?>
                <div class="alert-pill alert-pill-<?= e($notice[0]) ?>" role="status">
                    <span class="alert-pill-icon" aria-hidden="true"><i class="fa-solid <?= e($noticeIcons[$notice[0]] ?? 'fa-circle-info') ?>"></i></span>
                    <span><?= e($notice[1]) ?></span>
                </div>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
                <div class="alert-pill alert-pill-danger" role="alert">
                    <span class="alert-pill-icon" aria-hidden="true"><i class="fa-solid fa-circle-exclamation"></i></span>
                    <span><?= e($error) ?></span>
                </div>
            <?php endif; ?>

            <form method="post" action="<?= e(url('login.php')) ?>" id="loginForm" novalidate>
                <?= csrf_field() ?>

                <div class="mb-3">
                    <label for="login" class="form-label">Username or Email</label>
                    <div class="input-icon">
                        <i class="fa-solid fa-user input-icon-lead" aria-hidden="true"></i>
                        <input type="text" class="form-control" id="login" name="login"
                               value="<?= e($login) ?>" maxlength="150" autocomplete="username"
                               placeholder="Enter your username or email" required autofocus>
                    </div>
                    <div class="invalid-feedback d-block small" id="loginError" hidden>Please enter your username or email.</div>
                </div>

                <div class="mb-2">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-icon has-action">
                        <i class="fa-solid fa-lock input-icon-lead" aria-hidden="true"></i>
                        <input type="password" class="form-control" id="password" name="password"
                               maxlength="255" autocomplete="current-password"
                               placeholder="Enter your password" required>
                        <button type="button" class="input-icon-action" id="togglePassword"
                                aria-label="Show password" aria-pressed="false">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                    <div class="invalid-feedback d-block small" id="passwordError" hidden>Please enter your password.</div>
                    <div class="form-text text-warning" id="capsWarning" hidden>
                        <i class="fa-solid fa-triangle-exclamation"></i> Caps Lock is on.
                    </div>
                </div>

                <div class="form-check mb-4">
                    <input class="form-check-input" type="checkbox" value="1" id="remember" name="remember"
                        <?= $remember ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="remember">
                        Keep me signed in for <?= (int) REMEMBER_ME_DAYS ?> days
                        <span class="d-block text-muted">Do not use on shared or public computers.</span>
                    </label>
                </div>

                <button type="submit" class="btn btn-login w-100" id="loginButton">
                    <i class="fa-solid fa-right-to-bracket me-1"></i> Log In
                </button>
            </form>

            <p class="login-help mb-0">
                Forgot your password? Please contact the system administrator.
            </p>
        </section>
    </div>

    <p class="login-footer text-muted small text-center">
        &copy; <?= date('Y') ?> <?= e($officeName) ?> &middot; <?= e(app_name()) ?> v<?= e(APP_VERSION) ?>
    </p>
</main>

<script src="<?= e(asset('js/login.js')) ?>"></script>
</body>
</html>
