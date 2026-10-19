<?php
/**
 * EODB-TaskFlow - System settings (Administrator)
 *
 * 1. General and security settings stored in the `settings` table, each with
 *    its own validation range.
 * 2. Departments: add, rename, and delete (only when no user belongs to it).
 * Every change is recorded in the activity log as "update_settings".
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

/**
 * Editable settings: key => [label, type, min, max, help].
 * System-managed keys (such as last_status_check) are not listed and cannot be changed here.
 */
const EDITABLE_SETTINGS = [
    'system_name'             => ['System name',                     'text', 3, 60,   'Shown in the header, sidebar, login page, and reports.'],
    'office_name'             => ['Office name',                     'text', 3, 100,  'Shown on the login page, footer, and printed reports.'],
    'deadline_reminder_days'  => ['Deadline reminder (days before)', 'int',  1, 14,   'Employees get a daily reminder this many days before a deadline.'],
    'records_per_page'        => ['Rows per page',                   'int',  5, 100,  'Number of rows shown in tables before paging.'],
    'login_max_attempts'      => ['Failed logins before lockout',    'int',  3, 20,   'Wrong passwords allowed before a temporary lockout.'],
    'login_lockout_minutes'   => ['Lockout duration (minutes)',      'int',  1, 1440, 'How long login is blocked after too many failed attempts.'],
    'session_timeout_minutes' => ['Session timeout (minutes)',       'int',  5, 480,  'Users are logged out after this many minutes of inactivity.'],
];

function load_settings(): array
{
    $values = [];
    foreach (db()->query('SELECT setting_key, setting_value FROM settings') as $row) {
        $values[$row['setting_key']] = $row['setting_value'];
    }
    return $values;
}

$current = load_settings();
$form = [];
foreach (EDITABLE_SETTINGS as $key => $meta) {
    $form[$key] = $current[$key] ?? '';
}
$errors = [];
$deptErrors = [];
$newDepartment = '';

if (is_post()) {
    require_csrf();
    $action = post_str('action', 30);

    // ---- General and security settings ---------------------------------------
    if ($action === 'settings') {
        foreach (EDITABLE_SETTINGS as $key => [$label, $type, $min, $max]) {
            $value = post_str($key, 200);
            $form[$key] = $value;

            if ($type === 'int') {
                $number = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => $min, 'max_range' => $max]]);
                if ($number === false) {
                    $errors[$key] = "{$label} must be a whole number from {$min} to {$max}.";
                } else {
                    $form[$key] = (string) $number;
                }
            } else {
                $value = preg_replace('/\s+/u', ' ', $value);
                $form[$key] = $value;
                if (mb_strlen($value) < $min || mb_strlen($value) > $max) {
                    $errors[$key] = "{$label} must be {$min} to {$max} characters.";
                } elseif (!preg_match("/^[\\p{L}\\p{N}\\s.,'()&\\/-]+$/u", $value)) {
                    $errors[$key] = "{$label} contains characters that are not allowed.";
                }
            }
        }

        if (!$errors) {
            $changed = [];
            $save = db()->prepare(
                'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
            );
            foreach (EDITABLE_SETTINGS as $key => [$label]) {
                if (($current[$key] ?? null) !== $form[$key]) {
                    $save->execute([$key, $form[$key]]);
                    $changed[] = "{$label}: " . ($current[$key] ?? '(none)') . " → {$form[$key]}";
                }
            }

            if ($changed) {
                log_activity(current_user_id(), 'update_settings', 'Changed settings: ' . implode('; ', $changed) . '.');
                set_flash('success', 'Settings saved (' . count($changed) . ' change' . (count($changed) === 1 ? '' : 's') . ').');
            } else {
                set_flash('info', 'No settings were changed.');
            }
            redirect('admin/settings.php');
        }
    }

    // ---- Departments -----------------------------------------------------------
    elseif (in_array($action, ['add_department', 'rename_department'], true)) {
        $name = preg_replace('/\s+/u', ' ', post_str('name', 100));
        $deptId = post_int('department_id');
        $newDepartment = $action === 'add_department' ? $name : '';

        if (mb_strlen($name) < 3) {
            $deptErrors[] = 'Department name must be at least 3 characters.';
        } elseif (!preg_match("/^[\\p{L}\\p{N}\\s.,'()&\\/-]+$/u", $name)) {
            $deptErrors[] = 'Department name contains characters that are not allowed.';
        } else {
            $dup = db()->prepare('SELECT COUNT(*) FROM departments WHERE name = ? AND id <> ?');
            $dup->execute([$name, $deptId]);
            if ((int) $dup->fetchColumn() > 0) {
                $deptErrors[] = "A department named \"{$name}\" already exists.";
            }
        }

        if (!$deptErrors && $action === 'add_department') {
            db()->prepare('INSERT INTO departments (name) VALUES (?)')->execute([$name]);
            log_activity(current_user_id(), 'update_settings', "Added department \"{$name}\".");
            set_flash('success', "Department \"{$name}\" was added.");
            redirect('admin/settings.php#departments');
        }
        if (!$deptErrors && $action === 'rename_department') {
            $old = db()->prepare('SELECT name FROM departments WHERE id = ?');
            $old->execute([$deptId]);
            $oldName = $old->fetchColumn();
            if ($oldName === false) {
                $deptErrors[] = 'The selected department was not found.';
            } else {
                db()->prepare('UPDATE departments SET name = ? WHERE id = ?')->execute([$name, $deptId]);
                log_activity(current_user_id(), 'update_settings', "Renamed department \"{$oldName}\" to \"{$name}\".");
                set_flash('success', "Department renamed to \"{$name}\".");
                redirect('admin/settings.php#departments');
            }
        }
    } elseif ($action === 'delete_department') {
        $deptId = post_int('department_id');
        $stmt = db()->prepare('SELECT d.name, (SELECT COUNT(*) FROM users u WHERE u.department_id = d.id) AS users FROM departments d WHERE d.id = ?');
        $stmt->execute([$deptId]);
        $dept = $stmt->fetch();

        if (!$dept) {
            $deptErrors[] = 'The selected department was not found.';
        } elseif ((int) $dept['users'] > 0) {
            $deptErrors[] = "\"{$dept['name']}\" still has {$dept['users']} user(s). Move them to another department first.";
        } else {
            db()->prepare('DELETE FROM departments WHERE id = ?')->execute([$deptId]);
            log_activity(current_user_id(), 'update_settings', "Deleted department \"{$dept['name']}\".");
            set_flash('success', "Department \"{$dept['name']}\" was deleted.");
            redirect('admin/settings.php#departments');
        }
    } else {
        $errors['general'] = 'Invalid request.';
    }
}

