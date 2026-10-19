<?php
/**
 * EODB-TaskFlow - Employee performance details (Supervisor)
 *
 * One employee: profile, productivity figures (Algorithm 4), unfinished and
 * recently completed tasks, and the latest progress updates the employee made.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/task_functions.php';
require_once __DIR__ . '/../includes/productivity_functions.php';
require_role('supervisor');

$employeeId = get_int('id');
$employee = $employeeId > 0 ? fetch_user_by_id($employeeId) : null;

if ($employee === null || $employee['role'] !== 'employee') {
    set_flash('warning', 'The selected employee was not found.');
    redirect('supervisor/employees.php');
}

$stats = employee_productivity(['employee' => $employeeId])[0];
$statusExpr = effective_status_sql('t');

$tasksStmt = db()->prepare(
    "SELECT t.id, t.task_code, t.title, t.priority, t.deadline, t.progress, t.completed_at, t.updated_at,
            {$statusExpr} AS status
       FROM tasks t
      WHERE t.assigned_to = ?
      ORDER BY FIELD({$statusExpr}, 'Overdue', 'In Progress', 'Pending', 'Completed'),
               CASE WHEN t.status = 'Completed' THEN NULL ELSE t.deadline END,
               t.completed_at DESC
      LIMIT 12"
);
$tasksStmt->execute([$employeeId]);
$tasks = $tasksStmt->fetchAll();

$updatesStmt = db()->prepare(
    'SELECT tu.updated_at, tu.previous_status, tu.new_status, tu.previous_progress, tu.new_progress, tu.remarks,
            t.id AS task_id, t.task_code
       FROM task_updates tu
       JOIN tasks t ON t.id = tu.task_id
      WHERE tu.user_id = ?
      ORDER BY tu.updated_at DESC, tu.id DESC
      LIMIT 8'
);
$updatesStmt->execute([$employeeId]);
$updates = $updatesStmt->fetchAll();

$photo = profile_photo_url($employee['profile_photo']);

$pageTitle  = $employee['full_name'];
$activeMenu = 'employees';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= e(url('supervisor/employees.php')) ?>">Employee Performance</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?= e($employee['full_name']) ?></li>
            </ol>
        </nav>
        <h1 class="page-title">Employee Performance</h1>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?= e(url('supervisor/tasks.php?employee=' . $employeeId)) ?>" class="btn btn-outline-secondary">
            <i class="fa-solid fa-list-check me-1"></i> All Tasks of Employee
        </a>
        <?php if ($employee['status'] === 'active'): ?>
            <a href="<?= e(url('supervisor/create_task.php?employee=' . $employeeId)) ?>" class="btn btn-primary">
                <i class="fa-solid fa-plus me-1"></i> Assign Task
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-body p-4">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <?php if ($photo): ?>
                        <img src="<?= e($photo) ?>" alt="" class="avatar avatar-lg">
                    <?php else: ?>
                        <span class="avatar avatar-lg"><?= e(initials($employee['full_name'])) ?></span>
                    <?php endif; ?>
                    <div class="min-w-0">
                        <h2 class="h5 mb-1"><?= e($employee['full_name']) ?></h2>
                        <div class="small text-muted"><?= e($employee['position'] ?: '—') ?></div>
                        <?= account_status_badge($employee['status']) ?>
                    </div>
                </div>
                <dl class="detail-list">
                    <dt>Employee ID</dt><dd><?= e($employee['employee_id']) ?></dd>
                    <dt>Department</dt><dd><?= e($employee['department'] ?? '—') ?></dd>
                    <dt>Email</dt><dd><?= e($employee['email']) ?></dd>
                    <dt>Last update</dt><dd><?= e(format_datetime($stats['last_update'])) ?></dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header">Productivity Summary</div>
            <div class="card-body">
                <div class="row g-2 text-center mb-3">
                    <?php foreach ([
                        ['Total', $stats['total'], ''],
                        ['Completed', $stats['completed'], 'Completed'],
                        ['In Progress', $stats['in_progress'], 'In Progress'],
                        ['Pending', $stats['pending'], 'Pending'],
                        ['Overdue', $stats['overdue'], 'Overdue'],
                    ] as [$label, $value, $filter]): ?>
                        <div class="col">
                            <a class="d-block border rounded py-2 text-decoration-none text-reset"
                               href="<?= e(url('supervisor/tasks.php?' . http_build_query(array_filter(['employee' => $employeeId, 'status' => $filter])))) ?>">
                                <div class="fs-4 fw-bold <?= $label === 'Overdue' && $value > 0 ? 'text-danger' : '' ?>"><?= $value ?></div>
                                <div class="small text-muted"><?= e($label) ?></div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="row g-4">
                    <div class="col-md-4">
                        <div class="status-row-label"><span>Completion Rate</span><strong><?= $stats['completion_rate'] ?>%</strong></div>
                        <div class="progress" role="progressbar" aria-label="Completion rate" aria-valuenow="<?= $stats['completion_rate'] ?>" aria-valuemin="0" aria-valuemax="100">
                            <div class="progress-bar bar-completed" style="width: <?= $stats['completion_rate'] ?>%"></div>
                        </div>
                        <div class="small text-muted mt-1"><?= $stats['completed'] ?> of <?= $stats['total'] ?> completed</div>
                    </div>
                    <div class="col-md-4">
                        <div class="status-row-label"><span>Completed on Time</span><strong><?= $stats['completed'] > 0 ? $stats['on_time_rate'] . '%' : '—' ?></strong></div>
                        <div class="progress" role="progressbar" aria-label="On-time rate" aria-valuenow="<?= $stats['on_time_rate'] ?>" aria-valuemin="0" aria-valuemax="100">
                            <div class="progress-bar bar-progress" style="width: <?= $stats['on_time_rate'] ?>%"></div>
                        </div>
                        <div class="small text-muted mt-1"><?= $stats['on_time'] ?> of <?= $stats['completed'] ?> by the deadline</div>
                    </div>
                    <div class="col-md-4">
                        <div class="status-row-label"><span>Avg. Progress (unfinished)</span><strong><?= $stats['avg_open_progress'] !== null ? $stats['avg_open_progress'] . '%' : '—' ?></strong></div>
                        <div class="progress" role="progressbar" aria-label="Average progress" aria-valuenow="<?= (float) $stats['avg_open_progress'] ?>" aria-valuemin="0" aria-valuemax="100">
                            <div class="progress-bar bar-pending" style="width: <?= (float) $stats['avg_open_progress'] ?>%"></div>
                        </div>
                        <div class="small text-muted mt-1">Across <?= $stats['total'] - $stats['completed'] ?> unfinished task(s)</div>
                    </div>
                </div>
                <p class="small text-muted mt-3 mb-0">
                    <i class="fa-solid fa-circle-info me-1"></i>
                    For monitoring and reporting only, not an automatic disciplinary score.
                </p>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-7">
        <div class="card h-100">
            <div class="card-header">Tasks</div>
            <?php if (!$tasks): ?>
                <div class="empty-state">No tasks assigned yet.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                        <tr><th>Task</th><th>Priority</th><th>Deadline</th><th>Status</th><th class="col-w-progress">Progress</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($tasks as $task): ?>
                            <tr>
                                <td>
                                    <a href="<?= e(url('supervisor/task_details.php?id=' . (int) $task['id'])) ?>" class="task-code"><?= e($task['task_code']) ?></a>
                                    <div><?= e($task['title']) ?></div>
                                </td>
                                <td><?= priority_badge($task['priority']) ?></td>
                                <td class="text-nowrap">
                                    <?= e(format_date($task['deadline'])) ?>
                                    <?php if ($task['status'] === 'Completed'): ?>
                                        <div class="small text-muted">Done <?= e(format_date($task['completed_at'])) ?></div>
                                    <?php else: ?>
                                        <div class="small <?= $task['status'] === 'Overdue' ? 'text-danger' : 'text-muted' ?>"><?= e(deadline_note($task['deadline'], $task['status'])) ?></div>
                                    <?php endif; ?>
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

    <div class="col-xl-5">
        <div class="card h-100">
            <div class="card-header">Latest Progress Updates by Employee</div>
            <?php if (!$updates): ?>
                <div class="empty-state">No updates recorded yet.</div>
            <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($updates as $update): ?>
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between gap-2">
                                <a href="<?= e(url('supervisor/task_details.php?id=' . (int) $update['task_id'])) ?>" class="task-code"><?= e($update['task_code']) ?></a>
                                <span class="small text-muted"><?= e(format_datetime($update['updated_at'])) ?></span>
                            </div>
                            <div class="small">
                                <?php if ($update['previous_progress'] !== null && (int) $update['previous_progress'] !== (int) $update['new_progress']): ?>
                                    <?= (int) $update['previous_progress'] ?>% → <strong><?= (int) $update['new_progress'] ?>%</strong>
                                <?php else: ?>
                                    <?= (int) $update['new_progress'] ?>%
                                <?php endif; ?>
                                · <?= task_status_badge($update['new_status']) ?>
                            </div>
                            <?php if ($update['remarks']): ?>
                                <div class="small text-muted mt-1"><?= e($update['remarks']) ?></div>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
