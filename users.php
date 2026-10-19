<?php
/**
 * EODB-TaskFlow - Employee / user management list (Administrator)
 *
 * Search: name, Employee ID, username, email, position
 * Filters: role, department, status
 * Actions: view, edit, deactivate/activate
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/user_functions.php';
require_role('admin');

$departments = get_departments();

// Filters (validated against allowed values)
$search       = get_str('q', 100);
$roleFilter   = get_str('role', 20);
$deptFilter   = get_int('department');
$statusFilter = get_str('status', 10);

if (!in_array($roleFilter, USER_ROLES, true)) {
    $roleFilter = '';
}
if (!in_array($statusFilter, ['active', 'inactive'], true)) {
    $statusFilter = '';
}

$where  = [];
$params = [];

if ($search !== '') {
    $where[] = '(u.full_name LIKE ? OR u.employee_id LIKE ? OR u.username LIKE ? OR u.email LIKE ? OR u.position LIKE ?)';
    $term = like_term($search);
    array_push($params, $term, $term, $term, $term, $term);
}
if ($roleFilter !== '') {
    $where[]  = 'u.role = ?';
    $params[] = $roleFilter;
}
if ($deptFilter > 0) {
    $where[]  = 'u.department_id = ?';
    $params[] = $deptFilter;
}
if ($statusFilter !== '') {
    $where[]  = 'u.status = ?';
    $params[] = $statusFilter;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countStmt = db()->prepare("SELECT COUNT(*) FROM users u {$whereSql}");
$countStmt->execute($params);
$pagination = paginate((int) $countStmt->fetchColumn(), records_per_page(), get_int('page'));

// LIMIT/OFFSET are integers calculated by paginate(), not user text.
$listStmt = db()->prepare(
    "SELECT u.id, u.employee_id, u.full_name, u.username, u.email, u.role, u.position,
            u.status, u.profile_photo, d.name AS department
       FROM users u
       LEFT JOIN departments d ON d.id = u.department_id
       {$whereSql}
      ORDER BY u.status = 'inactive', FIELD(u.role, 'admin', 'supervisor', 'employee'), u.full_name
      LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}"
);
$listStmt->execute($params);
$users = $listStmt->fetchAll();

$filterQuery = array_filter([
    'q'          => $search,
    'role'       => $roleFilter,
    'department' => $deptFilter ?: '',
    'status'     => $statusFilter,
], fn ($v) => $v !== '');

$hasFilters = $filterQuery !== [];

$pageTitle  = 'Employees';
$activeMenu = 'employees';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Employees and User Accounts</h1>
        <p class="page-subtitle">Add, edit, view, and deactivate employee accounts and their system roles.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?= e(url('admin/roles.php')) ?>" class="btn btn-outline-secondary">
            <i class="fa-solid fa-user-shield me-1"></i> Roles &amp; Permissions
        </a>
        <a href="<?= e(url('admin/add_user.php')) ?>" class="btn btn-primary">
            <i class="fa-solid fa-user-plus me-1"></i> Add Employee
        </a>
    </div>
</div>

<form method="get" class="filter-bar" role="search">
    <div class="row g-2 align-items-end">
        <div class="col-12 col-lg-4">
            <label for="q" class="form-label">Search</label>
            <input type="search" class="form-control" id="q" name="q" value="<?= e($search) ?>"
                   placeholder="Name, Employee ID, username, email, or position" maxlength="100">
        </div>
        <div class="col-6 col-md-3 col-lg-2">
            <label for="role" class="form-label">Role</label>
            <select class="form-select" id="role" name="role">
                <option value="">All roles</option>
                <?php foreach (USER_ROLES as $role): ?>
                    <option value="<?= e($role) ?>" <?= $roleFilter === $role ? 'selected' : '' ?>><?= e(role_label($role)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-3 col-lg-2">
            <label for="department" class="form-label">Department</label>
            <select class="form-select" id="department" name="department">
                <option value="">All departments</option>
                <?php foreach ($departments as $dept): ?>
                    <option value="<?= (int) $dept['id'] ?>" <?= $deptFilter === (int) $dept['id'] ? 'selected' : '' ?>><?= e($dept['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-3 col-lg-2">
            <label for="status" class="form-label">Status</label>
            <select class="form-select" id="status" name="status">
                <option value="">All statuses</option>
                <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
        </div>
        <div class="col-6 col-md-3 col-lg-2 d-flex gap-2">
            <button type="submit" class="btn btn-primary flex-fill"><i class="fa-solid fa-magnifying-glass me-1"></i> Filter</button>
            <?php if ($hasFilters): ?>
                <a href="<?= e(url('admin/users.php')) ?>" class="btn btn-outline-secondary" title="Clear filters" aria-label="Clear filters">
                    <i class="fa-solid fa-xmark"></i>
                </a>
            <?php endif; ?>
        </div>
    </div>
</form>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span>User List</span>
        <span class="small text-muted fw-normal"><?= e(pagination_summary($pagination, count($users))) ?></span>
    </div>

    <?php if (!$users): ?>
        <div class="empty-state">
            <i class="fa-solid fa-users-slash d-block"></i>
            No users match your search or filters.
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover table-mobile-cards mb-0">
                <thead>
                <tr>
                    <th>Employee ID</th>
                    <th>Full Name</th>
                    <th>Position</th>
                    <th>Department</th>
                    <th>Email</th>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $row):
                    $isSelf = (int) $row['id'] === current_user_id();
                    $photo  = profile_photo_url($row['profile_photo']);
                    ?>
                    <tr class="<?= $row['status'] === 'inactive' ? 'text-muted' : '' ?>">
                        <td class="text-nowrap" data-label="Employee ID"><?= e($row['employee_id']) ?></td>
                        <td class="cell-title">
                            <div class="d-flex align-items-center gap-2">
                                <?php if ($photo): ?>
                                    <img src="<?= e($photo) ?>" alt="" class="avatar avatar-sm">
                                <?php else: ?>
                                    <span class="avatar avatar-sm"><?= e(initials($row['full_name'])) ?></span>
                                <?php endif; ?>
                                <span class="fw-semibold"><?= e($row['full_name']) ?></span>
                                <?php if ($isSelf): ?><span class="badge text-bg-light border">You</span><?php endif; ?>
                            </div>
                        </td>
                        <td data-label="Position"><?= e($row['position'] ?: '—') ?></td>
                        <td data-label="Department"><?= e($row['department'] ?? '—') ?></td>
                        <td class="small" data-label="Email"><?= e($row['email']) ?></td>
                        <td data-label="Username"><?= e($row['username']) ?></td>
                        <td data-label="Role"><?= role_badge($row['role']) ?></td>
                        <td data-label="Status"><?= account_status_badge($row['status']) ?></td>
                        <td class="table-actions text-end">
                            <a href="<?= e(url('admin/view_user.php?id=' . (int) $row['id'])) ?>"
                               class="btn btn-sm btn-outline-secondary" title="View" aria-label="View <?= e($row['full_name']) ?>">
                                <i class="fa-solid fa-eye"></i>
                            </a>
                            <a href="<?= e(url('admin/edit_user.php?id=' . (int) $row['id'])) ?>"
                               class="btn btn-sm btn-outline-secondary" title="Edit" aria-label="Edit <?= e($row['full_name']) ?>">
                                <i class="fa-solid fa-pen"></i>
                            </a>
                            <?php if (!$isSelf): ?>
                                <form method="post" action="<?= e(url('admin/toggle_user_status.php')) ?>" class="d-inline"
                                      data-confirm="<?= e(($row['status'] === 'active' ? 'Deactivate ' : 'Activate ') . $row['full_name'] . '?') ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                    <input type="hidden" name="return" value="<?= e(http_build_query($filterQuery + ['page' => $pagination['page']])) ?>">
                                    <?php if ($row['status'] === 'active'): ?>
                                        <input type="hidden" name="action" value="deactivate">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Deactivate"
                                                aria-label="Deactivate <?= e($row['full_name']) ?>">
                                            <i class="fa-solid fa-user-slash"></i>
                                        </button>
                                    <?php else: ?>
                                        <input type="hidden" name="action" value="activate">
                                        <button type="submit" class="btn btn-sm btn-outline-success" title="Activate"
                                                aria-label="Activate <?= e($row['full_name']) ?>">
                                            <i class="fa-solid fa-user-check"></i>
                                        </button>
                                    <?php endif; ?>
                                </form>
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
