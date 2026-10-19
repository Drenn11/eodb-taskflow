<?php
/**
 * EODB-TaskFlow - Task Report (Administrator and Supervisor)
 *
 * Columns: Task Code, Task, Employee, Priority, Status, Progress, Deadline,
 *          Date Created, Date Completed
 * Search and filters: task code/title/employee, employee, department, status,
 * priority, and a date range applied to the date created, deadline, or date
 * completed. Print / PDF and CSV use exactly the same filters.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/report_functions.php';
require_role('admin', 'supervisor');

$filters = read_report_filters('tasks');
$rows = fetch_task_report($filters);
$statusCounts = count_by_status($rows);
$total = count($rows);

$priorityCounts = array_fill_keys(TASK_PRIORITIES, 0);
foreach ($rows as $row) {
    $priorityCounts[$row['priority']]++;
}

$statusChart = [
    'kind' => 'doughnut',
    'labels' => TASK_STATUSES,
    'datasets' => [['label' => 'Tasks', 'data' => array_values($statusCounts), 'colors' => array_values(STATUS_COLORS)]],
];
$priorityChart = [
    'kind' => 'bar',
    'labels' => TASK_PRIORITIES,
    'datasets' => [['label' => 'Tasks', 'data' => array_values($priorityCounts), 'colors' => array_values(PRIORITY_COLORS)]],
];

$pageTitle   = 'Task Report';
$activeMenu  = 'reports';
$useCharts   = true;
$pageScripts = ['js/reports.js'];
require __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= e(url('reports/index.php')) ?>">Reports</a></li>
                <li class="breadcrumb-item active" aria-current="page">Task Report</li>
            </ol>
        </nav>
        <h1 class="page-title">Task Report</h1>
    </div>
</div>

<?php require __DIR__ . '/../includes/report_filter_bar.php'; ?>

<div class="row g-3 mb-3">
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">Summary</div>
            <div class="card-body">
                <dl class="detail-list report-summary mb-3">
                    <?php foreach (report_filter_summary($filters) as $label => $value): ?>
                        <dt><?= e($label) ?></dt><dd><?= e($value) ?></dd>
                    <?php endforeach; ?>
                </dl>
                <table class="table table-sm mb-0">
                    <tbody>
                    <?php foreach ($statusCounts as $status => $count): ?>
                        <tr><td><?= task_status_badge($status) ?></td><td class="text-end"><?= $count ?></td></tr>
                    <?php endforeach; ?>
                    <tr class="fw-semibold"><td>Total tasks</td><td class="text-end"><?= $total ?></td></tr>
                    <tr><td>Completion rate</td><td class="text-end"><?= completion_rate($statusCounts['Completed'], $total) ?>%</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-4">
        <div class="card h-100">
            <div class="card-header">Tasks by Status</div>
            <div class="card-body"><div class="chart-box"><canvas data-chart="chartStatus" role="img" aria-label="Tasks by status"></canvas></div></div>
        </div>
    </div>
    <div class="col-md-6 col-lg-4">
        <div class="card h-100">
            <div class="card-header">Tasks by Priority</div>
            <div class="card-body"><div class="chart-box"><canvas data-chart="chartPriority" role="img" aria-label="Tasks by priority"></canvas></div></div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between flex-wrap gap-2">
        <span>Task Records</span>
        <span class="small text-muted fw-normal"><?= $total ?> record<?= $total === 1 ? '' : 's' ?><?= $total >= REPORT_MAX_ROWS ? ' (limit reached – narrow the filters)' : '' ?></span>
    </div>
    <?php if (!$rows): ?>
        <div class="empty-state"><i class="fa-solid fa-file-circle-xmark d-block"></i>No tasks match the selected filters.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                <tr>
                    <th>Task Code</th><th>Task</th><th>Employee</th><th>Priority</th><th>Status</th>
                    <th class="col-w-progress">Progress</th><th>Deadline</th><th>Date Created</th><th>Date Completed</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td class="text-nowrap"><a href="<?= e(report_task_link((int) $row['id'])) ?>" class="task-code"><?= e($row['task_code']) ?></a></td>
                        <td><?= e($row['title']) ?></td>
                        <td><?= e($row['employee_name']) ?><div class="small text-muted"><?= e($row['department'] ?? '') ?></div></td>
                        <td><?= priority_badge($row['priority']) ?></td>
                        <td><?= task_status_badge($row['status']) ?></td>
                        <td><?= progress_bar((int) $row['progress'], $row['status']) ?></td>
                        <td class="text-nowrap small"><?= e(format_date($row['deadline'])) ?></td>
                        <td class="text-nowrap small"><?= e(format_date($row['created_at'])) ?></td>
                        <td class="text-nowrap small"><?= e(format_date($row['completed_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script type="application/json" id="chartStatus"><?= chart_json($statusChart) ?></script>
<script type="application/json" id="chartPriority"><?= chart_json($priorityChart) ?></script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
