<?php
/**
 * EODB-TaskFlow - Administrator dashboard
 *
 * Shows: total and active employees, task totals by status, a task status
 * overview, tasks due soon, active employees per department, and recent
 * activities. All figures come from the database.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$pdo = db();

// Employee counts (users with the Employee role)
$employeeStats = $pdo->query(
    "SELECT COUNT(*) AS total,
            COALESCE(SUM(status = 'active'), 0) AS active
       FROM users
      WHERE role = 'employee'"
)->fetch();

$otherUsers = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role <> 'employee'")->fetchColumn();

// Task counts by current status (Algorithm 3 rule applied in SQL)
$statusExpr = effective_status_sql('t');
$taskStats = $pdo->query(
    "SELECT COUNT(*) AS total,
            COALESCE(SUM({$statusExpr} = 'Pending'), 0)     AS pending,
            COALESCE(SUM({$statusExpr} = 'In Progress'), 0) AS in_progress,
            COALESCE(SUM({$statusExpr} = 'Completed'), 0)   AS completed,
            COALESCE(SUM({$statusExpr} = 'Overdue'), 0)     AS overdue
       FROM tasks t"
)->fetch();

$totalTasks     = (int) $taskStats['total'];
$completionRate = $totalTasks > 0 ? round($taskStats['completed'] / $totalTasks * 100, 1) : 0.0;

$statusRows = [
    ['Pending',     (int) $taskStats['pending'],     'bar-pending'],
    ['In Progress', (int) $taskStats['in_progress'], 'bar-progress'],
    ['Completed',   (int) $taskStats['completed'],   'bar-completed'],
    ['Overdue',     (int) $taskStats['overdue'],     'bar-overdue'],
];

// Unfinished tasks due within the next 3 days
$dueSoon = $pdo->query(
    "SELECT t.id, t.task_code, t.title, t.deadline, t.priority, u.full_name
       FROM tasks t
       JOIN users u ON u.id = t.assigned_to
      WHERE t.status <> 'Completed'
        AND t.deadline BETWEEN CURDATE() AND CURDATE() + INTERVAL 3 DAY
      ORDER BY t.deadline, FIELD(t.priority, 'Urgent', 'High', 'Medium', 'Low')
      LIMIT 6"
)->fetchAll();

// Active employees per department
$departments = $pdo->query(
    "SELECT d.name,
            COUNT(u.id) AS employees
       FROM departments d
       LEFT JOIN users u ON u.department_id = d.id AND u.role = 'employee' AND u.status = 'active'
      GROUP BY d.id, d.name
      ORDER BY employees DESC, d.name"
)->fetchAll();

// Recent activities
$activities = $pdo->query(
    'SELECT a.action, a.description, a.created_at, u.full_name, u.role
       FROM activity_logs a
       LEFT JOIN users u ON u.id = a.user_id
      ORDER BY a.created_at DESC, a.id DESC
      LIMIT 8'
)->fetchAll();

$cards = [
    ['Total Employees',  (int) $employeeStats['total'],  'fa-users',             'stat-dark',      "{$otherUsers} admin/supervisor account(s)"],
    ['Active Employees', (int) $employeeStats['active'], 'fa-user-check',        'stat-accent',    ((int) $employeeStats['total'] - (int) $employeeStats['active']) . ' inactive'],
    ['Total Tasks',      $totalTasks,                    'fa-list-check',        'stat-dark',      "{$completionRate}% completed overall"],
    ['Completed Tasks',  (int) $taskStats['completed'],  'fa-circle-check',      'stat-completed', 'Finished tasks'],
    ['Pending Tasks',    (int) $taskStats['pending'],    'fa-hourglass-start',   'stat-pending',   (int) $taskStats['in_progress'] . ' in progress'],
    ['Overdue Tasks',    (int) $taskStats['overdue'],    'fa-triangle-exclamation', 'stat-overdue', 'Past deadline, not completed'],
];

// ---- Presentation ----------------------------------------------------------
require_once __DIR__ . '/../includes/report_functions.php';   // chart colours, safe JSON, deadline_note()

$statusChart = [
    'kind' => 'doughnut',
    'hideLegend' => true,   // the coloured list beside the chart already names each status
    'labels' => array_column($statusRows, 0),
    'datasets' => [[
        'label' => 'Tasks',
        'data' => array_column($statusRows, 1),
        'colors' => array_values(STATUS_COLORS),
    ]],
];

$pageTitle   = 'Administrator Dashboard';
$activeMenu  = 'dashboard';
$useCharts   = true;
$pageScripts = ['js/reports.js'];
require __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Administrator Dashboard</h1>
        <p class="page-subtitle">
            Welcome, <?= e(current_user()['full_name']) ?>. System overview as of <?= e(date('F d, Y h:i A')) ?>.
        </p>
    </div>
    <a href="<?= e(url('admin/add_user.php')) ?>" class="btn btn-primary">
        <i class="fa-solid fa-user-plus me-1"></i> Add Employee
    </a>
</div>

<div class="row g-3 mb-4">
    <?php foreach ($cards as [$label, $value, $icon, $class, $note]): ?>
        <div class="col-6 col-xl-4 col-xxl-2">
            <div class="stat-card <?= e($class) ?>">
                <div class="stat-card-icon"><i class="fa-solid <?= e($icon) ?>" aria-hidden="true"></i></div>
                <div class="min-w-0">
                    <div class="stat-card-label"><?= e($label) ?></div>
                    <div class="stat-card-value"><?= number_format($value) ?></div>
                    <div class="stat-card-note"><?= e($note) ?></div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-3 mb-4">
    <div class="col-xl-5">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><span class="card-title-icon"><i class="fa-solid fa-chart-pie" aria-hidden="true"></i></span>Task Status Overview</span>
                <a href="<?= e(url('admin/tasks.php')) ?>" class="small">All tasks</a>
            </div>
            <div class="card-body">
                <?php if ($totalTasks === 0): ?>
                    <div class="empty-state">
                        <i class="fa-regular fa-folder-open" aria-hidden="true"></i>
                        <span class="empty-state-title">No tasks yet</span>
                        Tasks created by supervisors will be summarized here.
                    </div>
                <?php else: ?>
                    <div class="row g-3 align-items-center">
                        <div class="col-sm-5">
                            <div class="chart-box chart-box-sm"><canvas data-chart="chartStatus" role="img" aria-label="Task status chart"></canvas></div>
                        </div>
                        <div class="col-sm-7">
                            <?php foreach ($statusRows as [$label, $count, $barClass]):
                                $percent = $totalTasks > 0 ? round($count / $totalTasks * 100, 1) : 0;
                                ?>
                                <div class="status-row">
                                    <div class="status-row-label">
                                        <span><span class="status-dot <?= e($barClass) ?>" aria-hidden="true"></span><?= e($label) ?></span>
                                        <span class="text-muted"><strong class="text-body"><?= $count ?></strong> &middot; <?= $percent ?>%</span>
                                    </div>
                                    <div class="progress" role="progressbar" aria-label="<?= e($label) ?>"
                                         aria-valuenow="<?= $percent ?>" aria-valuemin="0" aria-valuemax="100">
                                        <div class="progress-bar <?= e($barClass) ?>" style="width: <?= $percent ?>%"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <div class="completion-summary">
                <span>Overall completion rate <span class="d-none d-sm-inline">(completed &divide; total &times; 100)</span></span>
                <strong><?= $completionRate ?>%</strong>
            </div>
        </div>
    </div>

    <div class="col-xl-7">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><span class="card-title-icon"><i class="fa-solid fa-calendar-day" aria-hidden="true"></i></span>Tasks Due in the Next 3 Days</span>
                <?php if ($dueSoon): ?><span class="badge text-bg-light border"><?= count($dueSoon) ?></span><?php endif; ?>
            </div>
            <?php if (!$dueSoon): ?>
                <div class="empty-state">
                    <i class="fa-regular fa-calendar-check" aria-hidden="true"></i>
                    <span class="empty-state-title">All caught up</span>
                    No unfinished tasks are due in the next 3 days.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                        <tr><th>Task</th><th>Employee</th><th>Priority</th><th>Deadline</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($dueSoon as $task): ?>
                            <tr>
                                <td>
                                    <a href="<?= e(url('admin/task_details.php?id=' . (int) $task['id'])) ?>" class="task-code"><?= e($task['task_code']) ?></a>
                                    <div><?= e($task['title']) ?></div>
                                </td>
                                <td><?= e($task['full_name']) ?></td>
                                <td><?= priority_badge($task['priority']) ?></td>
                                <td class="text-nowrap">
                                    <?= e(format_date($task['deadline'])) ?>
                                    <div class="small text-muted"><?= e(deadline_note($task['deadline'], 'Pending')) ?></div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-8">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><span class="card-title-icon"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i></span>Recent Activities</span>
                <?php if (page_exists('admin/activity_logs.php')): ?>
                    <a href="<?= e(url('admin/activity_logs.php')) ?>" class="small">View all</a>
                <?php endif; ?>
            </div>
            <?php if (!$activities): ?>
                <div class="empty-state">
                    <i class="fa-regular fa-rectangle-list" aria-hidden="true"></i>
                    <span class="empty-state-title">No activity yet</span>
                    Logins and task actions will appear here.
                </div>
            <?php else: ?>
                <ul class="list-group list-group-flush activity-list">
                    <?php foreach ($activities as $log): ?>
                        <li class="list-group-item">
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                                <span class="d-flex align-items-center gap-2 min-w-0">
                                    <span class="badge text-bg-light border"><?= e(action_label($log['action'])) ?></span>
                                    <span class="fw-semibold small text-truncate"><?= e($log['full_name'] ?? 'System') ?></span>
                                </span>
                                <span class="small text-muted text-nowrap"><?= e(format_datetime($log['created_at'])) ?></span>
                            </div>
                            <div class="small text-muted mt-1"><?= e($log['description']) ?></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card h-100">
            <div class="card-header">
                <span class="card-title-icon"><i class="fa-solid fa-building" aria-hidden="true"></i></span>Active Employees by Department
            </div>
            <ul class="list-group list-group-flush">
                <?php foreach ($departments as $dept): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center small">
                        <span><?= e($dept['name']) ?></span>
                        <span class="badge text-bg-light border"><?= (int) $dept['employees'] ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>

<script type="application/json" id="chartStatus"><?= chart_json($statusChart) ?></script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
