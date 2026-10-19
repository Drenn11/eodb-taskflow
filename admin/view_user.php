<?php
/**
 * EODB-TaskFlow - View employee / user account (Administrator)
 *
 * Account details, task summary with completion rate (for monitoring only),
 * recent assigned tasks, and the user's recent activities.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/user_functions.php';
require_role('admin');

$userId = get_int('id');
$user = $userId > 0 ? fetch_user_by_id($userId) : null;

if ($user === null) {
    set_flash('warning', 'The selected user was not found.');
    redirect('admin/users.php');
}

$isSelf = (int) $user['id'] === current_user_id();
$statusExpr = effective_status_sql('t');

// Task summary for tasks assigned to this user (Algorithm 4: completion rate)
$summaryStmt = db()->prepare(
    "SELECT COUNT(*) AS total,
            COALESCE(SUM({$statusExpr} = 'Completed'), 0)   AS completed,
            COALESCE(SUM({$statusExpr} = 'Pending'), 0)     AS pending,
            COALESCE(SUM({$statusExpr} = 'In Progress'), 0) AS in_progress,
            COALESCE(SUM({$statusExpr} = 'Overdue'), 0)     AS overdue
       FROM tasks t
      WHERE t.assigned_to = ?"
);
$summaryStmt->execute([$user['id']]);
$summary = $summaryStmt->fetch();
$total = (int) $summary['total'];
$completionRate = $total > 0 ? round($summary['completed'] / $total * 100, 1) : 0.0;

// Tasks created by this user (supervisors)
$createdStmt = db()->prepare('SELECT COUNT(*) FROM tasks WHERE assigned_by = ?');
$createdStmt->execute([$user['id']]);
$tasksCreated = (int) $createdStmt->fetchColumn();

// Recent assigned tasks
$tasksStmt = db()->prepare(
    "SELECT t.id, t.task_code, t.title, t.priority, t.deadline, t.progress, {$statusExpr} AS status
       FROM tasks t
      WHERE t.assigned_to = ?
      ORDER BY t.created_at DESC
      LIMIT 8"
);
$tasksStmt->execute([$user['id']]);
$tasks = $tasksStmt->fetchAll();

// Recent activities by this user
$logStmt = db()->prepare(
    'SELECT action, description, created_at
       FROM activity_logs
      WHERE user_id = ?
      ORDER BY created_at DESC, id DESC
      LIMIT 8'
);
$logStmt->execute([$user['id']]);
$activities = $logStmt->fetchAll();

$photo = profile_photo_url($user['profile_photo']);

$pageTitle  = $user['full_name'];
$activeMenu = 'employees';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= e(url('admin/users.php')) ?>">Employees</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?= e($user['full_name']) ?></li>
            </ol>
        </nav>
        <h1 class="page-title">Employee Details</h1>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?= e(url('admin/edit_user.php?id=' . (int) $user['id'])) ?>" class="btn btn-primary">
            <i class="fa-solid fa-pen me-1"></i> Edit
        </a>
        <?php if (!$isSelf): ?>
            <form method="post" action="<?= e(url('admin/toggle_user_status.php')) ?>" class="m-0"
                  data-confirm="<?= e(($user['status'] === 'active' ? 'Deactivate ' : 'Activate ') . $user['full_name'] . '?') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
                <input type="hidden" name="redirect_to" value="view">
                <?php if ($user['status'] === 'active'): ?>
                    <input type="hidden" name="action" value="deactivate">
                    <button type="submit" class="btn btn-outline-danger"><i class="fa-solid fa-user-slash me-1"></i> Deactivate</button>
                <?php else: ?>
                    <input type="hidden" name="action" value="activate">
                    <button type="submit" class="btn btn-outline-success"><i class="fa-solid fa-user-check me-1"></i> Activate</button>
                <?php endif; ?>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-body p-4">
                <div class="d-flex align-items-center gap-3 mb-4">
                    <?php if ($photo): ?>
                        <img src="<?= e($photo) ?>" alt="" class="avatar avatar-lg">
                    <?php else: ?>
                        <span class="avatar avatar-lg"><?= e(initials($user['full_name'])) ?></span>
                    <?php endif; ?>
                    <div class="min-w-0">
                        <h2 class="h5 mb-1"><?= e($user['full_name']) ?></h2>
                        <div class="text-muted small mb-2"><?= e($user['position'] ?: 'No position set') ?></div>
                        <?= role_badge($user['role']) ?> <?= account_status_badge($user['status']) ?>
                    </div>
                </div>
                <dl class="detail-list">
                    <dt>Employee ID</dt><dd><?= e($user['employee_id']) ?></dd>
                    <dt>Department</dt><dd><?= e($user['department'] ?? '—') ?></dd>
                    <dt>Email</dt><dd><?= e($user['email']) ?></dd>
                    <dt>Username</dt><dd><?= e($user['username']) ?></dd>
                    <dt>Last login</dt><dd><?= e(format_datetime($user['last_login_at'])) ?></dd>
                    <dt>Account created</dt><dd><?= e(format_datetime($user['created_at'])) ?></dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header">Task Summary</div>
            <div class="card-body">
                <?php if ($user['role'] !== 'employee' && $total === 0): ?>
                    <p class="text-muted mb-2">
                        <?= e(role_label($user['role'])) ?> accounts are not normally assigned tasks.
                    </p>
                    <?php if ($user['role'] === 'supervisor'): ?>
                        <p class="mb-0">Tasks created and assigned by this supervisor: <strong><?= $tasksCreated ?></strong></p>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="row g-2 text-center mb-3">
                        <?php foreach ([
                            ['Total', $total],
                            ['Completed', (int) $summary['completed']],
                            ['In Progress', (int) $summary['in_progress']],
                            ['Pending', (int) $summary['pending']],
                            ['Overdue', (int) $summary['overdue']],
                        ] as [$label, $value]): ?>
                            <div class="col">
                                <div class="border rounded py-2">
                                    <div class="fs-4 fw-bold"><?= $value ?></div>
                                    <div class="small text-muted"><?= e($label) ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="status-row-label">
                        <span>Completion Rate</span>
                        <strong><?= $completionRate ?>%</strong>
                    </div>
                    <div class="progress" role="progressbar" aria-label="Completion rate"
                         aria-valuenow="<?= $completionRate ?>" aria-valuemin="0" aria-valuemax="100">
                        <div class="progress-bar bar-completed" style="width: <?= $completionRate ?>%"></div>
                    </div>
                    <p class="small text-muted mt-2 mb-0">
                        Completed tasks &divide; total tasks &times; 100. For monitoring and reporting only.
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-7">
        <div class="card h-100">
            <div class="card-header">Recently Assigned Tasks</div>
            <?php if (!$tasks): ?>
                <div class="empty-state">No tasks assigned.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                        <tr><th>Task</th><th>Priority</th><th>Deadline</th><th>Status</th><th>Progress</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($tasks as $task): ?>
                            <tr>
                                <td>
                                    <a href="<?= e(url('admin/task_details.php?id=' . (int) $task['id'])) ?>" class="task-code"><?= e($task['task_code']) ?></a>
                                    <div><?= e($task['title']) ?></div>
                                </td>
                                <td><?= priority_badge($task['priority']) ?></td>
                                <td class="text-nowrap"><?= e(format_date($task['deadline'])) ?></td>
                                <td><?= task_status_badge($task['status']) ?></td>
                                <td class="col-w-progress">
                                    <div class="small text-muted"><?= (int) $task['progress'] ?>%</div>
                                    <div class="progress" role="progressbar" aria-label="Progress"
                                         aria-valuenow="<?= (int) $task['progress'] ?>" aria-valuemin="0" aria-valuemax="100">
                                        <div class="progress-bar bar-progress" style="width: <?= (int) $task['progress'] ?>%"></div>
                                    </div>
                                </td>
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
            <div class="card-header">Recent Activities</div>
            <?php if (!$activities): ?>
                <div class="empty-state">No activities recorded.</div>
            <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($activities as $log): ?>
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between gap-2">
                                <span class="badge text-bg-light border"><?= e(action_label($log['action'])) ?></span>
                                <span class="small text-muted text-nowrap"><?= e(format_datetime($log['created_at'])) ?></span>
                            </div>
                            <div class="small mt-1"><?= e($log['description']) ?></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
