<?php
/**
 * EODB-TaskFlow - Accomplishment Report (Administrator and Supervisor)
 *
 * Completed tasks within a selected date range (default: this month to date),
 * with the date completed and whether each task was finished on time.
 * Filters: search, employee, department, priority, completion date range.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/report_functions.php';
require_role('admin', 'supervisor');

$filters = read_report_filters('accomplishment');
$rows = fetch_accomplishment_report($filters);
$total = count($rows);

$onTime = 0;
$perEmployee = [];
foreach ($rows as $row) {
    if ((int) $row['days_after_deadline'] <= 0) {
        $onTime++;
    }
    $perEmployee[$row['employee_name']] = ($perEmployee[$row['employee_name']] ?? 0) + 1;
}
arsort($perEmployee);

$chart = [
    'kind' => 'bar',
    'horizontal' => true,
    'labels' => array_keys($perEmployee),
    'datasets' => [['label' => 'Completed tasks', 'data' => array_values($perEmployee), 'colors' => STATUS_COLORS['Completed']]],
];

$pageTitle   = 'Accomplishment Report';
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
                <li class="breadcrumb-item active" aria-current="page">Accomplishment Report</li>
            </ol>
        </nav>
        <h1 class="page-title">Accomplishment Report</h1>
        <p class="page-subtitle">Tasks completed from <?= e(format_date($filters['from'], 'F d, Y')) ?> to <?= e(format_date($filters['to'], 'F d, Y')) ?>.</p>
    </div>
</div>

<?php require __DIR__ . '/../includes/report_filter_bar.php'; ?>

<div class="row g-3 mb-3">
    <div class="col-lg-5">
        <div class="row g-3">
            <?php foreach ([
                ['Completed Tasks', (string) $total, 'stat-completed'],
                ['Completed on Time', $onTime . ($total > 0 ? ' (' . completion_rate($onTime, $total) . '%)' : ''), 'stat-accent'],
                ['Completed Late', (string) ($total - $onTime), 'stat-overdue'],
                ['Employees', (string) count($perEmployee), 'stat-dark'],
            ] as [$label, $value, $class]): ?>
                <div class="col-6">
                    <div class="stat-card <?= e($class) ?> py-3">
                        <div><div class="stat-card-label"><?= e($label) ?></div><div class="stat-card-value fs-4"><?= e($value) ?></div></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header">Completed Tasks per Employee</div>
            <div class="card-body">
                <?php if (!$perEmployee): ?>
                    <div class="empty-state py-4">No completed tasks in this period.</div>
                <?php else: ?>
                    <div class="chart-box"><canvas data-chart="chartAccomplishment" role="img" aria-label="Completed tasks per employee"></canvas></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between flex-wrap gap-2">
        <span>Completed Tasks</span>
        <span class="small text-muted fw-normal"><?= $total ?> record<?= $total === 1 ? '' : 's' ?></span>
    </div>
    <?php if (!$rows): ?>
        <div class="empty-state">
            <i class="fa-solid fa-calendar-xmark d-block"></i>
            No tasks were completed in the selected period. Try a wider date range.
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                <tr>
                    <th>Task Code</th><th>Task</th><th>Employee</th><th>Priority</th>
                    <th>Deadline</th><th>Date Completed</th><th>Timeliness</th><th>Remarks</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row):
                    $late = (int) $row['days_after_deadline'] > 0;
                    ?>
                    <tr>
                        <td class="text-nowrap"><a href="<?= e(report_task_link((int) $row['id'])) ?>" class="task-code"><?= e($row['task_code']) ?></a></td>
                        <td><?= e($row['title']) ?></td>
                        <td><?= e($row['employee_name']) ?><div class="small text-muted"><?= e($row['department'] ?? '') ?></div></td>
                        <td><?= priority_badge($row['priority']) ?></td>
                        <td class="text-nowrap small"><?= e(format_date($row['deadline'])) ?></td>
                        <td class="text-nowrap small"><?= e(format_datetime($row['completed_at'])) ?></td>
                        <td class="text-nowrap"><span class="badge <?= $late ? 'badge-priority-high' : 'badge-status-completed' ?>"><?= e(timeliness_label((int) $row['days_after_deadline'])) ?></span></td>
                        <td class="small"><?= e($row['remarks'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script type="application/json" id="chartAccomplishment"><?= chart_json($chart) ?></script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
