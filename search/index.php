<?php
/**
 * EODB-TaskFlow - Quick search (all roles)
 *
 * One search box (top bar) for:
 *   Tasks      - task code, title, description, employee name
 *   Employees  - name, Employee ID, position, department (admin also: username, email)
 *
 * Results respect each role's access:
 *   Employee    only their own tasks; no employee search
 *   Supervisor  all tasks; employees (role "employee")
 *   Admin       all tasks (read-only view); all user accounts
 * Each section shows up to 15 matches with a link to the full filtered list.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/task_functions.php';
require_login();

const SEARCH_LIMIT = 15;
const SEARCH_MIN_LENGTH = 2;

$user = current_user();
$role = $user['role'];
$q = get_str('q', 100);
$tooShort = $q !== '' && mb_strlen($q) < SEARCH_MIN_LENGTH;
$tasks = [];
$people = [];
$taskTotal = 0;
$peopleTotal = 0;

if ($q !== '' && !$tooShort) {
    $term = like_term($q);
    $statusExpr = effective_status_sql('t');

    // ---- Tasks ----------------------------------------------------------------
    $where = '(t.task_code LIKE ? OR t.title LIKE ? OR t.description LIKE ? OR e.full_name LIKE ?)';
    $params = [$term, $term, $term, $term];
    if ($role === 'employee') {
        $where .= ' AND t.assigned_to = ?';
        $params[] = (int) $user['id'];
    }

    $count = db()->prepare("SELECT COUNT(*) FROM tasks t JOIN users e ON e.id = t.assigned_to WHERE {$where}");
    $count->execute($params);
    $taskTotal = (int) $count->fetchColumn();

    $stmt = db()->prepare(
        "SELECT t.id, t.task_code, t.title, t.priority, t.deadline, t.progress, {$statusExpr} AS status,
                e.full_name AS employee_name
           FROM tasks t
           JOIN users e ON e.id = t.assigned_to
          WHERE {$where}
          ORDER BY (t.task_code = ?) DESC,
                   FIELD({$statusExpr}, 'Overdue', 'In Progress', 'Pending', 'Completed'), t.deadline
          LIMIT " . SEARCH_LIMIT
    );
    $stmt->execute(array_merge($params, [mb_strtoupper($q)]));
    $tasks = $stmt->fetchAll();

    // ---- Employees / users ------------------------------------------------------
    if ($role !== 'employee') {
        $where = '(u.full_name LIKE ? OR u.employee_id LIKE ? OR u.position LIKE ? OR d.name LIKE ?'
            . ($role === 'admin' ? ' OR u.username LIKE ? OR u.email LIKE ?' : '') . ')';
        $params = array_fill(0, $role === 'admin' ? 6 : 4, $term);
        if ($role === 'supervisor') {
            $where .= " AND u.role = 'employee'";
        }

        $count = db()->prepare("SELECT COUNT(*) FROM users u LEFT JOIN departments d ON d.id = u.department_id WHERE {$where}");
        $count->execute($params);
        $peopleTotal = (int) $count->fetchColumn();

        $stmt = db()->prepare(
            "SELECT u.id, u.employee_id, u.full_name, u.position, u.role, u.status, u.profile_photo, d.name AS department
               FROM users u
               LEFT JOIN departments d ON d.id = u.department_id
              WHERE {$where}
              ORDER BY u.status = 'inactive', u.full_name
              LIMIT " . SEARCH_LIMIT
        );
        $stmt->execute($params);
        $people = $stmt->fetchAll();
    }
}

$taskLink = static fn (int $id): string => url(match ($role) {
    'admin'      => 'admin/task_details.php?id=',
    'supervisor' => 'supervisor/task_details.php?id=',
    default      => 'employee/task_details.php?id=',
} . $id);
$allTasksLink = url(match ($role) {
    'admin'      => 'admin/tasks.php',
    'supervisor' => 'supervisor/tasks.php',
    default      => 'employee/my_tasks.php',
} . '?q=' . urlencode($q));
$personLink = static fn (array $p): ?string => match (true) {
    $role === 'admin'                                    => url('admin/view_user.php?id=' . (int) $p['id']),
    $role === 'supervisor' && $p['role'] === 'employee'  => url('supervisor/employee_details.php?id=' . (int) $p['id']),
    default                                              => null,
};
$allPeopleLink = url(($role === 'admin' ? 'admin/users.php' : 'supervisor/employees.php?status=all&') . ($role === 'admin' ? '?q=' : 'q=') . urlencode($q));

$pageTitle  = $q !== '' ? 'Search: ' . $q : 'Search';
$activeMenu = '';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Search</h1>
        <p class="page-subtitle">
            <?= $role === 'employee'
                ? 'Find your tasks by task code, title, or description.'
                : 'Find tasks by task code, title, description, or employee, and find employees by name, ID, position, or department.' ?>
        </p>
    </div>
</div>

<form method="get" action="<?= e(url('search/index.php')) ?>" class="filter-bar" role="search">
    <div class="input-group">
        <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass"></i></span>
        <input type="search" class="form-control form-control-lg" name="q" value="<?= e($q) ?>" maxlength="100"
               placeholder="<?= $role === 'employee' ? 'e.g. TASK-2026-0002 or report' : 'e.g. TASK-2026-0002, dengue, or Dela Cruz' ?>"
               aria-label="Search" autofocus>
        <button type="submit" class="btn btn-primary px-4">Search</button>
    </div>
</form>

<?php if ($q === ''): ?>
    <div class="empty-state card"><i class="fa-solid fa-magnifying-glass d-block"></i>Type at least <?= SEARCH_MIN_LENGTH ?> characters to search.</div>
<?php elseif ($tooShort): ?>
    <div class="alert alert-warning">Please type at least <?= SEARCH_MIN_LENGTH ?> characters.</div>
<?php else: ?>
    <div class="row g-3">
        <div class="<?= $role === 'employee' ? 'col-12' : 'col-xl-7' ?>">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <span>Tasks <span class="badge text-bg-light border ms-1"><?= $taskTotal ?></span></span>
                    <?php if ($taskTotal > 0): ?>
                        <a href="<?= e($allTasksLink) ?>" class="small">Open in task list<?= $taskTotal > SEARCH_LIMIT ? ' (all ' . $taskTotal . ')' : '' ?></a>
                    <?php endif; ?>
                </div>
                <?php if (!$tasks): ?>
                    <div class="empty-state"><i class="fa-solid fa-clipboard-question d-block"></i>No tasks found for "<?= e($q) ?>".</div>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($tasks as $task): ?>
                            <li class="list-group-item">
                                <div class="d-flex flex-wrap justify-content-between gap-2">
                                    <a href="<?= e($taskLink((int) $task['id'])) ?>" class="fw-semibold"><span class="task-code"><?= e($task['task_code']) ?></span> – <?= e($task['title']) ?></a>
                                    <span><?= task_status_badge($task['status']) ?> <?= priority_badge($task['priority']) ?></span>
                                </div>
                                <div class="small text-muted mt-1">
                                    <?php if ($role !== 'employee'): ?><?= e($task['employee_name']) ?> · <?php endif; ?>
                                    Deadline <?= e(format_date($task['deadline'])) ?> · <?= (int) $task['progress'] ?>%
                                    <?php if (($note = deadline_note($task['deadline'], $task['status'])) !== ''): ?>
                                        · <span class="<?= $task['status'] === 'Overdue' ? 'text-danger' : '' ?>"><?= e($note) ?></span>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($role !== 'employee'): ?>
            <div class="col-xl-5">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <span><?= $role === 'admin' ? 'User Accounts' : 'Employees' ?> <span class="badge text-bg-light border ms-1"><?= $peopleTotal ?></span></span>
                        <?php if ($peopleTotal > 0): ?>
                            <a href="<?= e($allPeopleLink) ?>" class="small">Open in list<?= $peopleTotal > SEARCH_LIMIT ? ' (all ' . $peopleTotal . ')' : '' ?></a>
                        <?php endif; ?>
                    </div>
                    <?php if (!$people): ?>
                        <div class="empty-state"><i class="fa-solid fa-user-slash d-block"></i>No <?= $role === 'admin' ? 'users' : 'employees' ?> found for "<?= e($q) ?>".</div>
                    <?php else: ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($people as $person):
                                $link = $personLink($person);
                                $photo = profile_photo_url($person['profile_photo']);
                                ?>
                                <li class="list-group-item d-flex align-items-center gap-3">
                                    <?php if ($photo): ?>
                                        <img src="<?= e($photo) ?>" alt="" class="avatar avatar-sm">
                                    <?php else: ?>
                                        <span class="avatar avatar-sm"><?= e(initials($person['full_name'])) ?></span>
                                    <?php endif; ?>
                                    <div class="min-w-0 flex-grow-1">
                                        <?php if ($link): ?>
                                            <a href="<?= e($link) ?>" class="fw-semibold"><?= e($person['full_name']) ?></a>
                                        <?php else: ?>
                                            <span class="fw-semibold"><?= e($person['full_name']) ?></span>
                                        <?php endif; ?>
                                        <?php if ($person['status'] === 'inactive'): ?><span class="badge badge-account-inactive">Inactive</span><?php endif; ?>
                                        <div class="small text-muted">
                                            <?= e($person['employee_id']) ?> · <?= e($person['position'] ?: '—') ?> · <?= e($person['department'] ?? '—') ?>
                                        </div>
                                    </div>
                                    <?php if ($role === 'admin'): ?><?= role_badge($person['role']) ?><?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
