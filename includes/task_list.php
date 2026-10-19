<?php
/**
 * EODB-TaskFlow - Task monitoring list (shared by supervisor/tasks.php and admin/tasks.php)
 *
 * Search: task code, title, description, employee name
 * Filters: status (incl. "all unfinished"), priority, employee, department,
 *          deadline date range, "only tasks I assigned" (supervisor)
 * The status filter uses Algorithm 3 so overdue tasks are always found.
 *
 * Partials: includes/task_filter_bar.php and includes/task_table.php
 */

declare(strict_types=1);

require_once __DIR__ . '/task_functions.php';

/** Reads and validates the list filters from the query string. */
function read_task_filters(bool $allowMine): array
{
    $filters = [
        'q'          => get_str('q', 100),
        'status'     => get_str('status', 20),
        'priority'   => get_str('priority', 10),
        'employee'   => get_int('employee'),
        'department' => get_int('department'),
        'from'       => get_str('from', 10),
        'to'         => get_str('to', 10),
        'mine'       => $allowMine && get_str('mine', 1) === '1',
    ];

    if (!in_array($filters['status'], TASK_STATUSES, true) && $filters['status'] !== 'open') {
        $filters['status'] = '';
    }
    if (!in_array($filters['priority'], TASK_PRIORITIES, true)) {
        $filters['priority'] = '';
    }
    foreach (['from', 'to'] as $key) {
        if ($filters[$key] !== '' && !is_valid_date($filters[$key])) {
            $filters[$key] = '';
        }
    }
    return $filters;
}

/** Non-empty filters as URL parameters (for pagination and return links). */
function task_filter_query(array $filters): array
{
    return array_filter([
        'q'          => $filters['q'],
        'status'     => $filters['status'],
        'priority'   => $filters['priority'],
        'employee'   => $filters['employee'] ?: '',
        'department' => $filters['department'] ?: '',
        'from'       => $filters['from'],
        'to'         => $filters['to'],
        'mine'       => $filters['mine'] ? '1' : '',
    ], fn ($v) => $v !== '');
}

/**
 * One page of tasks matching the filters.
 *
 * @return array{tasks: array, pagination: array}
 */
function fetch_tasks_page(array $filters, int $page): array
{
    $statusExpr = effective_status_sql('t');
    $where  = [];
    $params = [];

    if ($filters['q'] !== '') {
        $where[] = '(t.task_code LIKE ? OR t.title LIKE ? OR t.description LIKE ? OR e.full_name LIKE ?)';
        $term = like_term($filters['q']);
        array_push($params, $term, $term, $term, $term);
    }
    if ($filters['status'] === 'open') {
        $where[] = "{$statusExpr} <> 'Completed'";
    } elseif ($filters['status'] !== '') {
        $where[]  = "{$statusExpr} = ?";
        $params[] = $filters['status'];
    }
    if ($filters['priority'] !== '') {
        $where[]  = 't.priority = ?';
        $params[] = $filters['priority'];
    }
    if ($filters['employee'] > 0) {
        $where[]  = 't.assigned_to = ?';
        $params[] = $filters['employee'];
    }
    if ($filters['department'] > 0) {
        $where[]  = 'e.department_id = ?';
        $params[] = $filters['department'];
    }
    if ($filters['from'] !== '') {
        $where[]  = 't.deadline >= ?';
        $params[] = $filters['from'];
    }
    if ($filters['to'] !== '') {
        $where[]  = 't.deadline <= ?';
        $params[] = $filters['to'];
    }
    if ($filters['mine']) {
        $where[]  = 't.assigned_by = ?';
        $params[] = current_user_id();
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $fromSql  = 'FROM tasks t
                 JOIN users e ON e.id = t.assigned_to
                 JOIN users s ON s.id = t.assigned_by
                 LEFT JOIN departments d ON d.id = e.department_id';

    $countStmt = db()->prepare("SELECT COUNT(*) {$fromSql} {$whereSql}");
    $countStmt->execute($params);
    $pagination = paginate((int) $countStmt->fetchColumn(), records_per_page(), $page);

    // Unfinished first (overdue, then nearest deadline), completed last.
    // LIMIT/OFFSET are integers from paginate(), never user text.
    $listStmt = db()->prepare(
        "SELECT t.id, t.task_code, t.title, t.priority, t.deadline, t.progress, t.created_at, t.updated_at,
                {$statusExpr} AS status, e.full_name AS employee_name, d.name AS department,
                s.full_name AS supervisor_name
           {$fromSql}
           {$whereSql}
          ORDER BY FIELD({$statusExpr}, 'Overdue', 'In Progress', 'Pending', 'Completed'),
                   t.deadline, FIELD(t.priority, 'Urgent', 'High', 'Medium', 'Low'), t.id DESC
          LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}"
    );
    $listStmt->execute($params);

    return ['tasks' => $listStmt->fetchAll(), 'pagination' => $pagination];
}

/** Employees for the filter dropdown (inactive ones last, so old tasks can still be found). */
function employee_filter_options(): array
{
    return db()->query(
        "SELECT id, full_name, status FROM users WHERE role = 'employee' ORDER BY status = 'inactive', full_name"
    )->fetchAll();
}
