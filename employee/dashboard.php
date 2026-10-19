<?php
/**
 * EODB-TaskFlow - Employee dashboard
 *
 * Cards: My Total Tasks, Pending, In Progress, Completed, Overdue, Completion Rate.
 * Also: tasks to work on (overdue first, then nearest deadline), personal
 * productivity (Algorithm 4), and recent updates on the employee's tasks.
 * The completion rate is for monitoring only, not a disciplinary score.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/task_functions.php';
require_role('employee');

$pdo = db();
$me = current_user_id();
$statusExpr = effective_status_sql('t');

$statsStmt = $pdo->prepare(
    "SELECT COUNT(*) AS total,
            COALESCE(SUM({$statusExpr} = 'Pending'), 0)     AS pending,
            COALESCE(SUM({$statusExpr} = 'In Progress'), 0) AS in_progress,
            COALESCE(SUM({$statusExpr} = 'Completed'), 0)   AS completed,
            COALESCE(SUM({$statusExpr} = 'Overdue'), 0)     AS overdue,
            COALESCE(SUM(t.status = 'Completed' AND DATE(t.completed_at) <= t.deadline), 0) AS on_time,
            COALESCE(SUM(t.status = 'Completed' AND t.completed_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')), 0) AS completed_this_month
       FROM tasks t
      WHERE t.assigned_to = ?"
);
$statsStmt->execute([$me]);
$stats = $statsStmt->fetch();

$total     = (int) $stats['total'];
$completed = (int) $stats['completed'];
$onTime    = (int) $stats['on_time'];
$rate      = $total > 0 ? round($completed / $total * 100, 1) : 0.0;          // Algorithm 4
$onTimeRate = $completed > 0 ? round($onTime / $completed * 100, 1) : 0.0;

// Unfinished tasks: overdue first, then nearest deadline
$openStmt = $pdo->prepare(
    "SELECT t.id, t.task_code, t.title, t.priority, t.deadline, t.progress, {$statusExpr} AS status
       FROM tasks t
      WHERE t.assigned_to = ? AND t.status <> 'Completed'
      ORDER BY FIELD({$statusExpr}, 'Overdue', 'In Progress', 'Pending'), t.deadline,
               FIELD(t.priority, 'Urgent', 'High', 'Medium', 'Low')
      LIMIT 6"
);
$openStmt->execute([$me]);
$openTasks = $openStmt->fetchAll();

// Recent updates on my tasks (by me, my supervisor, or the system)
$recentStmt = $pdo->prepare(
    'SELECT tu.updated_at, tu.new_status, tu.new_progress, tu.remarks, tu.user_id,
            t.id AS task_id, t.task_code, u.full_name
       FROM task_updates tu
       JOIN tasks t ON t.id = tu.task_id
       LEFT JOIN users u ON u.id = tu.user_id
      WHERE t.assigned_to = ?
      ORDER BY tu.updated_at DESC, tu.id DESC
      LIMIT 6'
);
$recentStmt->execute([$me]);
$recent = $recentStmt->fetchAll();

$cards = [
    ['My Total Tasks', (string) $total,                  'fa-clipboard-list',       'stat-dark',      'All assigned tasks',    ''],
    ['Pending',        (string) $stats['pending'],       'fa-hourglass-start',      'stat-pending',   'Not yet started',       'Pending'],
    ['In Progress',    (string) $stats['in_progress'],   'fa-bars-progress',        'stat-progress',  'Being worked on',       'In Progress'],
    ['Completed',      (string) $completed,              'fa-circle-check',         'stat-completed', 'Finished tasks',        'Completed'],
    ['Overdue',        (string) $stats['overdue'],       'fa-triangle-exclamation', 'stat-overdue',   'Past deadline',         'Overdue'],
    ['Completion Rate', $rate . '%',                     'fa-chart-pie',            'stat-accent',    'Completed ÷ Total × 100', ''],
];

$pageTitle  = 'Employee Dashboard';
$activeMenu = 'dashboard';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">My Dashboard</h1>
        <p class="page-subtitle">Welcome, <?= e(current_user()['full_name']) ?>. Here is your task summary as of <?= e(date('F d, Y')) ?>.</p>
    </div>
    <a href="<?= e(url('employee/my_tasks.php?status=open')) ?>" class="btn btn-primary">
        <i class="fa-solid fa-list-check me-1"></i> My Unfinished Tasks
    </a>
</div>

<div class="row g-3 mb-4">
    <?php foreach ($cards as [$label, $value, $icon, $class, $note, $filter]):
        $link = url('employee/my_tasks.php' . ($filter !== '' ? '?status=' . urlencode($filter) : ''));
        ?>
        <div class="col-6 col-xl-4 col-xxl-2">
            <a href="<?= e($link) ?>" class="stat-card stat-card-link <?= e($class) ?>">
                <div class="stat-card-icon"><i class="fa-solid <?= e($icon) ?>"></i></div>
                <div class="min-w-0">
                    <div class="stat-card-label"><?= e($label) ?></div>
                    <div class="stat-card-value"><?= e($value) ?></div>
                    <div class="stat-card-note"><?= e($note) ?></div>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-3 mb-4">
    <div class="col-xl-8">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Tasks to Work On</span>
                <a href="<?= e(url('employee/my_tasks.php?status=open')) ?>" class="small">View all</a>
            </div>
            <?php if (!$openTasks): ?>
                <div class="empty-state">
                    <i class="fa-regular fa-circle-check d-block"></i>
                    You have no unfinished tasks.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                        <tr><th>Task</th><th>Priority</th><th>Deadline</th><th>Status</th><th class="col-w-progress">Progress</th><th></th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($openTasks as $task): ?>
                            <tr>
                                <td>
                                    <a href="<?= e(url('employee/task_details.php?id=' . (int) $task['id'])) ?>" class="task-code"><?= e($task['task_code']) ?></a>
                                    <div><?= e($task['title']) ?></div>
                                </td>
                                <td><?= priority_badge($task['priority']) ?></td>
                                <td class="text-nowrap">
                                    <?= e(format_date($task['deadline'])) ?>
                                    <div class="small <?= $task['status'] === 'Overdue' ? 'text-danger' : 'text-muted' ?>"><?= e(deadline_note($task['deadline'], $task['status'])) ?></div>
                                </td>
                                <td><?= task_status_badge($task['status']) ?></td>
                                <td><?= progress_bar((int) $task['progress'], $task['status']) ?></td>
                                <td class="text-end">
                                    <a href="<?= e(url('employee/update_task.php?id=' . (int) $task['id'])) ?>" class="btn btn-sm btn-primary">Update</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card h-100">
            <div class="card-header">My Productivity</div>
            <div class="card-body">
                <div class="status-row-label"><span>Completion Rate</span><strong><?= $rate ?>%</strong></div>
                <div class="progress mb-1" role="progressbar" aria-label="Completion rate" aria-valuenow="<?= $rate ?>" aria-valuemin="0" aria-valuemax="100">
                    <div class="progress-bar bar-completed" style="width: <?= $rate ?>%"></div>
                </div>
                <p class="small text-muted"><?= $completed ?> of <?= $total ?> task(s) completed.</p>

                <div class="status-row-label"><span>Completed on Time</span><strong><?= $onTimeRate ?>%</strong></div>
                <div class="progress mb-1" role="progressbar" aria-label="On-time completion" aria-valuenow="<?= $onTimeRate ?>" aria-valuemin="0" aria-valuemax="100">
                    <div class="progress-bar bar-progress" style="width: <?= $onTimeRate ?>%"></div>
                </div>
                <p class="small text-muted"><?= $onTime ?> of <?= $completed ?> completed task(s) finished by the deadline.</p>

                <dl class="detail-list mt-3">
                    <dt>Completed this month</dt><dd><?= (int) $stats['completed_this_month'] ?></dd>
                    <dt>Unfinished tasks</dt><dd><?= $total - $completed ?></dd>
                </dl>
                <p class="small text-muted mt-3 mb-0">
                    <i class="fa-solid fa-circle-info me-1"></i>
                    These figures are for monitoring and reporting only.
                </p>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Recent Updates on My Tasks</span>
        <a href="<?= e(url('employee/history.php')) ?>" class="small">Full history</a>
    </div>
    <?php if (!$recent): ?>
        <div class="empty-state">No updates yet.</div>
    <?php else: ?>
        <ul class="list-group list-group-flush">
            <?php foreach ($recent as $entry): ?>
                <li class="list-group-item">
                    <div class="d-flex flex-wrap justify-content-between gap-2">
                        <span class="small">
                            <a href="<?= e(url('employee/task_details.php?id=' . (int) $entry['task_id'])) ?>" class="task-code"><?= e($entry['task_code']) ?></a>
                            · <?= e((int) $entry['user_id'] === $me ? 'You' : ($entry['full_name'] ?? 'System')) ?>
                            · <?= task_status_badge($entry['new_status']) ?> <?= (int) $entry['new_progress'] ?>%
                        </span>
                        <span class="small text-muted"><?= e(format_datetime($entry['updated_at'])) ?></span>
                    </div>
                    <?php if ($entry['remarks']): ?>
                        <div class="small text-muted mt-1"><?= e($entry['remarks']) ?></div>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
