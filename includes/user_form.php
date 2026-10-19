<?php
/**
 * EODB-TaskFlow - User form fields (shared by add_user.php and edit_user.php)
 *
 * Expects these variables from the including page:
 *   array $form        current field values
 *   array $errors      field => error message
 *   array $departments from get_departments()
 *   bool  $isEdit      true on the edit page (password becomes optional)
 *   bool  $isSelf      true when the admin is editing their own account
 */

declare(strict_types=1);

$invalid = static fn (string $field): string => isset($errors[$field]) ? ' is-invalid' : '';
$feedback = static function (string $field) use ($errors): string {
    return isset($errors[$field])
        ? '<div class="invalid-feedback">' . e($errors[$field]) . '</div>'
        : '';
};
?>
<?= csrf_field() ?>

<p class="form-section-title">Employee Information</p>
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <label for="employee_id" class="form-label">Employee ID <span class="required">*</span></label>
        <input type="text" class="form-control<?= $invalid('employee_id') ?>" id="employee_id" name="employee_id"
               value="<?= e($form['employee_id']) ?>" maxlength="20" required placeholder="e.g. RHU-EMP-007"
               pattern="[A-Za-z0-9][A-Za-z0-9\-]{1,19}">
        <?= $feedback('employee_id') ?>
    </div>
    <div class="col-md-8">
        <label for="full_name" class="form-label">Full Name <span class="required">*</span></label>
        <input type="text" class="form-control<?= $invalid('full_name') ?>" id="full_name" name="full_name"
               value="<?= e($form['full_name']) ?>" maxlength="150" required placeholder="e.g. Juan M. Dela Cruz">
        <?= $feedback('full_name') ?>
    </div>
    <div class="col-md-6">
        <label for="position" class="form-label">Position</label>
        <input type="text" class="form-control<?= $invalid('position') ?>" id="position" name="position"
               value="<?= e($form['position']) ?>" maxlength="100" placeholder="e.g. Rural Health Midwife">
        <?= $feedback('position') ?>
    </div>
    <div class="col-md-6">
        <label for="department_id" class="form-label">Department <span class="required">*</span></label>
        <select class="form-select<?= $invalid('department_id') ?>" id="department_id" name="department_id" required>
            <option value="">Select department</option>
            <?php foreach ($departments as $dept): ?>
                <option value="<?= (int) $dept['id'] ?>" <?= (int) $form['department_id'] === (int) $dept['id'] ? 'selected' : '' ?>>
                    <?= e($dept['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?= $feedback('department_id') ?>
    </div>
    <div class="col-md-6">
        <label for="email" class="form-label">Email Address <span class="required">*</span></label>
        <input type="email" class="form-control<?= $invalid('email') ?>" id="email" name="email"
               value="<?= e($form['email']) ?>" maxlength="150" required autocomplete="off">
        <?= $feedback('email') ?>
    </div>
</div>

<p class="form-section-title">Account and Access</p>
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <label for="username" class="form-label">Username <span class="required">*</span></label>
        <input type="text" class="form-control<?= $invalid('username') ?>" id="username" name="username"
               value="<?= e($form['username']) ?>" maxlength="50" required autocomplete="off"
               pattern="[A-Za-z0-9._]{3,50}">
        <?= $feedback('username') ?>
        <div class="form-text">3-50 letters, numbers, periods, or underscores.</div>
    </div>
    <div class="col-md-4">
        <label for="role" class="form-label">Role <span class="required">*</span></label>
        <select class="form-select<?= $invalid('role') ?>" id="role" name="role" required <?= $isSelf ? 'disabled' : '' ?>>
            <?php foreach (USER_ROLES as $role): ?>
                <option value="<?= e($role) ?>" <?= $form['role'] === $role ? 'selected' : '' ?>><?= e(role_label($role)) ?></option>
            <?php endforeach; ?>
        </select>
        <?php if ($isSelf): ?>
            <input type="hidden" name="role" value="<?= e($form['role']) ?>">
            <div class="form-text">You cannot change your own role.</div>
        <?php endif; ?>
        <?= $feedback('role') ?>
    </div>
    <div class="col-md-4">
        <label for="status" class="form-label">Account Status <span class="required">*</span></label>
        <select class="form-select<?= $invalid('status') ?>" id="status" name="status" required <?= $isSelf ? 'disabled' : '' ?>>
            <option value="active" <?= $form['status'] === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="inactive" <?= $form['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
        </select>
        <?php if ($isSelf): ?>
            <input type="hidden" name="status" value="<?= e($form['status']) ?>">
            <div class="form-text">You cannot deactivate your own account.</div>
        <?php endif; ?>
        <?= $feedback('status') ?>
    </div>
</div>

<p class="form-section-title"><?= $isEdit ? 'Reset Password (optional)' : 'Password' ?></p>
<?php if ($isEdit): ?>
    <p class="small text-muted mb-3">Leave both fields blank to keep the current password.</p>
<?php endif; ?>
<div class="row g-3 mb-2">
    <div class="col-md-6">
        <label for="password" class="form-label">
            <?= $isEdit ? 'New Password' : 'Password' ?> <?= $isEdit ? '' : '<span class="required">*</span>' ?>
        </label>
        <div class="input-group has-validation">
            <input type="password" class="form-control<?= $invalid('password') ?>" id="password" name="password"
                   maxlength="72" autocomplete="new-password" <?= $isEdit ? '' : 'required' ?>>
            <button type="button" class="btn btn-outline-secondary" data-toggle-password="#password" aria-label="Show password">
                <i class="fa-solid fa-eye"></i>
            </button>
            <?= $feedback('password') ?>
        </div>
        <div class="form-text">At least 8 characters with upper case, lower case, a number, and a special character.</div>
    </div>
    <div class="col-md-6">
        <label for="password_confirm" class="form-label">
            Confirm Password <?= $isEdit ? '' : '<span class="required">*</span>' ?>
        </label>
        <div class="input-group has-validation">
            <input type="password" class="form-control<?= $invalid('password_confirm') ?>" id="password_confirm"
                   name="password_confirm" maxlength="72" autocomplete="new-password" <?= $isEdit ? '' : 'required' ?>>
            <button type="button" class="btn btn-outline-secondary" data-toggle-password="#password_confirm" aria-label="Show password">
                <i class="fa-solid fa-eye"></i>
            </button>
            <?= $feedback('password_confirm') ?>
        </div>
    </div>
</div>
