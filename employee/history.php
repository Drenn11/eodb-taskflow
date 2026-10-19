<?php
/**
 * EODB-TaskFlow - Task history (Employee)
 *
 * Every recorded change (status, progress, remarks, reassignment, automatic
 * overdue) on the employee's own tasks, newest first.
 * Search: task code or title. Filter: date range.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/task_functions.php';
require_role('employee');

$search   = get_str('q', 100);
$dateFrom = get_str('from', 10);
$dateTo   = get_str('to', 10);

if ($dateFrom !== '' && !is_valid_date($dateFrom)) {
    $dateFrom = '';
}
if ($dateTo !== '' && !is_valid_date($dateTo)) {
    $dateTo = '';
}

$where  = ['t.assigned_to = ?'];
$params = [current_user_id()];

if ($search !== '') {
    $where[] = '(t.task_code LIKE ? OR t.title LIKE ?)';
    $term = like_term($search);
    array_push($params, $term, $term);
}
if ($dateFrom !== '') {
    $where[]  = 'tu.updated_at >= ?';
    $params[] = $dateFrom . ' 00:00:00';
}
if ($dateTo !== '') {
    $where[]  = 'tu.updated_at <= ?';
    $params[] = $dateTo . ' 23:59:59';
}

$whereSql = 'WHERE ' . implode(' AND ', $where);
$fromSql  = 'FROM task_updates tu JOIN tasks t ON t.id = tu.task_id LEFT JOIN users u ON u.id = tu.user_id';

$countStmt = db()->prepare("SELECT COUNT(*) {$fromSql} {$whereSql}");
$countStmt->execute($params);
$pagination = paginate((int) $countStmt->fetchColumn(), records_per_page(), get_int('page'));

$listStmt = db()->prepare(
    "SELECT tu.*, t.id AS task_id, t.task_code, t.title, u.full_name, u.role
       {$fromSql}
       {$whereSql}
      ORDER BY tu.updated_at DESC, tu.id DESC
      LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}"
);
$listStmt->execute($params);
$entries = $listStmt->fetchAll();

$filterQuery = array_filter(['q' => $search, 'from' => $dateFrom, 'to' => $dateTo], fn ($v) => $v !== '');

$pageTitle  = 'Task History';
$activeMenu = 'history';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Task History</h1>
        <p class="page-subtitle">All recorded updates on your tasks: by you, your supervisor, and the system.</p>
    </div>
</div>

<form method="get" class="filter-bar" role="search">
    <div class="row g-2 align-items-end">
        <div class="col-12 col-md-5">
            <label for="q" class="form-label">Search</label>
            <input type="search" class="form-control" id="q" name="q" value="<?= e($search) ?>" placeholder="Task code or title" maxlength="100">
        </div>
        <div class="col-6 col-md-2">
            <label for="from" class="form-label">From</label>
            <input type="date" class="form-control" id="from" name="from" value="<?= e($dateFrom) ?>">
        </div>
        <div class="col-6 col-md-2">
            <label for="to" class="form-label">To</label>
            <input type="date" class="form-control" id="to" name="to" value="<?= e($dateTo) ?>">
        </div>
        <div class="col-12 col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-primary flex-fill"><i class="fa-solid fa-magnifying-glass me-1"></i> Filter</button>
            <?php if ($filterQuery): ?>
                <a href="<?= e(url('employee/history.php')) ?>" class="btn btn-outline-secondary" title="Clear filters" aria-label="Clear filters">
                    <i class="fa-solid fa-xmark"></i>
                </a>
            <?php endif; ?>
        </div>
    </div>
</form>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span>Update History</span>
        <span class="small text-muted fw-normal"><?= e(pagination_summary($pagination, count($entries))) ?></span>
    </div>

    <?php if (!$entries): ?>
        <div class="empty-state">
            <i class="fa-solid fa-clock-rotate-left d-block"></i>
            No history found.
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                <tr>
                    <th>Date &amp; Time</th>
                    <th>Task</th>
                    <th>Updated By</th>
                    <th>Status</th>
                    <th>Progress</th>
                    <th>Remarks</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($entries as $entry): ?>
                    <tr>
                        <td class="text-nowrap small"><?= e(format_datetime($entry['updated_at'])) ?></td>
                        <td>
                            <a href="<?= e(url('employee/task_details.php?id=' . (int) $entry['task_id'])) ?>" class="task-code"><?= e($entry['task_code']) ?></a>
                            <div class="small"><?= e($entry['title']) ?></div>
                        </td>
                        <td class="small text-nowrap">
                            <?= e($entry['full_name'] ?? 'System') ?>
                            <?php if ((int) $entry['user_id'] === current_user_id()): ?><span class="text-muted">(you)</span><?php endif; ?>
                        </td>
                        <td class="text-nowrap">
                            <?php if ($entry['previous_status'] === null): ?>
                                <span class="small text-muted">Created:</span> <?= task_status_badge($entry['new_status']) ?>
                            <?php elseif ($entry['previous_status'] !== $entry['new_status']): ?>
                                <?= task_status_badge($entry['previous_status']) ?> → <?= task_status_badge($entry['new_status']) ?>
                            <?php else: ?>
                                <?= task_status_badge($entry['new_status']) ?>
                            <?php endif; ?>
                        </td>
                        <td class="text-nowrap small">
                            <?php if ($entry['previous_progress'] === null || (int) $entry['previous_progress'] === (int) $entry['new_progress']): ?>
                                <?= (int) $entry['new_progress'] ?>%
                            <?php else: ?>
                                <?= (int) $entry['previous_progress'] ?>% → <strong><?= (int) $entry['new_progress'] ?>%</strong>
                            <?php endif; ?>
                        </td>
                        <td class="small"><?= e($entry['remarks'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php if ($pagination['pages'] > 1): ?>
        <div class="card-footer bg-white d-flex justify-content-end">
            <?= render_pagination($pagination, $filterQuery) ?>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
