<?php
/**
 * EODB-TaskFlow - Entry point
 *
 * http://localhost/eodb-taskflow/ sends logged-in users to the dashboard of
 * their role, and everyone else to the login page.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    redirect(dashboard_path(current_user()['role']));
}

$reason = $GLOBALS['eodb_logout_reason'] ?? '';
redirect('login.php' . ($reason !== '' ? '?reason=' . urlencode($reason) : ''));
