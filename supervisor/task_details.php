<?php
/**
 * EODB-TaskFlow - Task details (Supervisor)
 *
 * All task fields, a progress bar, and the full task history, with Edit and
 * Delete actions. Completed tasks are read-only.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/task_functions.php';
require_role('supervisor');

$taskId = get_int('id');
$task = $taskId > 0 ? get_task($taskId) : null;

if ($task === null) {
    set_flash('warning', 'The selected task was not found.');
    redirect('supervisor/tasks.php');
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
                <li class="breadcrumb-item"><a href="<?= e(url('supervisor/tasks.php')) ?>">Tasks</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?= e($task['task_code']) ?></li>
            </ol>
        </nav>
        <h1 class="page-title">Task Details</h1>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if (!$isCompleted): ?>
            <a href="<?= e(url('supervisor/edit_task.php?id=' . $taskId)) ?>" class="btn btn-primary">
                <i class="fa-solid fa-pen me-1"></i> Edit / Reassign
            </a>
            <form method="post" action="<?= e(url('supervisor/delete_task.php')) ?>" class="m-0"
                  data-confirm="<?= e("Delete {$task['task_code']} – {$task['title']}? Its history will also be removed. This cannot be undone.") ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $taskId ?>">
                <button type="submit" class="btn btn-outline-danger"><i class="fa-solid fa-trash me-1"></i> Delete</button>
            </form>
        <?php else: ?>
            <span class="badge badge-status-completed align-self-center p-2">
                <i class="fa-solid fa-lock me-1"></i> Completed – accomplishment record locked
            </span>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../includes/task_details_view.php'; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
