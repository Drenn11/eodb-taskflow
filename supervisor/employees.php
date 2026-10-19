<?php
/**
 * EODB-TaskFlow - Employee performance (Supervisor)
 *
 * Per employee: total, completed, in progress, pending, overdue, completion
 * rate (Algorithm 4), on-time rate, and average progress of unfinished tasks.
 * Filters: search, department, account status, task period, sort order.
 * For monitoring and reporting only, not a disciplinary score.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/task_functions.php';
require_once __DIR__ . '/../includes/user_functions.php';
require_once __DIR__ . '/../includes/productivity_functions.php';
require_role('supervisor');

$filters = [
    'q'          => get_str('q', 100),
    'department' => get_int('department'),
    'status'     => get_str('status', 10) ?: 'active',
    'from'       => get_str('from', 10),
    'to'         => get_str('to', 10),
    'sort'       => get_str('sort', 20) ?: 'name',
];
if (!in_array($filters['status'], ['active', 'inactive', 'all'], true)) {
    $filters['status'] = 'active';
}
foreach (['from', 'to'] as $key) {
    if ($filters[$key] !== '' && !is_valid_date($filters[$key])) {
        $filters[$key] = '';
    }
}
if (!isset(PRODUCTIVITY_SORTS[$filters['sort']])) {
    $filters['sort'] = 'name';
}

$rows = employee_productivity(['status' => $filters['status'] === 'all' ? '' : $filters['status']] + $filters);
$totals = productivity_totals($rows);
$departments = get_departments();

$sortLabels = [
    'name'      => 'Name (A–Z)',
    'rate_desc' => 'Completion rate (highest)',
    'rate_asc'  => 'Completion rate (lowest)',
    'overdue'   => 'Most overdue tasks',
    'total'     => 'Most tasks',
];
$hasFilters = $filters['q'] !== '' || $filters['department'] > 0 || $filters['status'] !== 'active'
    || $filters['from'] !== '' || $filters['to'] !== '' || $filters['sort'] !== 'name';

$pageTitle  = 'Employee Performance';
$activeMenu = 'employees';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Employee Performance</h1>
        <p class="page-subtitle">Task productivity per employee. Completion Rate = Completed ÷ Total × 100. For monitoring and reporting only.</p>
    </div>
</div>

<form method="get" class="filter-bar" role="search">
    <div class="row g-2 align-items-end">
        <div class="col-12 col-lg-3">
            <label for="q" class="form-label">Search</label>
            <input type="search" class="form-control" id="q" name="q" value="<?= e($filters['q']) ?>" placeholder="Name, Employee ID, or position" maxlength="100">
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <label for="department" class="form-label">Department</label>
            <select class="form-select" id="department" name="department">
                <option value="">All departments</option>
                <?php foreach ($departments as $dept): ?>
                    <option value="<?= (int) $dept['id'] ?>" <?= $filters['department'] === (int) $dept['id'] ? 'selected' : '' ?>><?= e($dept['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <label for="status" class="form-label">Account</label>
            <select class="form-select" id="status" name="status">
                <option value="active" <?= $filters['status'] === 'active' ? 'selected' : '' ?>>Active employees</option>
                <option value="inactive" <?= $filters['status'] === 'inactive' ? 'selected' : '' ?>>Inactive employees</option>
                <option value="all" <?= $filters['status'] === 'all' ? 'selected' : '' ?>>All employees</option>
            </select>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <label for="from" class="form-label">Tasks created from</label>
            <input type="date" class="form-control" id="from" name="from" value="<?= e($filters['from']) ?>">
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <label for="to" class="form-label">Tasks created to</label>
            <input type="date" class="form-control" id="to" name="to" value="<?= e($filters['to']) ?>">
        </div>
        <div class="col-6 col-md-4 col-lg-3">
            <label for="sort" class="form-label">Sort by</label>
            <select class="form-select" id="sort" name="sort">
                <?php foreach ($sortLabels as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= $filters['sort'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-4 col-lg-2 d-flex gap-2">
            <button type="submit" class="btn btn-primary flex-fill"><i class="fa-solid fa-magnifying-glass me-1"></i> Filter</button>
            <?php if ($hasFilters): ?>
                <a href="<?= e(url('supervisor/employees.php')) ?>" class="btn btn-outline-secondary" title="Clear filters" aria-label="Clear filters">
                    <i class="fa-solid fa-xmark"></i>
                </a>
            <?php endif; ?>
        </div>
    </div>
</form>

<div class="row g-3 mb-3">
    <?php foreach ([
        ['Employees', count($rows), 'stat-dark'],
        ['Tasks', $totals['total'], 'stat-dark'],
        ['Completed', $totals['completed'], 'stat-completed'],
        ['Overdue', $totals['overdue'], 'stat-overdue'],
        ['Overall Completion Rate', $totals['completion_rate'] . '%', 'stat-accent'],
    ] as [$label, $value, $class]): ?>
        <div class="col-6 col-lg">
            <div class="stat-card <?= e($class) ?> py-3">
                <div>
                    <div class="stat-card-label"><?= e($label) ?></div>
                    <div class="stat-card-value fs-4"><?= e((string) $value) ?></div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="card-header">Productivity per Employee</div>
    <?php if (!$rows): ?>
        <div class="empty-state">
            <i class="fa-solid fa-users-slash d-block"></i>
            No employees match your filters.
        </div>
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
                    <th class="text-center">On Time</th>
                    <th class="text-center">Avg. Progress<br><span class="fw-normal text-lowercase">(unfinished)</span></th>
                    <th class="text-end">Action</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr class="<?= $row['account_status'] === 'inactive' ? 'text-muted' : '' ?>">
                        <td>
                            <a href="<?= e(url('supervisor/employee_details.php?id=' . (int) $row['id'])) ?>" class="fw-semibold"><?= e($row['full_name']) ?></a>
                            <?php if ($row['account_status'] === 'inactive'): ?><span class="badge badge-account-inactive">Inactive</span><?php endif; ?>
                            <div class="small text-muted"><?= e(trim(($row['position'] ?? '') . ' · ' . ($row['department'] ?? ''), ' ·')) ?></div>
                        </td>
                        <td class="text-center"><?= $row['total'] ?></td>
                        <td class="text-center"><?= $row['completed'] ?></td>
                        <td class="text-center"><?= $row['in_progress'] ?></td>
                        <td class="text-center"><?= $row['pending'] ?></td>
                        <td class="text-center <?= $row['overdue'] > 0 ? 'text-danger fw-semibold' : '' ?>"><?= $row['overdue'] ?></td>
                        <td>
                            <?php if ($row['total'] === 0): ?>
                                <span class="small text-muted">No tasks</span>
                            <?php else: ?>
                                <div class="small mb-1"><?= $row['completion_rate'] ?>%</div>
                                <div class="progress" role="progressbar" aria-label="Completion rate" aria-valuenow="<?= $row['completion_rate'] ?>" aria-valuemin="0" aria-valuemax="100">
                                    <div class="progress-bar bar-completed" style="width: <?= $row['completion_rate'] ?>%"></div>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="text-center small"><?= $row['completed'] > 0 ? $row['on_time_rate'] . '%' : '—' ?></td>
                        <td class="text-center small"><?= $row['avg_open_progress'] !== null ? $row['avg_open_progress'] . '%' : '—' ?></td>
                        <td class="table-actions text-end">
                            <a href="<?= e(url('supervisor/employee_details.php?id=' . (int) $row['id'])) ?>" class="btn btn-sm btn-outline-secondary" title="View performance" aria-label="View <?= e($row['full_name']) ?>">
                                <i class="fa-solid fa-eye"></i>
                            </a>
                            <?php if ($row['account_status'] === 'active'): ?>
                                <a href="<?= e(url('supervisor/create_task.php?employee=' . (int) $row['id'])) ?>" class="btn btn-sm btn-outline-secondary" title="Assign a task" aria-label="Assign a task to <?= e($row['full_name']) ?>">
                                    <i class="fa-solid fa-plus"></i>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
