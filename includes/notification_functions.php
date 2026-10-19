<?php
/**
 * EODB-TaskFlow - Notification functions (Algorithm 5)
 *
 * create_notification() is used when tasks are assigned, updated, completed,
 * or become overdue. The notification list page, "mark as read", and the
 * automatic deadline/overdue reminders are added in Phase 7.
 */

declare(strict_types=1);

const NOTIFICATION_TYPES = [
    'task_assigned', 'deadline_upcoming', 'task_overdue', 'task_completed', 'task_updated', 'system',
];

/** Short labels and icons for each notification type. */
const NOTIFICATION_TYPE_INFO = [
    'task_assigned'     => ['New task',          'fa-clipboard-list'],
    'deadline_upcoming' => ['Deadline approaching', 'fa-hourglass-half'],
    'task_overdue'      => ['Overdue',           'fa-triangle-exclamation'],
    'task_completed'    => ['Completed',         'fa-circle-check'],
    'task_updated'      => ['Task update',       'fa-pen-to-square'],
    'system'            => ['System',            'fa-circle-info'],
];

function notification_icon(string $type): string
{
    return NOTIFICATION_TYPE_INFO[$type][1] ?? 'fa-circle-info';
}

function notification_type_label(string $type): string
{
    return NOTIFICATION_TYPE_INFO[$type][0] ?? 'System';
}

function unread_notification_count(int $userId): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn();
}

/** Marks one notification as read. Only the owner's own notification is changed. */
function mark_notification_read(int $notificationId, int $userId): bool
{
    $stmt = db()->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?');
    $stmt->execute([$notificationId, $userId]);
    return $stmt->rowCount() > 0;
}

/** Marks all of a user's notifications as read. Returns how many changed. */
function mark_all_notifications_read(int $userId): int
{
    $stmt = db()->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0');
    $stmt->execute([$userId]);
    return $stmt->rowCount();
}

/**
 * The page a notification opens for the given role: the related task's details,
 * or null when the task no longer exists or is not visible to that user.
 */
function notification_target(array $notification, string $role, int $userId): ?string
{
    if (empty($notification['task_id'])) {
        return null;
    }
    $taskId = (int) $notification['task_id'];

    if ($role === 'employee') {
        // Employees can only open tasks that are currently assigned to them
        $stmt = db()->prepare('SELECT COUNT(*) FROM tasks WHERE id = ? AND assigned_to = ?');
        $stmt->execute([$taskId, $userId]);
        return (int) $stmt->fetchColumn() > 0 ? 'employee/task_details.php?id=' . $taskId : null;
    }

    $stmt = db()->prepare('SELECT COUNT(*) FROM tasks WHERE id = ?');
    $stmt->execute([$taskId]);
    if ((int) $stmt->fetchColumn() === 0) {
        return null;
    }
    return ($role === 'admin' ? 'admin' : 'supervisor') . '/task_details.php?id=' . $taskId;
}

// =============================================================================
// ALGORITHM 5 - NOTIFICATION: approaching deadlines
// =============================================================================

/**
 * Reminds employees of unfinished tasks whose deadline is within the reminder
 * period (Settings → deadline_reminder_days). At most one reminder per task per
 * day: "due in 2 days", then "due tomorrow", then "due today".
 *
 * @return int number of reminders sent
 */
function send_deadline_reminders(): int
{
    $days = max(1, min(14, (int) get_setting('deadline_reminder_days', DEADLINE_REMINDER_DAYS)));

    $stmt = db()->prepare(
        "SELECT t.id, t.task_code, t.title, t.deadline, t.assigned_to, DATEDIFF(t.deadline, CURDATE()) AS days_left
           FROM tasks t
           JOIN users u ON u.id = t.assigned_to AND u.status = 'active'
          WHERE t.status IN ('Pending', 'In Progress')
            AND t.deadline BETWEEN CURDATE() AND CURDATE() + INTERVAL ? DAY
            AND NOT EXISTS (
                SELECT 1 FROM notifications n
                 WHERE n.task_id = t.id
                   AND n.user_id = t.assigned_to
                   AND n.type = 'deadline_upcoming'
                   AND n.created_at >= CURDATE()
            )"
    );
    $stmt->execute([$days]);

    $sent = 0;
    foreach ($stmt->fetchAll() as $task) {
        $left = (int) $task['days_left'];
        $when = match (true) {
            $left === 0 => 'is due today',
            $left === 1 => 'is due tomorrow',
            default     => "is due in {$left} days",
        };
        create_notification(
            (int) $task['assigned_to'],
            (int) $task['id'],
            'Deadline Approaching',
            "Task deadline is approaching: {$task['task_code']} - {$task['title']} {$when} (" . format_date($task['deadline']) . ').',
            'deadline_upcoming'
        );
        $sent++;
    }
    return $sent;
}

/** Saves one notification for one user. A failure is logged but never stops the main action. */
function create_notification(int $userId, ?int $taskId, string $title, string $message, string $type): void
{
    if (!in_array($type, NOTIFICATION_TYPES, true)) {
        $type = 'system';
    }

    try {
        $stmt = db()->prepare(
            'INSERT INTO notifications (user_id, task_id, title, message, type) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $userId,
            $taskId,
            mb_substr($title, 0, 150),
            mb_substr($message, 0, 500),
            $type,
        ]);
    } catch (PDOException $e) {
        error_log('[EODB-TaskFlow] Notification failed: ' . $e->getMessage());
    }
}
