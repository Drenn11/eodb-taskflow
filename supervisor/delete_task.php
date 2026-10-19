<?php
/**
 * EODB-TaskFlow - Delete a task (Supervisor)
 *
 * POST only, with a CSRF token and a confirmation prompt. Completed tasks
 * cannot be deleted because they are accomplishment records. Deleting a task
 * also removes its history (ON DELETE CASCADE); its notifications are kept
 * with task_id set to NULL. The deletion is recorded in the activity log.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/task_functions.php';
require_role('supervisor');

if (!is_post()) {
    redirect('supervisor/tasks.php');
}
require_csrf();

$taskId = post_int('id');
$task = $taskId > 0 ? get_task($taskId) : null;

if ($task === null) {
    set_flash('warning', 'The selected task was not found.');
    redirect('supervisor/tasks.php');
}

if ($task['status'] === 'Completed') {
    set_flash('danger', "{$task['task_code']} is completed and cannot be deleted. Completed tasks are kept as accomplishment records.");
    redirect('supervisor/task_details.php?id=' . $taskId);
}

$pdo = db();
try {
    $pdo->beginTransaction();

    $delete = $pdo->prepare('DELETE FROM tasks WHERE id = ?');
    $delete->execute([$taskId]);

    create_notification(
        (int) $task['assigned_to'],
        null,
        'Task Removed',
        "{$task['task_code']} - {$task['title']} was removed by your supervisor. No further action is needed.",
        'system'
    );

    log_activity(
        current_user_id(),
        'delete_task',
        "Deleted {$task['task_code']} - {$task['title']} (assigned to {$task['employee_name']}, status {$task['status']}, progress {$task['progress']}%)."
    );

    $pdo->commit();
    set_flash('success', "{$task['task_code']} was deleted.");
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[EODB-TaskFlow] delete_task failed: ' . $e->getMessage());
    set_flash('danger', 'The task could not be deleted. Please try again.');
    redirect('supervisor/task_details.php?id=' . $taskId);
}

redirect('supervisor/tasks.php');
