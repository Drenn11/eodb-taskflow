<?php
/**
 * EODB-TaskFlow - Open a notification
 *
 * POST with CSRF token: marks the notification as read and opens the related
 * task page for the user's role (or the notification list if there is none).
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/notification_functions.php';
require_login();

if (!is_post()) {
    redirect('notifications/index.php');
}
require_csrf();

$user = current_user();
$stmt = db()->prepare('SELECT id, task_id FROM notifications WHERE id = ? AND user_id = ? LIMIT 1');
$stmt->execute([post_int('id'), $user['id']]);
$notification = $stmt->fetch();

if (!$notification) {
    set_flash('warning', 'The notification was not found.');
    redirect('notifications/index.php');
}

mark_notification_read((int) $notification['id'], (int) $user['id']);

$target = notification_target($notification, $user['role'], (int) $user['id']);
if ($target === null) {
    set_flash('info', 'The task related to this notification is no longer available to you.');
    redirect('notifications/index.php');
}
redirect($target);
