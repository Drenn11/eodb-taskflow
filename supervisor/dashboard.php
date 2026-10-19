<?php
/**
 * EODB-TaskFlow - Supervisor dashboard
 *
 * Cards: tasks assigned by this supervisor (total, pending, in progress,
 * completed, overdue). Also: tasks needing attention, recently completed
 * tasks for review, and employee productivity (Algorithm 4).
 * Completion rates are for monitoring and reporting only.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/task_functions.php';
require_role('supervisor');

$pdo = db();
$me = current_user_id();
$statusExpr = effective_status_sql('t');

// Tasks assigned by this supervisor, by current status (Algorithm 3)
$statsStmt = $pdo->prepare(
    "SELECT COUNT(*) AS total,
            COALESCE(SUM({$statusExpr} = 'Pending'), 0)     AS pending,
            COALESCE(SUM({$statusExpr} = 'In Progress'), 0) AS in_progress,
            COALESCE(SUM({$statusExpr} = 'Completed'), 0)   AS completed,
            COALESCE(SUM({$statusExpr} = 'Overdue'), 0)     AS overdue
       FROM tasks t
      WHERE t.assigned_by = ?"
);
$statsStmt->execute([$me]);
$stats = $statsStmt->fetch();
$total = (int) $stats['total'];
$myRate = $total > 0 ? round($stats['completed'] / $total * 100, 1) : 0.0;

// Tasks needing attention: overdue or due within the reminder period
$reminderDays = max(1, (int) get_setting('deadline_reminder_days', DEADLINE_REMINDER_DAYS));
$attentionStmt = $pdo->prepare(
    "SELECT t.id, t.task_code, t.title, t.priority, t.deadline, t.progress,
            {$statusExpr} AS status, e.full_name AS employee_name
       FROM tasks t
       JOIN users e ON e.id = t.assigned_to
      WHERE t.status <> 'Completed'
        AND t.deadline <= CURDATE() + INTERVAL ? DAY
      ORDER BY t.deadline, FIELD(t.priority, 'Urgent', 'High', 'Medium', 'Low')
      LIMIT 8"
);
$attentionStmt->execute([$reminderDays]);
$attention = $attentionStmt->fetchAll();

// Recently completed tasks (review of accomplishments)
$completed = $pdo->query(
    "SELECT t.id, t.task_code, t.title, t.completed_at, t.deadline, e.full_name AS employee_name
       FROM tasks t
       JOIN users e ON e.id = t.assigned_to
      WHERE t.status = 'Completed'
      ORDER BY t.completed_at DESC
      LIMIT 5"
)->fetchAll();

// Employee productivity (Algorithm 4): Completed / Total x 100, active employees
$productivity = $pdo->query(
    "SELECT u.id, u.full_name, d.name AS department,
            COUNT(t.id) AS total,
            COALESCE(SUM({$statusExpr} = 'Completed'), 0)   AS completed,
            COALESCE(SUM({$statusExpr} = 'In Progress'), 0) AS in_progress,
            COALESCE(SUM({$statusExpr} = 'Pending'), 0)     AS pending,
            COALESCE(SUM({$statusExpr} = 'Overdue'), 0)     AS overdue
       FROM users u
       LEFT JOIN departments d ON d.id = u.department_id
       LEFT JOIN tasks t ON t.assigned_to = u.id
      WHERE u.role = 'employee' AND u.status = 'active'
      GROUP BY u.id, u.full_name, d.name
      ORDER BY u.full_name"
)->fetchAll();

$cards = [
    ['Assigned Tasks', $total,                      'fa-clipboard-list',        'stat-dark',      "{$myRate}% completed", ''],
    ['Pending',        (int) $stats['pending'],     'fa-hourglass-start',       'stat-pending',   'Not yet started',      'Pending'],
    ['In Progress',    (int) $stats['in_progress'], 'fa-bars-progress',         'stat-progress',  'Being worked on',      'In Progress'],
    ['Completed',      (int) $stats['completed'],   'fa-circle-check',          'stat-completed', 'Finished tasks',       'Completed'],
    ['Overdue',        (int) $stats['overdue'],     'fa-triangle-exclamation',  'stat-overdue',   'Past deadline',        'Overdue'],
];

// ---- Presentation ----------------------------------------------------------
require_once __DIR__ . '/../includes/report_functions.php';   // chart colours and safe JSON

$statusChart = [
    'kind' => 'doughnut',
    'labels' => TASK_STATUSES,
    'datasets' => [[
        'label' => 'Tasks',
        'data' => [(int) $stats['pending'], (int) $stats['in_progress'], (int) $stats['completed'], (int) $stats['overdue']],
        'colors' => array_values(STATUS_COLORS),
    ]],
];

$pageTitle   = 'Supervisor Dashboard';
$activeMenu  = 'dashboard';
$useCharts   = true;
$pageScripts = ['js/reports.js'];
require __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Supervisor Dashboard</h1>
        <p class="page-subtitle">Welcome, <?= e(current_user()['full_name']) ?>. Overview as of <?= e(date('F d, Y h:i A')) ?>.</p>
    </div>
    <a href="<?= e(url('supervisor/create_task.php')) ?>" class="btn btn-primary">
        <i class="fa-solid fa-plus me-1"></i> Create Task
    </a>
</div>

<p class="section-label">Tasks you assigned</p>
<div class="row g-3 mb-4">
    <?php foreach ($cards as [$label, $value, $icon, $class, $note, $filter]):
        $link = url('supervisor/tasks.php?' . http_build_query(array_filter(['mine' => '1', 'status' => $filter])));
        ?>
        <div class="col-6 col-lg-4 col-xl">
            <a href="<?= e($link) ?>" class="stat-card stat-card-link <?= e($class) ?>">
                <div class="stat-card-icon"><i class="fa-solid <?= e($icon) ?>" aria-hidden="true"></i></div>
                <div class="min-w-0">
                    <div class="stat-card-label"><?= e($label) ?></div>
                    <div class="stat-card-value"><?= number_format($value) ?></div>
                    <div class="stat-card-note"><?= e($note) ?></div>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-3 mb-4">
    <div class="col-xl-8">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center gap-2">
                <span>Needs Attention: Overdue and Due Within <?= $reminderDays ?> Day<?= $reminderDays === 1 ? '' : 's' ?></span>
                <a href="<?= e(url('supervisor/tasks.php?status=Overdue')) ?>" class="small text-nowrap">All overdue</a>
            </div>
            <?php if (!$attention): ?>
                <div class="empty-state">
                    <i class="fa-regular fa-calendar-check d-block"></i>
                    No overdue tasks or tasks due soon.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                        <tr><th>Task</th><th>Employee</th><th>Deadline</th><th>Status</th><th class="col-w-progress">Progress</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($attention as $task): ?>
                            <tr>
                                <td>
                                    <a href="<?= e(url('supervisor/task_details.php?id=' . (int) $task['id'])) ?>" class="task-code"><?= e($task['task_code']) ?></a>
                                    <div><?= e($task['title']) ?></div>
                                </td>
                                <td><?= e($task['employee_name']) ?></td>
                                <td class="text-nowrap">
                                    <?= e(format_date($task['deadline'])) ?>
                                    <div class="small <?= $task['status'] === 'Overdue' ? 'text-danger' : 'text-muted' ?>"><?= e(deadline_note($task['deadline'], $task['status'])) ?></div>
                                </td>
                                <td><?= task_status_badge($task['status']) ?></td>
                                <td><?= progress_bar((int) $task['progress'], $task['status']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-xl-4 d-flex flex-column gap-3">
        <div class="card">
            <div class="card-header">Status of Tasks You Assigned</div>
            <div class="card-body">
                <?php if ($total === 0): ?>
                    <div class="empty-state py-4">You have not assigned any tasks yet.</div>
                <?php else: ?>
                    <div class="chart-box chart-box-sm"><canvas data-chart="chartStatus" role="img" aria-label="Status of tasks you assigned"></canvas></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card flex-grow-1">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Recently Completed</span>
                <a href="<?= e(url('supervisor/tasks.php?status=Completed')) ?>" class="small">All completed</a>
            </div>
            <?php if (!$completed): ?>
                <div class="empty-state">No completed tasks yet.</div>
            <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($completed as $task):
                        $onTime = substr((string) $task['completed_at'], 0, 10) <= $task['deadline'];
                        ?>
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between gap-2">
                                <a href="<?= e(url('supervisor/task_details.php?id=' . (int) $task['id'])) ?>" class="task-code"><?= e($task['task_code']) ?></a>
                                <span class="badge <?= $onTime ? 'badge-status-completed' : 'badge-priority-high' ?>"><?= $onTime ? 'On time' : 'Late' ?></span>
                            </div>
                            <div class="small mt-1"><?= e($task['title']) ?></div>
                            <div class="small text-muted"><?= e($task['employee_name']) ?> · <?= e(format_datetime($task['completed_at'])) ?></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span>Employee Productivity</span>
        <span class="small text-muted fw-normal">Completion Rate = Completed ÷ Total × 100 (for monitoring only)</span>
    </div>
    <?php if (!$productivity): ?>
        <div class="empty-state">No active employees.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                <tr>
                    <th>Employee</th>
                    <th class="text-center">Total</th>
                    <th class="text-center">Completed</th>
                    <th class="text-center">In Progress</th>
                    <th class="text-center">Pending</th>
                    <th class="text-center">Overdue</th>
                    <th class="col-w-rate">Completion Rate</th>
                    <th class="text-end">Action</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($productivity as $row):
                    $rowTotal = (int) $row['total'];
                    $rate = $rowTotal > 0 ? round($row['completed'] / $rowTotal * 100, 1) : 0.0;
                    ?>
                    <tr>
                        <td>
                            <a href="<?= e(url('supervisor/employee_details.php?id=' . (int) $row['id'])) ?>" class="fw-semibold"><?= e($row['full_name']) ?></a>
                            <div class="small text-muted"><?= e($row['department'] ?? '') ?></div>
                        </td>
                        <td class="text-center"><?= $rowTotal ?></td>
                        <td class="text-center"><?= (int) $row['completed'] ?></td>
                        <td class="text-center"><?= (int) $row['in_progress'] ?></td>
                        <td class="text-center"><?= (int) $row['pending'] ?></td>
                        <td class="text-center <?= (int) $row['overdue'] > 0 ? 'text-danger fw-semibold' : '' ?>"><?= (int) $row['overdue'] ?></td>
                        <td>
                            <?php if ($rowTotal === 0): ?>
                                <span class="small text-muted">No tasks yet</span>
                            <?php else: ?>
                                <div class="small fw-semibold mb-1"><?= $rate ?>%</div>
                                <div class="progress" role="progressbar" aria-label="Completion rate" aria-valuenow="<?= $rate ?>" aria-valuemin="0" aria-valuemax="100">
                                    <div class="progress-bar bar-completed" style="width: <?= $rate ?>%"></div>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <a href="<?= e(url('supervisor/create_task.php?employee=' . (int) $row['id'])) ?>" class="btn btn-sm btn-outline-secondary" title="Assign a task">
                                <i class="fa-solid fa-plus"></i> Assign
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script type="application/json" id="chartStatus"><?= chart_json($statusChart) ?></script>

<?php require __DIR__ . '/../includes/footer.php'; ?>