<?php
/**
 * EODB-TaskFlow - Task monitoring table (partial)
 *
 * | Task Code | Task | Employee | Priority | Deadline | Status | Progress | Action |
 *
 * Expects from the including page:
 *   array  $tasks        rows from fetch_tasks_page()
 *   array  $pagination
 *   array  $filters
 *   string $detailsPath  e.g. 'supervisor/task_details.php'
 *   bool   $canManage    show Edit / Delete (supervisor only)
 */

declare(strict_types=1);
?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span>Task List</span>
        <span class="small text-muted fw-normal"><?= e(pagination_summary($pagination, count($tasks))) ?></span>
    </div>

    <?php if (!$tasks): ?>
        <div class="empty-state">
            <i class="fa-solid fa-clipboard-list d-block"></i>
            No tasks match your search or filters.
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover table-mobile-cards mb-0">
                <thead>
                <tr>
                    <th>Task Code</th>
                    <th>Task</th>
                    <th>Employee</th>
                    <th>Priority</th>
                    <th>Deadline</th>
                    <th>Status</th>
                    <th class="col-w-progress">Progress</th>
                    <th class="text-end">Action</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($tasks as $row):
                    $note = deadline_note($row['deadline'], $row['status']);
                    $isCompleted = $row['status'] === 'Completed';
                    ?>
                    <tr>
                        <td class="text-nowrap" data-label="Task Code"><span class="task-code"><?= e($row['task_code']) ?></span></td>
                        <td class="cell-title">
                            <?= e($row['title']) ?>
                            <div class="small text-muted">Updated <?= e(format_datetime($row['updated_at'])) ?></div>
                        </td>
                        <td data-label="Employee">
                            <div><?= e($row['employee_name']) ?>
                            <div class="small text-muted"><?= e($row['department'] ?? '') ?></div></div>
                        </td>
                        <td data-label="Priority"><?= priority_badge($row['priority']) ?></td>
                        <td class="text-nowrap" data-label="Deadline">
                            <div><?= e(format_date($row['deadline'])) ?>
                            <?php if ($note !== ''): ?>
                                <div class="small <?= $row['status'] === 'Overdue' ? 'text-danger' : 'text-muted' ?>"><?= e($note) ?></div>
                            <?php endif; ?></div>
                        </td>
                        <td data-label="Status"><?= task_status_badge($row['status']) ?></td>
                        <td data-label="Progress"><?= progress_bar((int) $row['progress'], $row['status']) ?></td>
                        <td class="table-actions text-end">
                            <a href="<?= e(url($detailsPath . '?id=' . (int) $row['id'])) ?>"
                               class="btn btn-sm btn-outline-secondary" title="View" aria-label="View <?= e($row['task_code']) ?>">
                                <i class="fa-solid fa-eye"></i>
                            </a>
                            <?php if ($canManage && !$isCompleted): ?>
                                <a href="<?= e(url('supervisor/edit_task.php?id=' . (int) $row['id'])) ?>"
                                   class="btn btn-sm btn-outline-secondary" title="Edit" aria-label="Edit <?= e($row['task_code']) ?>">
                                    <i class="fa-solid fa-pen"></i>
                                </a>
                                <form method="post" action="<?= e(url('supervisor/delete_task.php')) ?>" class="d-inline"
                                      data-confirm="<?= e("Delete {$row['task_code']} – {$row['title']}? This cannot be undone.") ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete" aria-label="Delete <?= e($row['task_code']) ?>">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php if ($pagination['pages'] > 1): ?>
        <div class="card-footer bg-white d-flex justify-content-end">
            <?= render_pagination($pagination, task_filter_query($filters)) ?>
        </div>
    <?php endif; ?>
</div>
