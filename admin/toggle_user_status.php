<?php
/**
 * EODB-TaskFlow - Deactivate / activate a user account (Administrator)
 *
 * POST only, with a CSRF token. Accounts are never deleted, so task history
 * and reports stay complete. A deactivated user is signed out on their next
 * page load (see enforce_session_rules() in includes/auth.php) and their
 * "Remember me" logins are removed.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/user_functions.php';
require_role('admin');

if (!is_post()) {
    redirect('admin/users.php');
}
require_csrf();

$userId = post_int('id');
$action = post_str('action', 20);

// Where to go afterwards: back to the list (keeping filters) or to the view page
$returnQuery = post_str('return', 300);
parse_str($returnQuery, $returnParams);
$allowed = array_intersect_key($returnParams, array_flip(['q', 'role', 'department', 'status', 'page']));
$back = post_str('redirect_to', 10) === 'view'
    ? 'admin/view_user.php?id=' . $userId
    : 'admin/users.php' . ($allowed ? '?' . http_build_query($allowed) : '');

$stmt = db()->prepare('SELECT id, employee_id, full_name, role, status FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user || !in_array($action, ['deactivate', 'activate'], true)) {
    set_flash('warning', 'Invalid request.');
    redirect('admin/users.php');
}

if ((int) $user['id'] === current_user_id()) {
    set_flash('danger', 'You cannot change the status of your own account.');
    redirect($back);
}

$newStatus = $action === 'deactivate' ? 'inactive' : 'active';

if ($user['status'] === $newStatus) {
    set_flash('info', "{$user['full_name']} is already " . ($newStatus === 'active' ? 'active' : 'inactive') . '.');
    redirect($back);
}

if ($action === 'deactivate' && $user['role'] === 'admin' && count_active_admins((int) $user['id']) === 0) {
    set_flash('danger', 'This is the only active Administrator and cannot be deactivated.');
    redirect($back);
}

$update = db()->prepare('UPDATE users SET status = ? WHERE id = ?');
$update->execute([$newStatus, $user['id']]);

if ($action === 'deactivate') {
    delete_remember_tokens_for_user((int) $user['id']);
    log_activity(current_user_id(), 'deactivate_user', "Deactivated user {$user['full_name']} ({$user['employee_id']}).");
    set_flash('success', "{$user['full_name']} was deactivated and can no longer log in.");

    $open = count_open_tasks((int) $user['id']);
    if ($open > 0) {
        set_flash('warning', "{$user['full_name']} still has {$open} unfinished task(s). Please reassign them to an active employee.");
    }
} else {
    log_activity(current_user_id(), 'activate_user', "Activated user {$user['full_name']} ({$user['employee_id']}).");
    set_flash('success', "{$user['full_name']} was activated and can log in again.");
}

redirect($back);
