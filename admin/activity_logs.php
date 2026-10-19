<?php
/**
 * EODB-TaskFlow - Activity logs (Administrator)
 *
 * Shows: User, Action, Description, Date, Time, IP address.
 * Search: description or user name. Filters: user, action, date range.
 * Records come from log_activity() (login, logout, create/update/delete/
 * assign/complete task, add/edit/deactivate user, security, system, ...).
 * The log is read-only: entries cannot be edited or deleted from the system.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/task_functions.php';
require_role('admin');

$search   = get_str('q', 100);
$userId   = get_int('user');
$action   = get_str('action', 50);
$dateFrom = get_str('from', 10);
$dateTo   = get_str('to', 10);

$actions = db()->query('SELECT DISTINCT action FROM activity_logs ORDER BY action')->fetchAll(PDO::FETCH_COLUMN);
$users = db()->query('SELECT id, full_name, role FROM users ORDER BY full_name')->fetchAll();

if (!in_array($action, $actions, true)) {
    $action = '';
}
if ($dateFrom !== '' && !is_valid_date($dateFrom)) {
    $dateFrom = '';
}
if ($dateTo !== '' && !is_valid_date($dateTo)) {
    $dateTo = '';
}

$where  = [];
$params = [];

if ($search !== '') {
    $where[] = '(a.description LIKE ? OR u.full_name LIKE ?)';
    $term = like_term($search);
    array_push($params, $term, $term);
}
if ($userId > 0) {
    $where[]  = 'a.user_id = ?';
    $params[] = $userId;
}
if ($action !== '') {
    $where[]  = 'a.action = ?';
    $params[] = $action;
}
if ($dateFrom !== '') {
    $where[]  = 'a.created_at >= ?';
    $params[] = $dateFrom . ' 00:00:00';
}
if ($dateTo !== '') {
    $where[]  = 'a.created_at <= ?';
    $params[] = $dateTo . ' 23:59:59';
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$fromSql  = 'FROM activity_logs a LEFT JOIN users u ON u.id = a.user_id';

$countStmt = db()->prepare("SELECT COUNT(*) {$fromSql} {$whereSql}");
$countStmt->execute($params);
$pagination = paginate((int) $countStmt->fetchColumn(), max(records_per_page(), 20), get_int('page'));

$listStmt = db()->prepare(
    "SELECT a.id, a.action, a.description, a.ip_address, a.created_at, u.full_name, u.role
       {$fromSql}
       {$whereSql}
      ORDER BY a.created_at DESC, a.id DESC
      LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}"
);
$listStmt->execute($params);
$logs = $listStmt->fetchAll();

$filterQuery = array_filter([
    'q'      => $search,
    'user'   => $userId ?: '',
    'action' => $action,
    'from'   => $dateFrom,
    'to'     => $dateTo,
], fn ($v) => $v !== '');

$actionClass = static fn (string $a): string => match (true) {
    in_array($a, ['login_failed', 'unauthorized_access', 'security', 'delete_task', 'deactivate_user'], true) => 'badge-status-overdue',
    in_array($a, ['complete_task', 'add_user', 'activate_user'], true) => 'badge-status-completed',
    in_array($a, ['create_task', 'assign_task', 'update_task', 'edit_user', 'update_settings'], true) => 'badge-status-progress',
    default => 'badge-status-pending',
};

$pageTitle  = 'Activity Logs';
$activeMenu = 'activity_logs';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Activity Logs</h1>
        <p class="page-subtitle">A read-only record of logins, task actions, user management, and security events.</p>
    </div>
</div>

<form method="get" class="filter-bar" role="search">
    <div class="row g-2 align-items-end">
        <div class="col-12 col-lg-3">
            <label for="q" class="form-label">Search</label>
            <input type="search" class="form-control" id="q" name="q" value="<?= e($search) ?>" placeholder="Description or user name" maxlength="100">
        </div>
        <div class="col-6 col-md-3 col-lg-2">
            <label for="user" class="form-label">User</label>
            <select class="form-select" id="user" name="user">
                <option value="">All users</option>
                <?php foreach ($users as $option): ?>
                    <option value="<?= (int) $option['id'] ?>" <?= $userId === (int) $option['id'] ? 'selected' : '' ?>><?= e($option['full_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-3 col-lg-2">
            <label for="action" class="form-label">Action</label>
            <select class="form-select" id="action" name="action">
                <option value="">All actions</option>
                <?php foreach ($actions as $option): ?>
                    <option value="<?= e($option) ?>" <?= $action === $option ? 'selected' : '' ?>><?= e(action_label($option)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-3 col-lg-2">
            <label for="from" class="form-label">From</label>
            <input type="date" class="form-control" id="from" name="from" value="<?= e($dateFrom) ?>">
        </div>
        <div class="col-6 col-md-3 col-lg-2">
            <label for="to" class="form-label">To</label>
            <input type="date" class="form-control" id="to" name="to" value="<?= e($dateTo) ?>">
        </div>
        <div class="col-12 col-lg-1 d-flex gap-2">
            <button type="submit" class="btn btn-primary flex-fill" title="Filter" aria-label="Filter"><i class="fa-solid fa-magnifying-glass"></i></button>
            <?php if ($filterQuery): ?>
                <a href="<?= e(url('admin/activity_logs.php')) ?>" class="btn btn-outline-secondary" title="Clear filters" aria-label="Clear filters">
                    <i class="fa-solid fa-xmark"></i>
                </a>
            <?php endif; ?>
        </div>
    </div>
</form>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span>Activity Records</span>
        <span class="small text-muted fw-normal"><?= e(pagination_summary($pagination, count($logs))) ?></span>
    </div>

    <?php if (!$logs): ?>
        <div class="empty-state">
            <i class="fa-solid fa-clock-rotate-left d-block"></i>
            No activity matches your filters.
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                <tr>
                    <th>User</th>
                    <th>Action</th>
                    <th>Description</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>IP Address</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td class="text-nowrap">
                            <?= e($log['full_name'] ?? 'System / Unknown') ?>
                            <?php if ($log['role']): ?><div class="small text-muted"><?= e(role_label($log['role'])) ?></div><?php endif; ?>
                        </td>
                        <td class="text-nowrap"><span class="badge <?= e($actionClass($log['action'])) ?>"><?= e(action_label($log['action'])) ?></span></td>
                        <td class="small"><?= e($log['description']) ?></td>
                        <td class="text-nowrap small"><?= e(format_date($log['created_at'])) ?></td>
                        <td class="text-nowrap small"><?= e(format_date($log['created_at'], 'h:i:s A')) ?></td>
                        <td class="text-nowrap small text-muted"><?= e($log['ip_address'] ?? '—') ?></td>
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
