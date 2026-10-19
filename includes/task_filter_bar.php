<?php
/**
 * EODB-TaskFlow - Task search and filter bar (partial)
 *
 * Expects from the including page:
 *   array  $filters   from read_task_filters()
 *   string $listPath  e.g. 'supervisor/tasks.php'
 *   bool   $showMine  show the "Only tasks I assigned" checkbox
 */

declare(strict_types=1);

require_once __DIR__ . '/user_functions.php';

$filterDepartments = get_departments();
$filterEmployees = employee_filter_options();
$activeFilters = task_filter_query($filters);
?>
<form method="get" action="<?= e(url($listPath)) ?>" class="filter-bar" role="search">
    <div class="row g-2 align-items-end">
        <div class="col-12 col-lg-4">
            <label for="q" class="form-label">Search</label>
            <input type="search" class="form-control" id="q" name="q" value="<?= e($filters['q']) ?>"
                   placeholder="Task code, title, description, or employee" maxlength="100">
        </div>
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
        <div class="col-6 col-md-4 col-lg-2">
            <label for="priority" class="form-label">Priority</label>
            <select class="form-select" id="priority" name="priority">
                <option value="">All priorities</option>
                <?php foreach (TASK_PRIORITIES as $option): ?>
                    <option value="<?= e($option) ?>" <?= $filters['priority'] === $option ? 'selected' : '' ?>><?= e($option) ?></option>
                <?php endforeach; ?>
            </select>
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
        <div class="col-6 col-md-4 col-lg-2">
            <label for="from" class="form-label">Deadline from</label>
            <input type="date" class="form-control" id="from" name="from" value="<?= e($filters['from']) ?>">
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <label for="to" class="form-label">Deadline to</label>
            <input type="date" class="form-control" id="to" name="to" value="<?= e($filters['to']) ?>">
        </div>
        <?php if ($showMine): ?>
            <div class="col-6 col-md-4 col-lg-3 d-flex align-items-center">
                <div class="form-check mt-3">
                    <input class="form-check-input" type="checkbox" id="mine" name="mine" value="1" <?= $filters['mine'] ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="mine">Only tasks I assigned</label>
                </div>
            </div>
        <?php endif; ?>
        <div class="col-6 col-md-4 col-lg-2 d-flex gap-2 ms-lg-auto">
            <button type="submit" class="btn btn-primary flex-fill"><i class="fa-solid fa-magnifying-glass me-1"></i> Filter</button>
            <?php if ($activeFilters): ?>
                <a href="<?= e(url($listPath)) ?>" class="btn btn-outline-secondary" title="Clear filters" aria-label="Clear filters">
                    <i class="fa-solid fa-xmark"></i>
                </a>
            <?php endif; ?>
        </div>
    </div>
</form>
