<?php
/**
 * EODB-TaskFlow - Task monitoring list (Supervisor)
 *
 * | Task Code | Task | Employee | Priority | Deadline | Status | Progress | Action |
 * Actions: View, Edit, Delete (Edit/Delete hidden for completed tasks).
 * Search and filters: see includes/task_list.php.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/task_list.php';
require_role('supervisor');

$filters = read_task_filters(true);
['tasks' => $tasks, 'pagination' => $pagination] = fetch_tasks_page($filters, get_int('page'));

$listPath    = 'supervisor/tasks.php';
$detailsPath = 'supervisor/task_details.php';
$showMine    = true;
$canManage   = true;

$pageTitle  = 'Tasks';
$activeMenu = 'tasks';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Task Monitoring</h1>
        <p class="page-subtitle">Monitor progress, deadlines, and overdue tasks of all employees.</p>
    </div>
    <a href="<?= e(url('supervisor/create_task.php')) ?>" class="btn btn-primary">
        <i class="fa-solid fa-plus me-1"></i> Create Task
    </a>
</div>

<?php require __DIR__ . '/../includes/task_filter_bar.php'; ?>
<?php require __DIR__ . '/../includes/task_table.php'; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
