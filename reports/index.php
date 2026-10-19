<?php
/**
 * EODB-TaskFlow - Reports (Administrator and Supervisor)
 *
 * Links to the three reports and overview charts built from the database:
 *   - Task Status Chart (Pending, In Progress, Completed, Overdue)
 *   - Productivity Chart (completed vs unfinished tasks per employee)
 *   - Completed tasks per month (last 6 months)
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/report_functions.php';
require_role('admin', 'supervisor');

$statusExpr = effective_status_sql('t');

// Task Status Chart
$statusCounts = array_fill_keys(TASK_STATUSES, 0);
foreach (db()->query("SELECT {$statusExpr} AS status, COUNT(*) AS n FROM tasks t GROUP BY {$statusExpr}") as $row) {
    $statusCounts[$row['status']] = (int) $row['n'];
}
$totalTasks = array_sum($statusCounts);

// Productivity Chart: active employees
$productivity = employee_productivity(['status' => 'active']);
$totals = productivity_totals($productivity);

// Completed tasks per month, last 6 months (months with no completions show 0)
$months = [];
for ($i = 5; $i >= 0; $i--) {
    $months[date('Y-m', strtotime("first day of -{$i} month"))] = 0;
}
$trendStmt = db()->prepare(
    "SELECT DATE_FORMAT(completed_at, '%Y-%m') AS ym, COUNT(*) AS n
       FROM tasks
      WHERE status = 'Completed' AND completed_at >= ?
      GROUP BY ym"
);
$trendStmt->execute([array_key_first($months) . '-01 00:00:00']);
foreach ($trendStmt as $row) {
    if (isset($months[$row['ym']])) {
        $months[$row['ym']] = (int) $row['n'];
    }
}

$statusChart = [
    'kind' => 'doughnut',
    'labels' => TASK_STATUSES,
    'datasets' => [[
        'label' => 'Tasks',
        'data' => array_values($statusCounts),
        'colors' => array_values(STATUS_COLORS),
    ]],
];
$productivityChart = [
    'kind' => 'bar',
    'stacked' => true,
    'horizontal' => true,
    'labels' => array_map(fn ($r) => $r['full_name'], $productivity),
    'datasets' => [
        ['label' => 'Completed',   'data' => array_column($productivity, 'completed'),   'colors' => STATUS_COLORS['Completed']],
        ['label' => 'In Progress', 'data' => array_column($productivity, 'in_progress'), 'colors' => STATUS_COLORS['In Progress']],
        ['label' => 'Pending',     'data' => array_column($productivity, 'pending'),     'colors' => STATUS_COLORS['Pending']],
        ['label' => 'Overdue',     'data' => array_column($productivity, 'overdue'),     'colors' => STATUS_COLORS['Overdue']],
    ],
];
$trendChart = [
    'kind' => 'bar',
    'labels' => array_map(fn ($ym) => date('M Y', strtotime($ym . '-01')), array_keys($months)),
    'datasets' => [[
        'label' => 'Completed tasks',
        'data' => array_values($months),
        'colors' => STATUS_COLORS['Completed'],
    ]],
];

$reports = [
    'tasks'          => ['fa-list-check',     'All tasks with employee, priority, status, progress, deadline, and dates. Filter by status, priority, employee, department, and date range.'],
    'productivity'   => ['fa-chart-column',   'Per employee: total, completed, pending, in progress, overdue, and completion rate (Completed ÷ Total × 100).'],
    'accomplishment' => ['fa-award',          'Tasks completed within a selected period, with completion date and timeliness, for accomplishment documentation.'],
];

$pageTitle   = 'Reports';
$activeMenu  = 'reports';
$useCharts   = true;
$pageScripts = ['js/reports.js'];
require __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Reports</h1>
        <p class="page-subtitle">Generate, print, and export task monitoring and productivity reports.</p>
    </div>
</div>

<div class="row g-3 mb-4">
    <?php foreach ($reports as $type => [$icon, $description]): ?>
        <div class="col-md-4">
            <a href="<?= e(url(REPORT_TYPES[$type][1])) ?>" class="card h-100 report-card text-reset text-decoration-none">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3 mb-2">
                        <span class="stat-card-icon"><i class="fa-solid <?= e($icon) ?>"></i></span>
                        <h2 class="h6 mb-0"><?= e(REPORT_TYPES[$type][0]) ?></h2>
                    </div>
                    <p class="small text-muted mb-0"><?= e($description) ?></p>
                </div>
                <div class="card-footer bg-white small fw-semibold">Open report <i class="fa-solid fa-arrow-right ms-1"></i></div>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header">Task Status Chart</div>
            <div class="card-body">
                <div class="chart-box"><canvas data-chart="chartStatus" aria-label="Task status chart" role="img"></canvas></div>
                <table class="table table-sm mt-3 mb-0">
                    <tbody>
                    <?php foreach ($statusCounts as $status => $count): ?>
                        <tr>
                            <td><?= task_status_badge($status) ?></td>
                            <td class="text-end"><?= $count ?></td>
                            <td class="text-end text-muted small"><?= $totalTasks > 0 ? round($count / $totalTasks * 100, 1) : 0 ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="fw-semibold"><td>Total</td><td class="text-end"><?= $totalTasks ?></td><td></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header">
                Productivity Chart
                <span class="small text-muted fw-normal ms-1">Tasks per active employee by status · overall completion rate <?= $totals['completion_rate'] ?>%</span>
            </div>
            <div class="card-body">
                <div class="chart-box chart-box-tall"><canvas data-chart="chartProductivity" aria-label="Productivity chart: tasks per employee by status" role="img"></canvas></div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card">
            <div class="card-header">Completed Tasks per Month (Last 6 Months)</div>
            <div class="card-body">
                <div class="chart-box"><canvas data-chart="chartTrend" aria-label="Completed tasks per month" role="img"></canvas></div>
            </div>
        </div>
    </div>
</div>

<script type="application/json" id="chartStatus"><?= chart_json($statusChart) ?></script>
<script type="application/json" id="chartProductivity"><?= chart_json($productivityChart) ?></script>
<script type="application/json" id="chartTrend"><?= chart_json($trendChart) ?></script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
