<?php
/**
 * EODB-TaskFlow - User management helpers
 *
 * Shared by admin/add_user.php, admin/edit_user.php, admin/users.php, and
 * admin/toggle_user_status.php: form reading, validation, uniqueness checks,
 * and the "last active administrator" safeguard.
 */

declare(strict_types=1);

const PASSWORD_MIN_LENGTH = 8;

/** All departments for dropdowns and filters. */
function get_departments(): array
{
    return db()->query('SELECT id, name FROM departments ORDER BY name')->fetchAll();
}

/** Reads the user form fields from $_POST (trimmed and length-limited). */
function read_user_form(): array
{
    return [
        'employee_id'      => mb_strtoupper(post_str('employee_id', 20)),
        'full_name'        => preg_replace('/\s+/u', ' ', post_str('full_name', 150)),
        'position'         => post_str('position', 100),
        'department_id'    => post_int('department_id'),
        'email'            => mb_strtolower(post_str('email', 150)),
        'username'         => post_str('username', 50),
        'role'             => post_str('role', 20),
        'status'           => post_str('status', 10),
        'password'         => is_string($_POST['password'] ?? null) ? $_POST['password'] : '',
        'password_confirm' => is_string($_POST['password_confirm'] ?? null) ? $_POST['password_confirm'] : '',
    ];
}

/** Password policy: at least 8 characters with upper case, lower case, number, and symbol. */
function password_policy_errors(string $password, string $username = ''): array
{
    $errors = [];
    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        $errors[] = 'at least ' . PASSWORD_MIN_LENGTH . ' characters';
    }
    if (strlen($password) > 72) {
        $errors[] = 'no more than 72 characters';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'an upper-case letter';
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'a lower-case letter';
    }
    if (!preg_match('/\d/', $password)) {
        $errors[] = 'a number';
    }
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = 'a special character (e.g. ! @ # $)';
    }

    $messages = $errors ? ['Password must contain ' . implode(', ', $errors) . '.'] : [];
    if ($username !== '' && strcasecmp($password, $username) === 0) {
        $messages[] = 'Password must not be the same as the username.';
    }
    return $messages;
}

/** True if another user (other than $exceptId) already uses this value. */
function user_value_taken(string $column, string $value, int $exceptId = 0): bool
{
    // Column names come from this fixed list only, never from user input.
    if (!in_array($column, ['employee_id', 'username', 'email'], true)) {
        throw new InvalidArgumentException('Invalid column');
    }
    $stmt = db()->prepare("SELECT COUNT(*) FROM users WHERE `{$column}` = ? AND id <> ?");
    $stmt->execute([$value, $exceptId]);
    return (int) $stmt->fetchColumn() > 0;
}

/** Number of active administrators, optionally excluding one user. */
function count_active_admins(int $exceptId = 0): int
{
    $stmt = db()->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active' AND id <> ?");
    $stmt->execute([$exceptId]);
    return (int) $stmt->fetchColumn();
}

/**
 * Validates the user form.
 *
 * @param array      $data     from read_user_form()
 * @param array|null $existing the current record when editing, null when adding
 * @return array<string,string> field name => error message (empty when valid)
 */
function validate_user_form(array $data, ?array $existing): array
{
    $errors = [];
    $userId = $existing ? (int) $existing['id'] : 0;

    // Employee ID
    if ($data['employee_id'] === '') {
        $errors['employee_id'] = 'Employee ID is required.';
    } elseif (!preg_match('/^[A-Z0-9][A-Z0-9-]{1,19}$/', $data['employee_id'])) {
        $errors['employee_id'] = 'Use 2-20 letters, numbers, or hyphens (e.g. RHU-EMP-007).';
    } elseif (user_value_taken('employee_id', $data['employee_id'], $userId)) {
        $errors['employee_id'] = 'This Employee ID is already assigned to another user.';
    }

    // Full name
    if ($data['full_name'] === '') {
        $errors['full_name'] = 'Full name is required.';
    } elseif (mb_strlen($data['full_name']) < 3) {
        $errors['full_name'] = 'Full name is too short.';
    } elseif (!preg_match("/^[\\p{L}][\\p{L}\\s.,'-]*$/u", $data['full_name'])) {
        $errors['full_name'] = 'Full name may only contain letters, spaces, periods, commas, apostrophes, and hyphens.';
    }

    // Position (optional)
    if ($data['position'] !== '' && !preg_match("/^[\\p{L}\\p{N}\\s.,'()\\/&-]+$/u", $data['position'])) {
        $errors['position'] = 'Position contains characters that are not allowed.';
    }

    // Department
    if ($data['department_id'] === 0) {
        $errors['department_id'] = 'Please select a department.';
    } else {
        $stmt = db()->prepare('SELECT COUNT(*) FROM departments WHERE id = ?');
        $stmt->execute([$data['department_id']]);
        if ((int) $stmt->fetchColumn() === 0) {
            $errors['department_id'] = 'Please select a valid department.';
        }
    }

    // Email
    if ($data['email'] === '') {
        $errors['email'] = 'Email address is required.';
    } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    } elseif (user_value_taken('email', $data['email'], $userId)) {
        $errors['email'] = 'This email address is already used by another account.';
    }

    // Username
    if ($data['username'] === '') {
        $errors['username'] = 'Username is required.';
    } elseif (!preg_match('/^[A-Za-z0-9._]{3,50}$/', $data['username'])) {
        $errors['username'] = 'Use 3-50 letters, numbers, periods, or underscores (no spaces).';
    } elseif (user_value_taken('username', $data['username'], $userId)) {
        $errors['username'] = 'This username is already taken.';
    }

    // Role and status
    if (!in_array($data['role'], USER_ROLES, true)) {
        $errors['role'] = 'Please select a valid role.';
    }
    if (!in_array($data['status'], ['active', 'inactive'], true)) {
        $errors['status'] = 'Please select a valid status.';
    }

    // Password: required when adding, optional when editing (blank = keep current)
    $passwordGiven = $data['password'] !== '' || $data['password_confirm'] !== '';
    if ($existing === null && !$passwordGiven) {
        $errors['password'] = 'Password is required.';
    } elseif ($passwordGiven) {
        $policy = password_policy_errors($data['password'], $data['username']);
        if ($policy) {
            $errors['password'] = implode(' ', $policy);
        } elseif (!hash_equals($data['password'], $data['password_confirm'])) {
            $errors['password_confirm'] = 'Passwords do not match.';
        }
    }

    // Safeguards when editing
    if ($existing !== null && !isset($errors['role']) && !isset($errors['status'])) {
        $isSelf = $userId === current_user_id();

        if ($isSelf && $data['role'] !== 'admin') {
            $errors['role'] = 'You cannot remove your own Administrator role.';
        }
        if ($isSelf && $data['status'] !== 'active') {
            $errors['status'] = 'You cannot deactivate your own account.';
        }

        $wasActiveAdmin = $existing['role'] === 'admin' && $existing['status'] === 'active';
        $staysActiveAdmin = $data['role'] === 'admin' && $data['status'] === 'active';
        if ($wasActiveAdmin && !$staysActiveAdmin && count_active_admins($userId) === 0) {
            $errors['role'] = 'This is the only active Administrator. Add or activate another administrator first.';
        }
    }

    return $errors;
}

/** Number of unfinished tasks assigned to a user (Pending, In Progress, or Overdue). */
function count_open_tasks(int $userId): int
{
    $stmt = db()->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND status <> 'Completed'");
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn();
}
