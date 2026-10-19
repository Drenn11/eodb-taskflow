<?php
/**
 * EODB-TaskFlow - Edit employee / user account (Administrator)
 *
 * Updates employee details, role (Manage Roles), status, and optionally
 * resets the password. Safeguards: an administrator cannot change their own
 * role or deactivate themselves, and the last active administrator cannot be
 * removed. Records "edit_user" (and "deactivate_user"/"activate_user").
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/user_functions.php';
require_role('admin');

$userId = is_post() ? post_int('id') : get_int('id');

$stmt = db()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$userId]);
$existing = $stmt->fetch();

if (!$existing) {
    set_flash('warning', 'The selected user was not found.');
    redirect('admin/users.php');
}

$departments = get_departments();
$isEdit = true;
$isSelf = (int) $existing['id'] === current_user_id();
$errors = [];
$form = [
    'employee_id'   => $existing['employee_id'],
    'full_name'     => $existing['full_name'],
    'position'      => (string) $existing['position'],
    'department_id' => (int) $existing['department_id'],
    'email'         => $existing['email'],
    'username'      => $existing['username'],
    'role'          => $existing['role'],
    'status'        => $existing['status'],
];

if (is_post()) {
    require_csrf();

    $data = read_user_form();
    $errors = validate_user_form($data, $existing);
    $form = array_merge($form, array_diff_key($data, ['password' => 1, 'password_confirm' => 1]));

    if (!$errors) {
        $resetPassword = $data['password'] !== '';

        // Describe what changed for the activity log
        $labels = [
            'employee_id'   => 'Employee ID',
            'full_name'     => 'name',
            'position'      => 'position',
            'department_id' => 'department',
            'email'         => 'email',
            'username'      => 'username',
            'role'          => 'role',
            'status'        => 'status',
        ];
        $changes = [];
        foreach ($labels as $field => $label) {
            $old = (string) ($existing[$field] ?? '');
            $new = (string) $data[$field];
            if ($old !== $new) {
                $changes[] = in_array($field, ['role', 'status'], true) ? "{$label} {$old} → {$new}" : $label;
            }
        }
        if ($resetPassword) {
            $changes[] = 'password reset';
        }

        try {
            $sql = 'UPDATE users
                       SET employee_id = ?, full_name = ?, username = ?, email = ?, role = ?,
                           department_id = ?, position = ?, status = ?'
                . ($resetPassword ? ', password = ?' : '')
                . ' WHERE id = ?';

            $params = [
                $data['employee_id'],
                $data['full_name'],
                $data['username'],
                $data['email'],
                $data['role'],
                $data['department_id'],
                $data['position'] !== '' ? $data['position'] : null,
                $data['status'],
            ];
            if ($resetPassword) {
                $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
            }
            $params[] = $existing['id'];

            db()->prepare($sql)->execute($params);

            // A deactivated account, changed role, or new password ends "Remember me" logins
            if ($resetPassword || $data['status'] !== 'active' || $data['role'] !== $existing['role']) {
                delete_remember_tokens_for_user((int) $existing['id']);
            }

            if ($changes) {
                log_activity(
                    current_user_id(),
                    'edit_user',
                    "Edited user {$data['full_name']} ({$data['employee_id']}): " . implode(', ', $changes) . '.'
                );
            }
            if ($existing['status'] !== $data['status']) {
                log_activity(
                    current_user_id(),
                    $data['status'] === 'active' ? 'activate_user' : 'deactivate_user',
                    ($data['status'] === 'active' ? 'Activated' : 'Deactivated')
                        . " user {$data['full_name']} ({$data['employee_id']})."
                );
            }

            if ($data['status'] === 'inactive' && $existing['status'] === 'active') {
                $open = count_open_tasks((int) $existing['id']);
                if ($open > 0) {
                    set_flash('warning', "{$data['full_name']} still has {$open} unfinished task(s). Please reassign them to an active employee.");
                }
            }

            set_flash('success', $changes ? "{$data['full_name']} was updated successfully." : 'No changes were made.');
            redirect('admin/view_user.php?id=' . (int) $existing['id']);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $errors['username'] = 'The Employee ID, username, or email is already in use. Please check and try again.';
            } else {
                error_log('[EODB-TaskFlow] edit_user failed: ' . $e->getMessage());
                $errors['general'] = 'The changes could not be saved. Please try again.';
            }
        }
    }
}

$pageTitle  = 'Edit Employee';
$activeMenu = 'employees';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= e(url('admin/users.php')) ?>">Employees</a></li>
                <li class="breadcrumb-item"><a href="<?= e(url('admin/view_user.php?id=' . (int) $existing['id'])) ?>"><?= e($existing['full_name']) ?></a></li>
                <li class="breadcrumb-item active" aria-current="page">Edit</li>
            </ol>
        </nav>
        <h1 class="page-title">Edit Employee</h1>
        <p class="page-subtitle">Update details, change the role, deactivate the account, or reset the password.</p>
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
        <form method="post" action="<?= e(url('admin/edit_user.php')) ?>" novalidate data-once>
            <input type="hidden" name="id" value="<?= (int) $existing['id'] ?>">
            <?php require __DIR__ . '/../includes/user_form.php'; ?>

            <div class="form-actions">
                <a href="<?= e(url('admin/view_user.php?id=' . (int) $existing['id'])) ?>" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i> Save Changes</button>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
