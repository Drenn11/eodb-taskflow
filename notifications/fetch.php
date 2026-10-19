<?php
/**
 * EODB-TaskFlow - Unread notification count (JSON)
 *
 * Called every 60 seconds by assets/js/app.js to refresh the bell badge.
 * It is a "passive" request: it does not reset the inactivity timer, so an
 * idle session still expires. Returns {"unread": n} or HTTP 401 when the
 * session has ended.
 */

declare(strict_types=1);

define('EODB_PASSIVE_REQUEST', true);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/notification_functions.php';

header('Content-Type: application/json; charset=utf-8');
send_no_cache_headers();

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['unread' => 0, 'logged_in' => false]);
    exit;
}

echo json_encode(['unread' => unread_notification_count(current_user_id()), 'logged_in' => true]);
