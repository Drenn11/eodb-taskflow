<?php
/**
 * EODB-TaskFlow - View all tasks (Administrator)
 *
 * Read-only task monitoring for the Administrator. Tasks are created, edited,
 * and deleted by supervisors only. Search and filters: see includes/task_list.php.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/task_list.php';
require_role('admin');

$filters = read_task_filters(false);
['tasks' => $tasks, 'pagination' => $pagination] = fetch_tasks_page($filters, get_int('page'));

$listPath    = 'admin/tasks.php';
$detailsPath = 'admin/task_details.php';
$showMine    = false;
$canManage   = false;

$pageTitle  = 'All Tasks';
$activeMenu = 'tasks';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">All Tasks</h1>
        <p class="page-subtitle">Read-only view of every task in the system. Tasks are managed by supervisors.</p>
    </div>
</div>

<?php require __DIR__ . '/../includes/task_filter_bar.php'; ?>
<?php require __DIR__ . '/../includes/task_table.php'; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
