<?php
/**
 * EODB-TaskFlow - Employee Productivity Report (Administrator and Supervisor)
 *
 * Columns: Employee, Total Tasks, Completed, Pending, In Progress, Overdue,
 *          Completion Rate (Algorithm 4: Completed / Total x 100), On-time Rate
 * Filters: search, employee, department, active/inactive, period (tasks
 * created), sort order. For monitoring and reporting only.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/report_functions.php';
require_role('admin', 'supervisor');

$filters = read_report_filters('productivity');
$rows = fetch_productivity_report($filters);
$totals = productivity_totals($rows);

$completedChart = [
    'kind' => 'bar',
    'stacked' => true,
    'horizontal' => true,
    'labels' => array_column($rows, 'full_name'),
    'datasets' => [
        ['label' => 'Completed',   'data' => array_column($rows, 'completed'),   'colors' => STATUS_COLORS['Completed']],
        ['label' => 'In Progress', 'data' => array_column($rows, 'in_progress'), 'colors' => STATUS_COLORS['In Progress']],
        ['label' => 'Pending',     'data' => array_column($rows, 'pending'),     'colors' => STATUS_COLORS['Pending']],
        ['label' => 'Overdue',     'data' => array_column($rows, 'overdue'),     'colors' => STATUS_COLORS['Overdue']],
    ],
];
$rateChart = [
    'kind' => 'bar',
    'horizontal' => true,
    'max' => 100,
    'percentSuffix' => true,
    'labels' => array_column($rows, 'full_name'),
    'datasets' => [['label' => 'Completion rate', 'data' => array_column($rows, 'completion_rate'), 'colors' => STATUS_COLORS['Completed']]],
];

$pageTitle   = 'Employee Productivity Report';
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
                <li class="breadcrumb-item active" aria-current="page">Employee Productivity Report</li>
            </ol>
        </nav>
        <h1 class="page-title">Employee Productivity Report</h1>
        <p class="page-subtitle">Completion Rate = Completed ÷ Total × 100. For monitoring and reporting only, not a disciplinary score.</p>
    </div>
</div>

<?php require __DIR__ . '/../includes/report_filter_bar.php'; ?>

<div class="row g-3 mb-3">
    <?php foreach ([
        ['Employees', (string) count($rows), 'stat-dark'],
        ['Total Tasks', (string) $totals['total'], 'stat-dark'],
        ['Completed', (string) $totals['completed'], 'stat-completed'],
        ['Overdue', (string) $totals['overdue'], 'stat-overdue'],
        ['Overall Completion Rate', $totals['completion_rate'] . '%', 'stat-accent'],
    ] as [$label, $value, $class]): ?>
        <div class="col-6 col-lg">
            <div class="stat-card <?= e($class) ?> py-3">
                <div><div class="stat-card-label"><?= e($label) ?></div><div class="stat-card-value fs-4"><?= e($value) ?></div></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header">Tasks per Employee by Status</div>
            <div class="card-body"><div class="chart-box chart-box-tall"><canvas data-chart="chartCompleted" role="img" aria-label="Tasks per employee by status"></canvas></div></div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header">Completion Rate per Employee</div>
            <div class="card-body"><div class="chart-box chart-box-tall"><canvas data-chart="chartRate" role="img" aria-label="Completion rate per employee"></canvas></div></div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between flex-wrap gap-2">
        <span>Productivity per Employee</span>
        <span class="small text-muted fw-normal">
            <?php foreach (report_filter_summary($filters) as $label => $value): ?>
                <span class="me-2"><?= e($label) ?>: <?= e($value) ?></span>
            <?php endforeach; ?>
        </span>
    </div>
    <?php if (!$rows): ?>
        <div class="empty-state"><i class="fa-solid fa-users-slash d-block"></i>No employees match the selected filters.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                <tr>
                    <th>Employee</th><th class="text-center">Total Tasks</th><th class="text-center">Completed</th>
                    <th class="text-center">Pending</th><th class="text-center">In Progress</th><th class="text-center">Overdue</th>
                    <th class="col-w-rate">Completion Rate</th><th class="text-center">On-time Rate</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td>
                            <span class="fw-semibold"><?= e($row['full_name']) ?></span>
                            <?php if ($row['account_status'] === 'inactive'): ?><span class="badge badge-account-inactive">Inactive</span><?php endif; ?>
                            <div class="small text-muted"><?= e($row['employee_id']) ?> · <?= e($row['department'] ?? '—') ?></div>
                        </td>
                        <td class="text-center"><?= $row['total'] ?></td>
                        <td class="text-center"><?= $row['completed'] ?></td>
                        <td class="text-center"><?= $row['pending'] ?></td>
                        <td class="text-center"><?= $row['in_progress'] ?></td>
                        <td class="text-center <?= $row['overdue'] > 0 ? 'text-danger fw-semibold' : '' ?>"><?= $row['overdue'] ?></td>
                        <td>
                            <div class="small mb-1"><?= $row['completion_rate'] ?>%</div>
                            <div class="progress" role="progressbar" aria-label="Completion rate" aria-valuenow="<?= $row['completion_rate'] ?>" aria-valuemin="0" aria-valuemax="100">
                                <div class="progress-bar bar-completed" style="width: <?= $row['completion_rate'] ?>%"></div>
                            </div>
                        </td>
                        <td class="text-center small"><?= $row['completed'] > 0 ? $row['on_time_rate'] . '%' : '—' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                <tr class="fw-semibold table-light">
                    <td>Total</td>
                    <td class="text-center"><?= $totals['total'] ?></td>
                    <td class="text-center"><?= $totals['completed'] ?></td>
                    <td class="text-center"><?= $totals['pending'] ?></td>
                    <td class="text-center"><?= $totals['in_progress'] ?></td>
                    <td class="text-center"><?= $totals['overdue'] ?></td>
                    <td><?= $totals['completion_rate'] ?>%</td>
                    <td class="text-center"><?= $totals['completed'] > 0 ? $totals['on_time_rate'] . '%' : '—' ?></td>
                </tr>
                </tfoot>
            </table>
        </div>
    <?php endif; ?>
</div>

<script type="application/json" id="chartCompleted"><?= chart_json($completedChart) ?></script>
<script type="application/json" id="chartRate"><?= chart_json($rateChart) ?></script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
