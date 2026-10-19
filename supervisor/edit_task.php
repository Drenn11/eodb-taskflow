<?php
/**
 * EODB-TaskFlow - Edit / reassign a task (Supervisor)
 *
 * Changes title, description, assigned employee, priority, deadline, and
 * remarks. The status is recalculated with Algorithm 3 (e.g. extending the
 * deadline of an Overdue task returns it to In Progress). Completed tasks are
 * locked so accomplishment records stay unchanged.
 *
 * Records: task history, notifications, and activity log (update_task / assign_task).
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/task_functions.php';
require_role('supervisor');

$taskId = is_post() ? post_int('id') : get_int('id');
$task = $taskId > 0 ? get_task($taskId) : null;

if ($task === null) {
    set_flash('warning', 'The selected task was not found.');
    redirect('supervisor/tasks.php');
}

if ($task['status'] === 'Completed') {
    set_flash('info', "{$task['task_code']} is already completed. Completed tasks are locked to keep the accomplishment record unchanged.");
    redirect('supervisor/task_details.php?id=' . $taskId);
}

$employees = get_assignable_employees();
$errors = [];
$form = [
    'title'       => $task['title'],
    'description' => (string) $task['description'],
    'assigned_to' => (int) $task['assigned_to'],
    'priority'    => $task['priority'],
    'deadline'    => $task['deadline'],
    'remarks'     => (string) $task['remarks'],
];

if (is_post()) {
    require_csrf();

    $data = read_task_form();
    $form = array_merge($form, array_intersect_key($data, $form));
    $errors = validate_task_form($data, $task);

    if (!$errors) {
        $labels = [
            'title'       => 'title',
            'description' => 'description',
            'priority'    => 'priority',
            'deadline'    => 'deadline',
            'remarks'     => 'remarks',
        ];
        $changes = [];
        foreach ($labels as $field => $label) {
            if ((string) $task[$field] !== (string) $data[$field]) {
                $changes[] = $label;
            }
        }
        $reassigned = (int) $task['assigned_to'] !== $data['assigned_to'];

        if (!$changes && !$reassigned) {
            set_flash('info', 'No changes were made.');
            redirect('supervisor/task_details.php?id=' . $taskId);
        }

        $progress  = (int) $task['progress'];
        $oldStatus = $task['status'];
        $newStatus = compute_task_status($progress, $data['deadline']);   // Algorithm 3
        $pdo = db();

        try {
            $pdo->beginTransaction();

            $update = $pdo->prepare(
                'UPDATE tasks
                    SET title = ?, description = ?, assigned_to = ?, priority = ?, deadline = ?, remarks = ?, status = ?
                  WHERE id = ?'
            );
            $update->execute([
                $data['title'],
                $data['description'] !== '' ? $data['description'] : null,
                $data['assigned_to'],
                $data['priority'],
                $data['deadline'],
                $data['remarks'] !== '' ? $data['remarks'] : null,
                $newStatus,
                $taskId,
            ]);

            $newEmployee = $reassigned ? get_task($taskId)['employee_name'] : $task['employee_name'];

            // Task history entries for the important changes
            $historyNotes = [];
            if ($reassigned) {
                $historyNotes[] = "Reassigned from {$task['employee_name']} to {$newEmployee}.";
            }
            if ($task['deadline'] !== $data['deadline']) {
                $historyNotes[] = 'Deadline changed from ' . format_date($task['deadline']) . ' to ' . format_date($data['deadline']) . '.';
            }
            if ($task['priority'] !== $data['priority']) {
                $historyNotes[] = "Priority changed from {$task['priority']} to {$data['priority']}.";
            }
            if ($historyNotes || $oldStatus !== $newStatus) {
                record_task_update(
                    $taskId,
                    current_user_id(),
                    $oldStatus,
                    $newStatus,
                    $progress,
                    $progress,
                    implode(' ', $historyNotes) ?: 'Task details updated.'
                );
            }

            // Notifications
            if ($reassigned) {
                create_notification(
                    $data['assigned_to'],
                    $taskId,
                    'New Task Assigned',
                    "New task assigned to you: {$task['task_code']} - {$data['title']}. Deadline: " . format_date($data['deadline']) . '.',
                    'task_assigned'
                );
                create_notification(
                    (int) $task['assigned_to'],
                    $taskId,
                    'Task Reassigned',
                    "{$task['task_code']} - {$task['title']} was reassigned to another employee. No further action is needed from you.",
                    'system'
                );
                log_activity(
                    current_user_id(),
                    'assign_task',
                    "Reassigned {$task['task_code']} from {$task['employee_name']} to {$newEmployee}."
                );
            }
            if ($changes) {
                if (!$reassigned) {
                    create_notification(
                        $data['assigned_to'],
                        $taskId,
                        'Task Updated',
                        "Your task {$task['task_code']} was updated by your supervisor (changed: " . implode(', ', $changes) . ').',
                        'task_updated'
                    );
                }
                log_activity(
                    current_user_id(),
                    'update_task',
                    "Edited {$task['task_code']}: " . implode(', ', $changes)
                        . ($oldStatus !== $newStatus ? "; status {$oldStatus} → {$newStatus}" : '') . '.'
                );
            }

            $pdo->commit();

            set_flash('success', "{$task['task_code']} was updated successfully.");
            redirect('supervisor/task_details.php?id=' . $taskId);
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[EODB-TaskFlow] edit_task failed: ' . $e->getMessage());
            $errors['general'] = 'The changes could not be saved. Please try again.';
        }
    }
}

$pageTitle  = 'Edit ' . $task['task_code'];
$activeMenu = 'tasks';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= e(url('supervisor/tasks.php')) ?>">Tasks</a></li>
                <li class="breadcrumb-item"><a href="<?= e(url('supervisor/task_details.php?id=' . $taskId)) ?>" class="task-code"><?= e($task['task_code']) ?></a></li>
                <li class="breadcrumb-item active" aria-current="page">Edit</li>
            </ol>
        </nav>
        <h1 class="page-title">Edit Task <?= e($task['task_code']) ?></h1>
        <p class="page-subtitle">Update the details or reassign the task. The employee is notified of the changes.</p>
    </div>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <i class="fa-solid fa-circle-exclamation me-1"></i>
        <?= e($errors['general'] ?? 'Please correct the highlighted fields.') ?>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-body p-4">
        <form method="post" action="<?= e(url('supervisor/edit_task.php')) ?>" novalidate data-once>
            <input type="hidden" name="id" value="<?= $taskId ?>">
            <?php require __DIR__ . '/../includes/task_form.php'; ?>

            <div class="form-actions">
                <a href="<?= e(url('supervisor/task_details.php?id=' . $taskId)) ?>" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i> Save Changes</button>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
