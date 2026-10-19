<?php
/**
 * EODB-TaskFlow - Report filter bar and actions (partial)
 *
 * Expects from the including page:
 *   array $filters  from read_report_filters()
 * Shows only the filters that apply to the report type, plus the
 * Print / PDF and Export CSV buttons (which use the same filters).
 */

declare(strict_types=1);

$type = $filters['type'];
$reportPath = REPORT_TYPES[$type][1];
$query = report_filter_query($filters);
$exportQuery = http_build_query(['type' => $type] + $query);
$filterDepartments = get_departments();
$filterEmployees = db()->query(
    "SELECT id, full_name, status FROM users WHERE role = 'employee' ORDER BY status = 'inactive', full_name"
)->fetchAll();
?>
<form method="get" action="<?= e(url($reportPath)) ?>" class="filter-bar" role="search">
    <div class="row g-2 align-items-end">
        <div class="col-12 col-lg-3">
            <label for="q" class="form-label">Search</label>
            <input type="search" class="form-control" id="q" name="q" value="<?= e($filters['q']) ?>" maxlength="100"
                   placeholder="<?= $type === 'productivity' ? 'Name, Employee ID, or position' : 'Task code, task, or employee' ?>">
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <label for="employee" class="form-label">Employee</label>
            <select class="form-select" id="employee" name="employee">
                <option value="">All employees</option>
                <?php foreach ($filterEmployees as $option): ?>
                    <option value="<?= (int) $option['id'] ?>" <?= $filters['employee'] === (int) $option['id'] ? 'selected' : '' ?>>
                        <?= e($option['full_name']) ?><?= $option['status'] === 'inactive' ? ' (inactive)' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <label for="department" class="form-label">Department</label>
            <select class="form-select" id="department" name="department">
                <option value="">All departments</option>
                <?php foreach ($filterDepartments as $dept): ?>
                    <option value="<?= (int) $dept['id'] ?>" <?= $filters['department'] === (int) $dept['id'] ? 'selected' : '' ?>><?= e($dept['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php if ($type === 'tasks'): ?>
            <div class="col-6 col-md-4 col-lg-2">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All statuses</option>
                    <option value="open" <?= $filters['status'] === 'open' ? 'selected' : '' ?>>All unfinished</option>
                    <?php foreach (TASK_STATUSES as $option): ?>
                        <option value="<?= e($option) ?>" <?= $filters['status'] === $option ? 'selected' : '' ?>><?= e($option) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>

        <?php if ($type !== 'productivity'): ?>
            <div class="col-6 col-md-4 col-lg-2">
                <label for="priority" class="form-label">Priority</label>
                <select class="form-select" id="priority" name="priority">
                    <option value="">All priorities</option>
                    <?php foreach (TASK_PRIORITIES as $option): ?>
                        <option value="<?= e($option) ?>" <?= $filters['priority'] === $option ? 'selected' : '' ?>><?= e($option) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php else: ?>
            <div class="col-6 col-md-4 col-lg-2">
                <label for="status" class="form-label">Employees</label>
                <select class="form-select" id="status" name="status">
                    <option value="active" <?= $filters['status'] === 'active' ? 'selected' : '' ?>>Active only</option>
                    <option value="inactive" <?= $filters['status'] === 'inactive' ? 'selected' : '' ?>>Inactive only</option>
                    <option value="all" <?= $filters['status'] === 'all' ? 'selected' : '' ?>>Active and inactive</option>
                </select>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <label for="sort" class="form-label">Sort by</label>
                <select class="form-select" id="sort" name="sort">
                    <?php foreach (['name' => 'Name (A–Z)', 'rate_desc' => 'Completion rate (highest)', 'rate_asc' => 'Completion rate (lowest)', 'overdue' => 'Most overdue', 'total' => 'Most tasks'] as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= $filters['sort'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>

        <?php if ($type === 'tasks'): ?>
            <div class="col-6 col-md-4 col-lg-2">
                <label for="basis" class="form-label">Date range applies to</label>
                <select class="form-select" id="basis" name="basis">
                    <?php foreach (TASK_DATE_BASIS as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= $filters['basis'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>

        <div class="col-6 col-md-4 col-lg-2">
            <label for="from" class="form-label"><?= $type === 'accomplishment' ? 'Completed from' : ($type === 'productivity' ? 'Tasks created from' : 'From') ?></label>
            <input type="date" class="form-control" id="from" name="from" value="<?= e($filters['from']) ?>">
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <label for="to" class="form-label"><?= $type === 'accomplishment' ? 'Completed to' : ($type === 'productivity' ? 'Tasks created to' : 'To') ?></label>
            <input type="date" class="form-control" id="to" name="to" value="<?= e($filters['to']) ?>">
        </div>
        <div class="col-12 col-md-4 col-lg-2 d-flex gap-2 ms-lg-auto">
            <button type="submit" class="btn btn-primary flex-fill"><i class="fa-solid fa-filter me-1"></i> Generate</button>
            <a href="<?= e(url($reportPath)) ?>" class="btn btn-outline-secondary" title="Reset filters" aria-label="Reset filters">
                <i class="fa-solid fa-rotate-left"></i>
            </a>
        </div>
    </div>
</form>

<div class="d-flex flex-wrap gap-2 justify-content-end mb-3">
    <a href="<?= e(url('reports/print_report.php?' . $exportQuery)) ?>" target="_blank" rel="noopener" class="btn btn-outline-secondary">
        <i class="fa-solid fa-print me-1"></i> Print / Save as PDF
    </a>
    <a href="<?= e(url('reports/export_csv.php?' . $exportQuery)) ?>" class="btn btn-outline-secondary">
        <i class="fa-solid fa-file-csv me-1"></i> Export CSV
    </a>
</div>