$departments = db()->query(
    "SELECT d.id, d.name,
            COUNT(u.id) AS users,
            COALESCE(SUM(u.status = 'active'), 0) AS active_users
       FROM departments d
       LEFT JOIN users u ON u.department_id = d.id
      GROUP BY d.id, d.name
      ORDER BY d.name"
)->fetchAll();

$lastCheck = $current['last_status_check'] ?? null;

$pageTitle  = 'Settings';
$activeMenu = 'settings';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">System Settings</h1>
        <p class="page-subtitle">General, notification, and security settings, and the list of departments.</p>
    </div>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <i class="fa-solid fa-circle-exclamation me-1"></i>
        <?= e($errors['general'] ?? 'Please correct the highlighted settings.') ?>
    </div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-xl-7">
        <div class="card">
            <div class="card-header">General and Security Settings</div>
            <div class="card-body p-4">
                <form method="post" action="<?= e(url('admin/settings.php')) ?>" novalidate data-once>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="settings">
                    <div class="row g-3">
                        <?php foreach (EDITABLE_SETTINGS as $key => [$label, $type, $min, $max, $help]): ?>
                            <div class="<?= $type === 'text' ? 'col-12' : 'col-md-6' ?>">
                                <label for="<?= e($key) ?>" class="form-label"><?= e($label) ?></label>
                                <input type="<?= $type === 'int' ? 'number' : 'text' ?>" id="<?= e($key) ?>" name="<?= e($key) ?>"
                                       class="form-control<?= isset($errors[$key]) ? ' is-invalid' : '' ?>"
                                       value="<?= e($form[$key]) ?>" required
                                       <?= $type === 'int' ? 'min="' . $min . '" max="' . $max . '" step="1"' : 'maxlength="' . $max . '"' ?>>
                                <?php if (isset($errors[$key])): ?>
                                    <div class="invalid-feedback"><?= e($errors[$key]) ?></div>
                                <?php endif; ?>
                                <div class="form-text"><?= e($help) ?><?= $type === 'int' ? " Allowed: {$min}–{$max}." : '' ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="d-flex justify-content-end mt-4 pt-3 border-top">
                        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i> Save Settings</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-xl-5">
        <div class="card mb-3" id="departments">
            <div class="card-header">Departments</div>
            <div class="card-body">
                <?php foreach ($deptErrors as $message): ?>
                    <div class="alert alert-danger py-2 small"><?= e($message) ?></div>
                <?php endforeach; ?>
                <form method="post" action="<?= e(url('admin/settings.php')) ?>" class="mb-3" novalidate data-once>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add_department">
                    <label for="new_department" class="form-label">Add a department</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="new_department" name="name" maxlength="100"
                               value="<?= e($newDepartment) ?>" placeholder="e.g. Dental Services" required>
                        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Add</button>
                    </div>
                </form>
            </div>
            <ul class="list-group list-group-flush">
                <?php foreach ($departments as $dept): ?>
                    <li class="list-group-item">
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <form method="post" action="<?= e(url('admin/settings.php')) ?>" class="d-flex gap-2 flex-grow-1 m-0">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="rename_department">
                                <input type="hidden" name="department_id" value="<?= (int) $dept['id'] ?>">
                                <input type="text" class="form-control form-control-sm" name="name" value="<?= e($dept['name']) ?>"
                                       maxlength="100" required aria-label="Department name">
                                <button type="submit" class="btn btn-sm btn-outline-secondary" title="Save name" aria-label="Save name of <?= e($dept['name']) ?>">
                                    <i class="fa-solid fa-floppy-disk"></i>
                                </button>
                            </form>
                            <span class="small text-muted text-nowrap" title="Active users / all users"><?= (int) $dept['active_users'] ?>/<?= (int) $dept['users'] ?> users</span>
                            <?php if ((int) $dept['users'] === 0): ?>
                                <form method="post" action="<?= e(url('admin/settings.php')) ?>" class="m-0"
                                      data-confirm="<?= e('Delete the department "' . $dept['name'] . '"?') ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete_department">
                                    <input type="hidden" name="department_id" value="<?= (int) $dept['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete" aria-label="Delete <?= e($dept['name']) ?>">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="card">
            <div class="card-header">Automatic Checks</div>
            <div class="card-body small">
                <p class="mb-2">
                    Overdue detection and deadline reminders run automatically while the system is in use
                    (at most once per minute), and can also be scheduled with <code>cron/run_checks.php</code>.
                </p>
                <p class="mb-0 text-muted">Last automatic check: <strong><?= e(format_datetime($lastCheck)) ?></strong></p>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
