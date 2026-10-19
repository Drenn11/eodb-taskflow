<?php
/**
 * EODB-TaskFlow - Add employee / user account (Administrator)
 *
 * Validates the input, hashes the password with password_hash(), saves the
 * account, and records "add_user" in the activity log.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/user_functions.php';
require_role('admin');

$departments = get_departments();
$errors = [];
$form = [
    'employee_id'   => '',
    'full_name'     => '',
    'position'      => '',
    'department_id' => 0,
    'email'         => '',
    'username'      => '',
    'role'          => 'employee',
    'status'        => 'active',
];

if (is_post()) {
    require_csrf();

    $data = read_user_form();
    $errors = validate_user_form($data, null);
    $form = array_merge($form, array_diff_key($data, ['password' => 1, 'password_confirm' => 1]));

    if (!$errors) {
        try {
            $stmt = db()->prepare(
                'INSERT INTO users (employee_id, full_name, username, email, password, role, department_id, position, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $data['employee_id'],
                $data['full_name'],
                $data['username'],
                $data['email'],
                password_hash($data['password'], PASSWORD_DEFAULT),
                $data['role'],
                $data['department_id'],
                $data['position'] !== '' ? $data['position'] : null,
                $data['status'],
            ]);
            $newId = (int) db()->lastInsertId();

            log_activity(
                current_user_id(),
                'add_user',
                "Added user {$data['full_name']} ({$data['employee_id']}) with role " . role_label($data['role']) . '.'
            );

            set_flash('success', "{$data['full_name']} was added successfully.");
            redirect('admin/view_user.php?id=' . $newId);
        } catch (PDOException $e) {
            // 23000 = duplicate value (e.g. two admins saving the same username at once)
            if ($e->getCode() === '23000') {
                $errors['username'] = 'The Employee ID, username, or email is already in use. Please check and try again.';
            } else {
                error_log('[EODB-TaskFlow] add_user failed: ' . $e->getMessage());
                $errors['general'] = 'The account could not be saved. Please try again.';
            }
        }
    }
}

$isEdit = false;
$isSelf = false;

$pageTitle  = 'Add Employee';
$activeMenu = 'employees';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= e(url('admin/users.php')) ?>">Employees</a></li>
                <li class="breadcrumb-item active" aria-current="page">Add Employee</li>
            </ol>
        </nav>
        <h1 class="page-title">Add Employee</h1>
        <p class="page-subtitle">Create an account. The employee logs in with the username and password you set here.</p>
    </div>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <i class="fa-solid fa-circle-exclamation me-1"></i>
        <?= e($errors['general'] ?? 'Please correct the highlighted fields.') ?>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-body p-4">
        <form method="post" action="<?= e(url('admin/add_user.php')) ?>" novalidate data-once>
            <?php require __DIR__ . '/../includes/user_form.php'; ?>

            <div class="form-actions">
                <a href="<?= e(url('admin/users.php')) ?>" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i> Save Employee</button>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
