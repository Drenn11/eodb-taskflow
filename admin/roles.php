<?php
/**
 * EODB-TaskFlow - Roles and permissions (Administrator)
 *
 * Shows the three roles, how many accounts have each role, and what each role
 * can do. A user's role is changed on the Edit Employee page.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$counts = [];
foreach (db()->query(
    "SELECT role, COUNT(*) AS total, COALESCE(SUM(status = 'active'), 0) AS active
       FROM users GROUP BY role"
) as $row) {
    $counts[$row['role']] = $row;
}

$descriptions = [
    'admin'      => 'Manages user accounts, roles, and system settings. Views all tasks, reports, and activity logs.',
    'supervisor' => 'Creates and assigns tasks, monitors progress and deadlines, reviews accomplishments, and generates reports.',
    'employee'   => 'Views assigned tasks, updates status and progress, adds remarks, and views personal productivity.',
];

// Permission matrix: [permission, admin, supervisor, employee]
$permissions = [
    ['View dashboard',                          true,  true,  true],
    ['Manage users (add, edit, deactivate)',    true,  false, false],
    ['Manage roles',                            true,  false, false],
    ['Manage system settings',                  true,  false, false],
    ['View activity logs',                      true,  false, false],
    ['View all tasks',                          true,  true,  false],
    ['Create, assign, and edit tasks',          false, true,  false],
    ['Delete tasks',                            false, true,  false],
    ['Monitor tasks, deadlines, and overdue',   true,  true,  false],
    ['View employee performance',               true,  true,  false],
    ['Review task accomplishments',             false, true,  false],
    ['Generate reports',                        true,  true,  false],
    ['View own assigned tasks',                 false, false, true],
    ['Update task status, progress, remarks',   false, false, true],
    ['Mark own task as completed',              false, false, true],
    ['View own task history and productivity',  false, false, true],
    ['Receive notifications',                   true,  true,  true],
    ['Manage own profile',                      true,  true,  true],
];

$pageTitle  = 'Roles & Permissions';
$activeMenu = 'employees';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= e(url('admin/users.php')) ?>">Employees</a></li>
                <li class="breadcrumb-item active" aria-current="page">Roles &amp; Permissions</li>
            </ol>
        </nav>
        <h1 class="page-title">Roles &amp; Permissions</h1>
        <p class="page-subtitle">
            Access is enforced on every page by the server. To change a user's role, open
            <a href="<?= e(url('admin/users.php')) ?>">Employees</a> and choose Edit.
        </p>
    </div>
</div>

<div class="row g-3 mb-4">
    <?php foreach (USER_ROLES as $role):
        $total  = (int) ($counts[$role]['total'] ?? 0);
        $active = (int) ($counts[$role]['active'] ?? 0);
        ?>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h2 class="h6 mb-0"><?= e(role_label($role)) ?></h2>
                        <?= role_badge($role) ?>
                    </div>
                    <p class="small text-muted"><?= e($descriptions[$role]) ?></p>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="small"><strong><?= $active ?></strong> active of <?= $total ?> account(s)</span>
                        <a class="small" href="<?= e(url('admin/users.php?role=' . $role)) ?>">View users</a>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="card-header">Permission Matrix</div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
            <tr>
                <th>Permission</th>
                <th class="text-center">Administrator</th>
                <th class="text-center">Supervisor</th>
                <th class="text-center">Employee</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($permissions as [$label, $admin, $supervisor, $employee]): ?>
                <tr>
                    <td><?= e($label) ?></td>
                    <?php foreach ([$admin, $supervisor, $employee] as $allowed): ?>
                        <td class="text-center">
                            <?php if ($allowed): ?>
                                <i class="fa-solid fa-check permission-yes" aria-label="Allowed"></i>
                            <?php else: ?>
                                <i class="fa-solid fa-minus permission-no" aria-label="Not allowed"></i>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
