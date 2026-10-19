<?php
/**
 * EODB-TaskFlow - My Tasks (Employee)
 *
 * Lists only the tasks assigned to the logged-in employee.
 * Search: task code, title, description. Filters: status, priority, deadline range.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/task_functions.php';
require_role('employee');

$search   = get_str('q', 100);
$status   = get_str('status', 20);
$priority = get_str('priority', 10);
$dateFrom = get_str('from', 10);
$dateTo   = get_str('to', 10);

if (!in_array($status, TASK_STATUSES, true) && $status !== 'open') {
    $status = '';
}
if (!in_array($priority, TASK_PRIORITIES, true)) {
    $priority = '';
}
if ($dateFrom !== '' && !is_valid_date($dateFrom)) {
    $dateFrom = '';
}
if ($dateTo !== '' && !is_valid_date($dateTo)) {
    $dateTo = '';
}

$statusExpr = effective_status_sql('t');
$where  = ['t.assigned_to = ?'];
$params = [current_user_id()];

if ($search !== '') {
    $where[] = '(t.task_code LIKE ? OR t.title LIKE ? OR t.description LIKE ?)';
    $term = like_term($search);
    array_push($params, $term, $term, $term);
}
if ($status === 'open') {
    $where[] = "{$statusExpr} <> 'Completed'";
} elseif ($status !== '') {
    $where[]  = "{$statusExpr} = ?";
    $params[] = $status;
}
if ($priority !== '') {
    $where[]  = 't.priority = ?';
    $params[] = $priority;
}
if ($dateFrom !== '') {
    $where[]  = 't.deadline >= ?';
    $params[] = $dateFrom;
}
if ($dateTo !== '') {
    $where[]  = 't.deadline <= ?';
    $params[] = $dateTo;
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

$countStmt = db()->prepare("SELECT COUNT(*) FROM tasks t {$whereSql}");
$countStmt->execute($params);
$pagination = paginate((int) $countStmt->fetchColumn(), records_per_page(), get_int('page'));

$listStmt = db()->prepare(
    "SELECT t.id, t.task_code, t.title, t.priority, t.deadline, t.progress, t.completed_at,
            {$statusExpr} AS status, s.full_name AS supervisor_name
       FROM tasks t
       JOIN users s ON s.id = t.assigned_by
       {$whereSql}
      ORDER BY FIELD({$statusExpr}, 'Overdue', 'In Progress', 'Pending', 'Completed'),
               t.deadline, FIELD(t.priority, 'Urgent', 'High', 'Medium', 'Low'), t.id DESC
      LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}"
);
$listStmt->execute($params);
$tasks = $listStmt->fetchAll();

$filterQuery = array_filter(['q' => $search, 'status' => $status, 'priority' => $priority, 'from' => $dateFrom, 'to' => $dateTo], fn ($v) => $v !== '');

$pageTitle  = 'My Tasks';
$activeMenu = 'tasks';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">My Tasks</h1>
        <p class="page-subtitle">Tasks assigned to you. Open a task to update its progress or mark it as completed.</p>
    </div>
</div>

<form method="get" class="filter-bar" role="search">
    <div class="row g-2 align-items-end">
        <div class="col-12 col-lg-4">
            <label for="q" class="form-label">Search</label>
            <input type="search" class="form-control" id="q" name="q" value="<?= e($search) ?>" placeholder="Task code, title, or description" maxlength="100">
        </div>
        <div class="col-6 col-md-3 col-lg-2">
            <label for="status" class="form-label">Status</label>
            <select class="form-select" id="status" name="status">
                <option value="">All statuses</option>
                <option value="open" <?= $status === 'open' ? 'selected' : '' ?>>All unfinished</option>
                <?php foreach (TASK_STATUSES as $option): ?>
                    <option value="<?= e($option) ?>" <?= $status === $option ? 'selected' : '' ?>><?= e($option) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-3 col-lg-2">
            <label for="priority" class="form-label">Priority</label>
            <select class="form-select" id="priority" name="priority">
                <option value="">All</option>
                <?php foreach (TASK_PRIORITIES as $option): ?>
                    <option value="<?= e($option) ?>" <?= $priority === $option ? 'selected' : '' ?>><?= e($option) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-3 col-lg-2">
            <label for="from" class="form-label">Deadline from</label>
            <input type="date" class="form-control" id="from" name="from" value="<?= e($dateFrom) ?>">
        </div>
        <div class="col-6 col-md-3 col-lg-2">
            <label for="to" class="form-label">Deadline to</label>
            <input type="date" class="form-control" id="to" name="to" value="<?= e($dateTo) ?>">
        </div>
        <div class="col-12 col-lg-2 d-flex gap-2">
            <button type="submit" class="btn btn-primary flex-fill"><i class="fa-solid fa-magnifying-glass me-1"></i> Filter</button>
            <?php if ($filterQuery): ?>
                <a href="<?= e(url('employee/my_tasks.php')) ?>" class="btn btn-outline-secondary" title="Clear filters" aria-label="Clear filters">
                    <i class="fa-solid fa-xmark"></i>
                </a>
            <?php endif; ?>
        </div>
    </div>
</form>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span>Assigned Tasks</span>
        <span class="small text-muted fw-normal"><?= e(pagination_summary($pagination, count($tasks))) ?></span>
    </div>

    <?php if (!$tasks): ?>
        <div class="empty-state">
            <i class="fa-solid fa-clipboard-list d-block"></i>
            <?= $filterQuery ? 'No tasks match your search or filters.' : 'You have no assigned tasks yet.' ?>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover table-mobile-cards mb-0">
                <thead>
                <tr>
                    <th>Task Code</th>
                    <th>Task</th>
                    <th>Priority</th>
                    <th>Deadline</th>
                    <th>Status</th>
                    <th class="col-w-progress">Progress</th>
                    <th>Assigned By</th>
                    <th class="text-end">Action</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($tasks as $row):
                    $note = deadline_note($row['deadline'], $row['status']);
                    ?>
                    <tr>
                        <td class="text-nowrap" data-label="Task Code"><span class="task-code"><?= e($row['task_code']) ?></span></td>
                        <td class="cell-title"><?= e($row['title']) ?></td>
                        <td data-label="Priority"><?= priority_badge($row['priority']) ?></td>
                        <td class="text-nowrap" data-label="Deadline">
                            <div><?= e(format_date($row['deadline'])) ?>
                            <?php if ($note !== ''): ?>
                                <div class="small <?= $row['status'] === 'Overdue' ? 'text-danger' : 'text-muted' ?>"><?= e($note) ?></div>
                            <?php endif; ?></div>
                        </td>
                        <td data-label="Status"><?= task_status_badge($row['status']) ?></td>
                        <td data-label="Progress"><?= progress_bar((int) $row['progress'], $row['status']) ?></td>
                        <td class="small" data-label="Assigned By"><?= e($row['supervisor_name']) ?></td>
                        <td class="table-actions text-end">
                            <a href="<?= e(url('employee/task_details.php?id=' . (int) $row['id'])) ?>"
                               class="btn btn-sm btn-outline-secondary" title="View" aria-label="View <?= e($row['task_code']) ?>">
                                <i class="fa-solid fa-eye"></i>
                            </a>
                            <?php if ($row['status'] !== 'Completed'): ?>
                                <a href="<?= e(url('employee/update_task.php?id=' . (int) $row['id'])) ?>"
                                   class="btn btn-sm btn-primary" title="Update" aria-label="Update <?= e($row['task_code']) ?>">
                                    <i class="fa-solid fa-pen-to-square"></i> Update
                                </a>
                            <?php endif; ?>
                        </td>
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
