<?php
/**
 * EODB-TaskFlow - Create and assign a task (Supervisor)
 *
 * ALGORITHM 2 - TASK ASSIGNMENT
 *   1. Supervisor opens Create Task.
 *   2. Supervisor enters task details.
 *   3. System validates the data.
 *   4. System saves the task.
 *   5. System generates a task code.
 *   6. System assigns the task to the selected employee.
 *   7. System creates a notification.
 *   8. System records the activity.
 * Steps 4-8 run in one database transaction: either everything is saved or nothing is.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/task_functions.php';
require_role('supervisor');

$employees = get_assignable_employees();
$errors = [];
$task = null;

// Step 1: empty form (an employee can be pre-selected with ?employee=ID)
$form = [
    'title'            => '',
    'description'      => '',
    'assigned_to'      => get_int('employee'),
    'priority'         => 'Medium',
    'deadline'         => date('Y-m-d', strtotime('+7 days')),
    'initial_status'   => 'Pending',
    'initial_progress' => '',
    'remarks'          => '',
];

if (is_post()) {
    require_csrf();

    // Step 2: read the entered details
    $data = read_task_form();
    $form = array_merge($form, $data);
    if ($form['initial_progress'] === false) {
        $form['initial_progress'] = post_str('initial_progress', 3);
    }

    // Step 3: validate
    $errors = validate_task_form($data, null);

    if (!$errors) {
        $pdo = db();
        $progress = initial_progress_from_form($data);
        $status = compute_task_status($progress, $data['deadline']);   // Algorithm 3

        try {
            $pdo->beginTransaction();

            // Step 5: generate the task code
            $taskCode = generate_task_code();

            // Steps 4 and 6: save the task and assign it to the employee
            $insert = $pdo->prepare(
                'INSERT INTO tasks
                    (task_code, title, description, assigned_by, assigned_to, priority, deadline, status, progress, remarks)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $insert->execute([
                $taskCode,
                $data['title'],
                $data['description'] !== '' ? $data['description'] : null,
                current_user_id(),
                $data['assigned_to'],
                $data['priority'],
                $data['deadline'],
                $status,
                $progress,
                $data['remarks'] !== '' ? $data['remarks'] : null,
            ]);
            $taskId = (int) $pdo->lastInsertId();

            // First entry of the task history
            record_task_update(
                $taskId,
                current_user_id(),
                null,
                $status,
                null,
                $progress,
                'Task created and assigned.' . ($data['remarks'] !== '' ? ' ' . $data['remarks'] : '')
            );

            $employee = get_task($taskId);

            // Step 7: notify the employee
            create_notification(
                $data['assigned_to'],
                $taskId,
                'New Task Assigned',
                "New task assigned to you: {$taskCode} - {$data['title']}. Deadline: " . format_date($data['deadline']) . '.',
                'task_assigned'
            );

            // Step 8: record the activity
            log_activity(
                current_user_id(),
                'create_task',
                "Created and assigned {$taskCode} to {$employee['employee_name']} (priority {$data['priority']}, deadline {$data['deadline']})."
            );

            $pdo->commit();

            set_flash('success', "Task {$taskCode} was created and assigned to {$employee['employee_name']}.");
            redirect('supervisor/task_details.php?id=' . $taskId);
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[EODB-TaskFlow] create_task failed: ' . $e->getMessage());
            $errors['general'] = 'The task could not be saved. Please try again.';
        }
    }
}

$pageTitle   = 'Create Task';
$activeMenu  = 'create_task';
$pageScripts = ['js/task_form.js'];
require __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= e(url('supervisor/tasks.php')) ?>">Tasks</a></li>
                <li class="breadcrumb-item active" aria-current="page">Create Task</li>
            </ol>
        </nav>
        <h1 class="page-title">Create and Assign Task</h1>
        <p class="page-subtitle">The task code is generated automatically and the employee is notified.</p>
    </div>
</div>

<?php if (!$employees): ?>
    <div class="alert alert-warning">
        There are no active employees to assign tasks to. Ask the administrator to add or activate an employee account.
    </div>
<?php endif; ?>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <i class="fa-solid fa-circle-exclamation me-1"></i>
        <?= e($errors['general'] ?? 'Please correct the highlighted fields.') ?>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-body p-4">
        <form method="post" action="<?= e(url('supervisor/create_task.php')) ?>" novalidate data-once>
            <?php require __DIR__ . '/../includes/task_form.php'; ?>

            <div class="form-actions">
                <a href="<?= e(url('supervisor/tasks.php')) ?>" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary" <?= $employees ? '' : 'disabled' ?>>
                    <i class="fa-solid fa-paper-plane me-1"></i> Create and Assign
                </button>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
