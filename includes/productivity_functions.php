<?php
/**
 * EODB-TaskFlow - Productivity computation (Algorithm 4)
 *
 *   Completion Rate = Completed Tasks / Total Tasks x 100
 *
 * Extra monitoring figures:
 *   On-time Rate    = Completed on or before the deadline / Completed x 100
 *   Average Progress of unfinished tasks
 *
 * These figures are for monitoring and reporting only. They are NOT an
 * automatic disciplinary score.
 *
 * Used by supervisor/employees.php, supervisor/employee_details.php, and the
 * reports (Phase 8).
 */

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

const PRODUCTIVITY_SORTS = [
    'name'      => 'u.full_name ASC',
    'rate_desc' => 'completion_ratio DESC, u.full_name ASC',
    'rate_asc'  => 'completion_ratio ASC, u.full_name ASC',
    'overdue'   => 'overdue DESC, u.full_name ASC',
    'total'     => 'total DESC, u.full_name ASC',
];

/** Completion rate in percent, rounded to 1 decimal (0 when there are no tasks). */
function completion_rate(int $completed, int $total): float
{
    return $total > 0 ? round($completed / $total * 100, 1) : 0.0;
}

/**
 * Productivity per employee.
 *
 * @param array $options
 *   q            search name / Employee ID / position
 *   department   department id (0 = all)
 *   status       'active' | 'inactive' | '' (account status)
 *   employee     a single employee id (0 = all)
 *   from, to     'Y-m-d': only tasks CREATED in this period ('' = no limit)
 *   sort         a key of PRODUCTIVITY_SORTS
 * @return list<array> one row per employee, including employees with no tasks
 */
function employee_productivity(array $options = []): array
{
    $statusExpr = effective_status_sql('t');
    $joinConditions = ['t.assigned_to = u.id'];
    $joinParams = [];
    $where  = ["u.role = 'employee'"];
    $params = [];

    // Period filter goes in the JOIN so employees without tasks still appear
    if (($options['from'] ?? '') !== '') {
        $joinConditions[] = 't.created_at >= ?';
        $joinParams[] = $options['from'] . ' 00:00:00';
    }
    if (($options['to'] ?? '') !== '') {
        $joinConditions[] = 't.created_at <= ?';
        $joinParams[] = $options['to'] . ' 23:59:59';
    }

    if (($options['q'] ?? '') !== '') {
        $where[] = '(u.full_name LIKE ? OR u.employee_id LIKE ? OR u.position LIKE ?)';
        $term = like_term($options['q']);
        array_push($params, $term, $term, $term);
    }
    if (($options['department'] ?? 0) > 0) {
        $where[]  = 'u.department_id = ?';
        $params[] = $options['department'];
    }
    if (in_array($options['status'] ?? '', ['active', 'inactive'], true)) {
        $where[]  = 'u.status = ?';
        $params[] = $options['status'];
    }
    if (($options['employee'] ?? 0) > 0) {
        $where[]  = 'u.id = ?';
        $params[] = $options['employee'];
    }

    $orderBy = PRODUCTIVITY_SORTS[$options['sort'] ?? 'name'] ?? PRODUCTIVITY_SORTS['name'];

    $sql = "SELECT u.id, u.employee_id, u.full_name, u.position, u.status AS account_status,
                   d.name AS department,
                   COUNT(t.id) AS total,
                   COALESCE(SUM({$statusExpr} = 'Completed'), 0)   AS completed,
                   COALESCE(SUM({$statusExpr} = 'In Progress'), 0) AS in_progress,
                   COALESCE(SUM({$statusExpr} = 'Pending'), 0)     AS pending,
                   COALESCE(SUM({$statusExpr} = 'Overdue'), 0)     AS overdue,
                   COALESCE(SUM(t.status = 'Completed' AND DATE(t.completed_at) <= t.deadline), 0) AS on_time,
                   AVG(CASE WHEN t.status <> 'Completed' THEN t.progress END) AS avg_open_progress,
                   COALESCE(SUM({$statusExpr} = 'Completed') / NULLIF(COUNT(t.id), 0), 0) AS completion_ratio,
                   (SELECT MAX(tu.updated_at) FROM task_updates tu WHERE tu.user_id = u.id) AS last_update
              FROM users u
              LEFT JOIN departments d ON d.id = u.department_id
              LEFT JOIN tasks t ON " . implode(' AND ', $joinConditions) . '
             WHERE ' . implode(' AND ', $where) . "
             GROUP BY u.id, u.employee_id, u.full_name, u.position, u.status, d.name
             ORDER BY {$orderBy}";

    $stmt = db()->prepare($sql);
    $stmt->execute(array_merge($joinParams, $params));

    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        foreach (['total', 'completed', 'in_progress', 'pending', 'overdue', 'on_time'] as $key) {
            $row[$key] = (int) $row[$key];
        }
        $row['completion_rate'] = completion_rate($row['completed'], $row['total']);
        $row['on_time_rate'] = completion_rate($row['on_time'], $row['completed']);
        $row['avg_open_progress'] = $row['avg_open_progress'] === null ? null : round((float) $row['avg_open_progress'], 1);
        $rows[] = $row;
    }
    return $rows;
}

/** Totals across all rows returned by employee_productivity(). */
function productivity_totals(array $rows): array
{
    $totals = ['total' => 0, 'completed' => 0, 'in_progress' => 0, 'pending' => 0, 'overdue' => 0, 'on_time' => 0];
    foreach ($rows as $row) {
        foreach ($totals as $key => $value) {
            $totals[$key] += $row[$key];
        }
    }
    $totals['completion_rate'] = completion_rate($totals['completed'], $totals['total']);
    $totals['on_time_rate'] = completion_rate($totals['on_time'], $totals['completed']);
    return $totals;
}
