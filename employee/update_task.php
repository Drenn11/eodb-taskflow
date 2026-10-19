<?php
/**
 * EODB-TaskFlow - Update task status, progress, and remarks (Employee)
 *
 * The employee sets the status and progress and adds remarks, or marks the
 * task as completed. The saved status always follows Algorithm 3:
 *   100% = Completed; deadline passed = Overdue (progress kept);
 *   1-99% = In Progress; 0% = Pending.
 *
 * Each update writes a task history row, notifies the supervisor, and is
 * recorded in the activity log (update_task or complete_task).
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/task_functions.php';
require_role('employee');

$taskId = is_post() ? post_int('id') : get_int('id');
$task = get_own_task($taskId);

if ($task === null) {
    set_flash('warning', 'The selected task was not found in your assigned tasks.');
    redirect('employee/my_tasks.php');
}

if ($task['status'] === 'Completed') {
    set_flash('info', "{$task['task_code']} is already completed. Completed tasks can no longer be updated.");
    redirect('employee/task_details.php?id=' . $taskId);
}

$currentProgress = (int) $task['progress'];
$errors = [];
$form = [
    'status'   => $currentProgress === 0 ? 'Pending' : 'In Progress',
    'progress' => $currentProgress,
    'remarks'  => '',
];
if (get_str('complete', 1) === '1') {
    $form['status'] = 'Completed';
    $form['progress'] = 100;
}

if (is_post()) {
    require_csrf();

    $status   = post_str('status', 20);
    $progress = filter_var($_POST['progress'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 100]]);
    $remarks  = post_str('remarks', TASK_REMARKS_MAX);
    $seenProgress = filter_var($_POST['seen_progress'] ?? '', FILTER_VALIDATE_INT);

    $form = ['status' => $status, 'progress' => $progress === false ? $currentProgress : $progress, 'remarks' => $remarks];

    // Validation: the chosen status must agree with the progress value
    if (!in_array($status, ['Pending', 'In Progress', 'Completed'], true)) {
        $errors['status'] = 'Please choose a status.';
    }
    if ($progress === false) {
        $errors['progress'] = 'Progress must be a whole number from 0 to 100.';
    } elseif (!isset($errors['status'])) {
        if ($status === 'Pending' && $progress !== 0) {
            $errors['progress'] = 'A Pending task must have 0% progress.';
        } elseif ($status === 'In Progress' && ($progress < 1 || $progress > 99)) {
            $errors['progress'] = 'An In Progress task must have a progress from 1% to 99%.';
        } elseif ($status === 'Completed' && $progress !== 100) {
            $errors['progress'] = 'A Completed task must have 100% progress.';
        }
    }

    if (!$errors) {
        if ($progress === $currentProgress && $remarks === '') {
            $errors['remarks'] = 'Nothing changed. Change the progress or add a remark.';
        } elseif ($status === 'Completed' && $remarks === '') {
            $errors['remarks'] = 'Please describe what was accomplished before marking the task as completed.';
        } elseif ($progress < $currentProgress && $remarks === '') {
            $errors['remarks'] = "Please explain why the progress is lower than before ({$currentProgress}%).";
        }
    }

    // Someone else changed the task after this form was opened
    if (!$errors && $seenProgress !== false && $seenProgress !== $currentProgress) {
        $errors['general'] = "This task was updated elsewhere (progress is now {$currentProgress}%). Please review and save again.";
    }

    if (!$errors) {
        $oldStatus = $task['status'];
        $newStatus = compute_task_status($progress, $task['deadline']);   // Algorithm 3
        $isCompletion = $newStatus === 'Completed';
        $me = current_user();
        $pdo = db();

        try {
            $pdo->beginTransaction();

            $update = $pdo->prepare(
                'UPDATE tasks
                    SET progress = ?, status = ?,
                        remarks = COALESCE(?, remarks),
                        completed_at = ' . ($isCompletion ? 'NOW()' : 'NULL') . '
                  WHERE id = ? AND assigned_to = ? AND status <> \'Completed\''
            );
            $update->execute([$progress, $newStatus, $remarks !== '' ? $remarks : null, $taskId, $me['id']]);

            if ($update->rowCount() !== 1) {
                throw new RuntimeException('Task could not be updated (it may have been completed or reassigned).');
            }

            record_task_update($taskId, (int) $me['id'], $oldStatus, $newStatus, $currentProgress, $progress, $remarks);

            if ($isCompletion) {
                $late = date('Y-m-d') > $task['deadline'];
                create_notification(
                    (int) $task['assigned_by'],
                    $taskId,
                    'Task Completed',
                    "{$me['full_name']} marked {$task['task_code']} as completed" . ($late ? ' (after the deadline)' : '') . '.',
                    'task_completed'
                );
                log_activity((int) $me['id'], 'complete_task', "Marked {$task['task_code']} as completed.");
            } else {
                create_notification(
                    (int) $task['assigned_by'],
                    $taskId,
                    'Task Updated',
                    "{$me['full_name']} updated {$task['task_code']} progress to {$progress}%"
                        . ($newStatus === 'Overdue' ? ' (task is overdue)' : '') . '.',
                    'task_updated'
                );
                log_activity(
                    (int) $me['id'],
                    'update_task',
                    "Updated {$task['task_code']} progress from {$currentProgress}% to {$progress}% (status {$newStatus})."
                );
            }

            $pdo->commit();

            set_flash('success', $isCompletion
                ? "{$task['task_code']} was marked as completed. Your supervisor has been notified."
                : "{$task['task_code']} was updated to {$progress}% ({$newStatus}).");
            redirect('employee/task_details.php?id=' . $taskId);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[EODB-TaskFlow] update_task failed: ' . $e->getMessage());
            $errors['general'] = 'The update could not be saved. The task may have been changed by your supervisor. Please reload and try again.';
        }
    }
}

$isOverdue = $task['status'] === 'Overdue';
$invalid = static fn (string $field): string => isset($errors[$field]) ? ' is-invalid' : '';

$pageTitle   = 'Update ' . $task['task_code'];
$activeMenu  = 'tasks';
$pageScripts = ['js/update_task.js'];
require __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= e(url('employee/my_tasks.php')) ?>">My Tasks</a></li>
                <li class="breadcrumb-item"><a href="<?= e(url('employee/task_details.php?id=' . $taskId)) ?>" class="task-code"><?= e($task['task_code']) ?></a></li>
                <li class="breadcrumb-item active" aria-current="page">Update</li>
            </ol>
        </nav>
        <h1 class="page-title">Update Task</h1>
        <p class="page-subtitle"><?= e($task['task_code']) ?> – <?= e($task['title']) ?></p>
    </div>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <i class="fa-solid fa-circle-exclamation me-1"></i>
        <?= e($errors['general'] ?? 'Please correct the highlighted fields.') ?>
    </div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body p-4">
                <?php if ($isOverdue): ?>
                    <div class="alert alert-warning small">
                        <i class="fa-solid fa-triangle-exclamation me-1"></i>
                        This task is past its deadline (<?= e(format_date($task['deadline'])) ?>). It stays <strong>Overdue</strong>
                        until it reaches 100%, but your progress is still recorded.
                    </div>
                <?php endif; ?>

                <form method="post" action="<?= e(url('employee/update_task.php')) ?>" novalidate data-once id="updateTaskForm">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= $taskId ?>">
                    <input type="hidden" name="seen_progress" value="<?= $currentProgress ?>">

                    <fieldset class="mb-4">
                        <legend class="form-label fs-6">Status <span class="required">*</span></legend>
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach (['Pending' => 'fa-hourglass-start', 'In Progress' => 'fa-bars-progress', 'Completed' => 'fa-circle-check'] as $option => $icon): ?>
                                <input type="radio" class="btn-check" name="status" id="status_<?= e(str_replace(' ', '_', $option)) ?>"
                                       value="<?= e($option) ?>" <?= $form['status'] === $option ? 'checked' : '' ?> autocomplete="off">
                                <label class="btn btn-outline-secondary" for="status_<?= e(str_replace(' ', '_', $option)) ?>">
                                    <i class="fa-solid <?= e($icon) ?> me-1"></i><?= e($option) ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <?php if (isset($errors['status'])): ?><div class="text-danger small mt-1"><?= e($errors['status']) ?></div><?php endif; ?>
                    </fieldset>

                    <div class="mb-4">
                        <label for="progress" class="form-label">Progress <span class="required">*</span></label>
                        <div class="d-flex align-items-center gap-3">
                            <input type="range" class="form-range flex-grow-1" id="progressRange" min="0" max="100" step="5"
                                   value="<?= (int) $form['progress'] ?>" aria-label="Progress slider">
                            <div class="input-group" style="width: 120px">
                                <input type="number" class="form-control<?= $invalid('progress') ?>" id="progress" name="progress"
                                       min="0" max="100" step="1" value="<?= (int) $form['progress'] ?>" required>
                                <span class="input-group-text">%</span>
                            </div>
                        </div>
                        <?php if (isset($errors['progress'])): ?><div class="text-danger small mt-1"><?= e($errors['progress']) ?></div><?php endif; ?>
                        <div class="form-text">
                            Previous progress: <?= $currentProgress ?>%. Status after saving:
                            <strong id="statusPreview" data-deadline-passed="<?= $isOverdue ? '1' : '0' ?>">—</strong>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label for="remarks" class="form-label">Remarks <span class="text-muted small">(required when completing or lowering progress)</span></label>
                        <textarea class="form-control<?= $invalid('remarks') ?>" id="remarks" name="remarks" rows="4"
                                  maxlength="<?= TASK_REMARKS_MAX ?>"
                                  placeholder="What did you accomplish? Any problems or needs?"><?= e($form['remarks']) ?></textarea>
                        <?php if (isset($errors['remarks'])): ?><div class="invalid-feedback"><?= e($errors['remarks']) ?></div><?php endif; ?>
                    </div>

                    <div class="form-actions">
                        <a href="<?= e(url('employee/task_details.php?id=' . $taskId)) ?>" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i> Save Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">Task Summary</div>
            <div class="card-body">
                <dl class="detail-list">
                    <dt>Task Code</dt><dd><span class="task-code"><?= e($task['task_code']) ?></span></dd>
                    <dt>Priority</dt><dd><?= priority_badge($task['priority']) ?></dd>
                    <dt>Deadline</dt>
                    <dd>
                        <?= e(format_date($task['deadline'], 'F d, Y')) ?>
                        <div class="small <?= $isOverdue ? 'text-danger' : 'text-muted' ?>"><?= e(deadline_note($task['deadline'], $task['status'])) ?></div>
                    </dd>
                    <dt>Status</dt><dd><?= task_status_badge($task['status']) ?></dd>
                    <dt>Assigned By</dt><dd><?= e($task['supervisor_name']) ?></dd>
                </dl>
                <hr>
                <p class="small text-muted mb-0">
                    Status rule: 0% = Pending · 1–99% = In Progress · 100% = Completed.
                    A task past its deadline is Overdue until completed.
                </p>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
