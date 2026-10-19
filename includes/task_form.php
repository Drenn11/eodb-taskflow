<?php
/**
 * EODB-TaskFlow - Task form fields (shared by create_task.php and edit_task.php)
 *
 * Expects from the including page:
 *   array      $form       current values
 *   array      $errors     field => message
 *   array      $employees  from get_assignable_employees()
 *   array|null $task       the task being edited, or null when creating
 */

declare(strict_types=1);

$isEdit = $task !== null;
$invalid = static fn (string $field): string => isset($errors[$field]) ? ' is-invalid' : '';
$feedback = static function (string $field) use ($errors): string {
    return isset($errors[$field]) ? '<div class="invalid-feedback">' . e($errors[$field]) . '</div>' : '';
};

// Group employees by department for the dropdown
$byDepartment = [];
foreach ($employees as $employee) {
    $byDepartment[$employee['department'] ?? 'No department'][] = $employee;
}

// When editing a task whose employee was deactivated, keep that employee selectable
$currentEmployeeListed = !$isEdit || in_array((int) $task['assigned_to'], array_map('intval', array_column($employees, 'id')), true);
?>
<?= csrf_field() ?>

<p class="form-section-title">Task Information</p>
<div class="row g-3 mb-4">
    <div class="col-12">
        <label for="title" class="form-label">Task Title <span class="required">*</span></label>
        <input type="text" class="form-control<?= $invalid('title') ?>" id="title" name="title"
               value="<?= e($form['title']) ?>" maxlength="<?= TASK_TITLE_MAX ?>" required
               placeholder="e.g. Prepare monthly program accomplishment report">
        <?= $feedback('title') ?>
    </div>
    <div class="col-12">
        <label for="description" class="form-label">Description</label>
        <textarea class="form-control<?= $invalid('description') ?>" id="description" name="description" rows="4"
                  maxlength="<?= TASK_DESCRIPTION_MAX ?>"
                  placeholder="What needs to be done, expected output, and any instructions"><?= e($form['description']) ?></textarea>
        <?= $feedback('description') ?>
    </div>
</div>

<p class="form-section-title">Assignment and Schedule</p>
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <label for="assigned_to" class="form-label">Assigned Employee <span class="required">*</span></label>
        <select class="form-select<?= $invalid('assigned_to') ?>" id="assigned_to" name="assigned_to" required>
            <option value="">Select employee</option>
            <?php if (!$currentEmployeeListed): ?>
                <option value="<?= (int) $task['assigned_to'] ?>" <?= (int) $form['assigned_to'] === (int) $task['assigned_to'] ? 'selected' : '' ?>>
                    <?= e($task['employee_name']) ?> (inactive)
                </option>
            <?php endif; ?>
            <?php foreach ($byDepartment as $department => $list): ?>
                <optgroup label="<?= e($department) ?>">
                    <?php foreach ($list as $employee): ?>
                        <option value="<?= (int) $employee['id'] ?>" <?= (int) $form['assigned_to'] === (int) $employee['id'] ? 'selected' : '' ?>>
                            <?= e($employee['full_name']) ?> – <?= e($employee['position'] ?: 'Employee') ?>
                            (<?= (int) $employee['open_tasks'] ?> open task<?= (int) $employee['open_tasks'] === 1 ? '' : 's' ?>)
                        </option>
                    <?php endforeach; ?>
                </optgroup>
            <?php endforeach; ?>
        </select>
        <?= $feedback('assigned_to') ?>
        <div class="form-text">The number of unfinished tasks helps balance the workload.</div>
    </div>
    <div class="col-sm-6 col-md-3">
        <label for="priority" class="form-label">Priority <span class="required">*</span></label>
        <select class="form-select<?= $invalid('priority') ?>" id="priority" name="priority" required>
            <?php foreach (TASK_PRIORITIES as $priority): ?>
                <option value="<?= e($priority) ?>" <?= $form['priority'] === $priority ? 'selected' : '' ?>><?= e($priority) ?></option>
            <?php endforeach; ?>
        </select>
        <?= $feedback('priority') ?>
    </div>
    <div class="col-sm-6 col-md-3">
        <label for="deadline" class="form-label">Deadline <span class="required">*</span></label>
        <input type="date" class="form-control<?= $invalid('deadline') ?>" id="deadline" name="deadline"
               value="<?= e($form['deadline']) ?>" required
               <?= $isEdit && $task['deadline'] < date('Y-m-d') ? '' : 'min="' . e(date('Y-m-d')) . '"' ?>>
        <?= $feedback('deadline') ?>
    </div>

    <?php if (!$isEdit): ?>
        <div class="col-sm-6 col-md-3">
            <label for="initial_status" class="form-label">Initial Status <span class="required">*</span></label>
            <select class="form-select<?= $invalid('initial_status') ?>" id="initial_status" name="initial_status" required>
                <option value="Pending" <?= $form['initial_status'] === 'Pending' ? 'selected' : '' ?>>Pending (not started)</option>
                <option value="In Progress" <?= $form['initial_status'] === 'In Progress' ? 'selected' : '' ?>>In Progress (already started)</option>
            </select>
            <?= $feedback('initial_status') ?>
        </div>
        <div class="col-sm-6 col-md-3" id="initialProgressGroup" <?= $form['initial_status'] === 'In Progress' ? '' : 'hidden' ?>>
            <label for="initial_progress" class="form-label">Initial Progress (%) <span class="required">*</span></label>
            <input type="number" class="form-control<?= $invalid('initial_progress') ?>" id="initial_progress"
                   name="initial_progress" min="1" max="99" step="1" value="<?= e((string) $form['initial_progress']) ?>">
            <?= $feedback('initial_progress') ?>
        </div>
        <div class="col-12">
            <div class="form-text">
                Status follows the progress rule: 0% = Pending, 1–99% = In Progress, 100% = Completed.
                A task that passes its deadline unfinished becomes Overdue automatically.
            </div>
        </div>
    <?php else: ?>
        <div class="col-md-6">
            <span class="form-label d-block">Current Status and Progress</span>
            <div class="d-flex align-items-center gap-3">
                <?= task_status_badge($task['status']) ?>
                <div class="flex-grow-1"><?= progress_bar((int) $task['progress'], $task['status']) ?></div>
            </div>
            <div class="form-text">Progress is updated by the assigned employee. Changing the deadline recalculates the status.</div>
        </div>
    <?php endif; ?>
</div>

<p class="form-section-title">Remarks</p>
<div class="mb-2">
    <label for="remarks" class="form-label">Remarks / Instructions</label>
    <textarea class="form-control<?= $invalid('remarks') ?>" id="remarks" name="remarks" rows="2"
              maxlength="<?= TASK_REMARKS_MAX ?>" placeholder="Optional notes for the employee"><?= e($form['remarks']) ?></textarea>
    <?= $feedback('remarks') ?>
</div>
