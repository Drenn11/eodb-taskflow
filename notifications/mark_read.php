<?php
/**
 * EODB-TaskFlow - Mark notifications as read
 *
 * POST with CSRF token:
 *   id=<n>   marks one of the user's own notifications as read
 *   all=1    marks all of the user's notifications as read
 * Then returns to the page given in "return" (only pages inside this system).
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/notification_functions.php';
require_login();

if (!is_post()) {
    redirect('notifications/index.php');
}
require_csrf();

$userId = current_user_id();
$return = safe_return_path(post_str('return', 300), 'notifications/index.php');

if (post_str('all', 1) === '1') {
    $count = mark_all_notifications_read($userId);
    set_flash('success', $count > 0 ? "{$count} notification(s) marked as read." : 'All notifications were already read.');
} elseif (post_int('id') > 0) {
    mark_notification_read(post_int('id'), $userId);
}

redirect($return);
