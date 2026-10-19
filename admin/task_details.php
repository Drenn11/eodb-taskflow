<?php
/**
 * EODB-TaskFlow - Task details (Administrator, read-only)
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/task_functions.php';
require_role('admin');

$taskId = get_int('id');
$task = $taskId > 0 ? get_task($taskId) : null;

if ($task === null) {
    set_flash('warning', 'The selected task was not found.');
    redirect('admin/tasks.php');
}

$history = get_task_history($taskId);

$pageTitle  = $task['task_code'];
$activeMenu = 'tasks';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= e(url('admin/tasks.php')) ?>">All Tasks</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?= e($task['task_code']) ?></li>
            </ol>
        </nav>
        <h1 class="page-title">Task Details</h1>
    </div>
    <a href="<?= e(url('admin/view_user.php?id=' . (int) $task['assigned_to'])) ?>" class="btn btn-outline-secondary">
        <i class="fa-solid fa-user me-1"></i> View Employee
    </a>
</div>

<?php require __DIR__ . '/../includes/task_details_view.php'; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
