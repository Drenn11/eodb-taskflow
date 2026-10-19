<?php
/**
 * EODB-TaskFlow - Task details (Employee)
 *
 * Shows one of the employee's own tasks: all fields, progress bar, and
 * history. Tasks assigned to other employees are not shown.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/task_functions.php';
require_role('employee');

$taskId = get_int('id');
$task = get_own_task($taskId);

if ($task === null) {
    set_flash('warning', 'The selected task was not found in your assigned tasks.');
    redirect('employee/my_tasks.php');
}

$history = get_task_history($taskId);
$isCompleted = $task['status'] === 'Completed';

$pageTitle  = $task['task_code'];
$activeMenu = 'tasks';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= e(url('employee/my_tasks.php')) ?>">My Tasks</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?= e($task['task_code']) ?></li>
            </ol>
        </nav>
        <h1 class="page-title">Task Details</h1>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if (!$isCompleted): ?>
            <a href="<?= e(url('employee/update_task.php?id=' . $taskId)) ?>" class="btn btn-primary">
                <i class="fa-solid fa-pen-to-square me-1"></i> Update Progress
            </a>
            <a href="<?= e(url('employee/update_task.php?id=' . $taskId . '&complete=1')) ?>" class="btn btn-outline-success">
                <i class="fa-solid fa-circle-check me-1"></i> Mark as Completed
            </a>
        <?php else: ?>
            <span class="badge badge-status-completed align-self-center p-2">
                <i class="fa-solid fa-check me-1"></i> Completed on <?= e(format_datetime($task['completed_at'])) ?>
            </span>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../includes/task_details_view.php'; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
