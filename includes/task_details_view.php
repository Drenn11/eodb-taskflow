<?php
/**
 * EODB-TaskFlow - Task details view (shared by the supervisor, employee, and admin task pages)
 *
 * Expects from the including page:
 *   array $task     from get_task()
 *   array $history  from get_task_history()
 */

declare(strict_types=1);

$note = deadline_note($task['deadline'], $task['status']);
?>
<div class="row g-3">
    <div class="col-xl-8">
        <div class="card mb-3">
            <div class="card-body p-4">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                    <span class="badge text-bg-dark"><?= e($task['task_code']) ?></span>
                    <?= task_status_badge($task['status']) ?>
                    <?= priority_badge($task['priority']) ?>
                </div>
                <h2 class="h4 mb-3"><?= e($task['title']) ?></h2>

                <div class="mb-4">
                    <div class="d-flex justify-content-between small mb-1">
                        <span class="fw-semibold">Progress</span>
                        <span><?= (int) $task['progress'] ?>%</span>
                    </div>
                    <?= progress_bar((int) $task['progress'], $task['status'], false) ?>
                    <?php if ($task['status'] === 'Overdue' && (int) $task['progress'] > 0): ?>
                        <div class="small text-muted mt-1">The deadline has passed; the employee's progress of <?= (int) $task['progress'] ?>% is kept.</div>
                    <?php endif; ?>
                </div>

                <h3 class="h6 text-muted text-uppercase small">Description</h3>
                <p class="mb-4 text-pre-line"><?= $task['description'] !== null && $task['description'] !== '' ? e($task['description']) : '<span class="text-muted">No description.</span>' ?></p>

                <h3 class="h6 text-muted text-uppercase small">Latest Remarks</h3>
                <p class="mb-0 text-pre-line"><?= $task['remarks'] !== null && $task['remarks'] !== '' ? e($task['remarks']) : '<span class="text-muted">No remarks.</span>' ?></p>
            </div>
        </div>

        <div class="card">
            <div class="card-header">Task History</div>
            <?php if (!$history): ?>
                <div class="empty-state">No history recorded.</div>
            <?php else: ?>
                <ul class="timeline list-unstyled mb-0">
                    <?php foreach ($history as $entry):
                        $statusChanged = $entry['previous_status'] !== null && $entry['previous_status'] !== $entry['new_status'];
                        $progressChanged = $entry['previous_progress'] !== null && (int) $entry['previous_progress'] !== (int) $entry['new_progress'];
                        ?>
                        <li class="timeline-item">
                            <div class="d-flex flex-wrap justify-content-between gap-2">
                                <span class="fw-semibold small">
                                    <?= e($entry['full_name'] ?? 'System (automatic)') ?>
                                    <?php if ($entry['role']): ?><span class="text-muted fw-normal">· <?= e(role_label($entry['role'])) ?></span><?php endif; ?>
                                </span>
                                <span class="small text-muted"><?= e(format_datetime($entry['updated_at'])) ?></span>
                            </div>
                            <div class="small mt-1">
                                <?php if ($entry['previous_status'] === null): ?>
                                    Task created as <?= task_status_badge($entry['new_status']) ?> at <?= (int) $entry['new_progress'] ?>%.
                                <?php else: ?>
                                    <?php if ($statusChanged): ?>
                                        Status <?= task_status_badge($entry['previous_status']) ?> → <?= task_status_badge($entry['new_status']) ?>
                                    <?php endif; ?>
                                    <?php if ($progressChanged): ?>
                                        <?= $statusChanged ? ' · ' : '' ?>Progress <?= (int) $entry['previous_progress'] ?>% → <?= (int) $entry['new_progress'] ?>%
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                            <?php if ($entry['remarks'] !== null && $entry['remarks'] !== ''): ?>
                                <div class="small text-muted mt-1 text-pre-line"><?= e($entry['remarks']) ?></div>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card">
            <div class="card-header">Task Information</div>
            <div class="card-body">
                <dl class="detail-list">
                    <dt>Task Code</dt><dd><span class="task-code"><?= e($task['task_code']) ?></span></dd>
                    <dt>Assigned To</dt>
                    <dd>
                        <?= e($task['employee_name']) ?>
                        <?php if ($task['employee_status'] !== 'active'): ?><span class="badge badge-account-inactive">Inactive</span><?php endif; ?>
                        <div class="small text-muted"><?= e(trim(($task['employee_position'] ?? '') . ' · ' . ($task['employee_department'] ?? ''), ' ·')) ?></div>
                    </dd>
                    <dt>Assigned By</dt><dd><?= e($task['supervisor_name']) ?></dd>
                    <dt>Priority</dt><dd><?= priority_badge($task['priority']) ?></dd>
                    <dt>Deadline</dt>
                    <dd>
                        <?= e(format_date($task['deadline'], 'F d, Y')) ?>
                        <?php if ($note !== ''): ?>
                            <div class="small <?= $task['status'] === 'Overdue' ? 'text-danger' : 'text-muted' ?>"><?= e($note) ?></div>
                        <?php endif; ?>
                    </dd>
                    <dt>Status</dt><dd><?= task_status_badge($task['status']) ?></dd>
                    <dt>Progress</dt><dd><?= (int) $task['progress'] ?>%</dd>
                    <dt>Date Created</dt><dd><?= e(format_datetime($task['created_at'])) ?></dd>
                    <dt>Last Updated</dt><dd><?= e(format_datetime($task['updated_at'])) ?></dd>
                    <dt>Date Completed</dt><dd><?= e(format_datetime($task['completed_at'])) ?></dd>
                </dl>
            </div>
        </div>
    </div>
</div>
