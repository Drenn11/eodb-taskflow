<?php
/**
 * EODB-TaskFlow - Reports (shared by the report pages, CSV export, and print view)
 *
 * Report types:
 *   tasks           Task Report
 *   productivity    Employee Productivity Report (Algorithm 4)
 *   accomplishment  Accomplishment Report (completed tasks in a date range)
 *
 * Every report is built from the same validated filters, so the screen, the
 * CSV file, and the printed report always show the same records.
 */

declare(strict_types=1);

require_once __DIR__ . '/task_functions.php';
require_once __DIR__ . '/productivity_functions.php';
require_once __DIR__ . '/user_functions.php';

const REPORT_TYPES = [
    'tasks'          => ['Task Report',                   'reports/task_report.php'],
    'productivity'   => ['Employee Productivity Report',  'reports/productivity_report.php'],
    'accomplishment' => ['Accomplishment Report',         'reports/accomplishment_report.php'],
];

const REPORT_MAX_ROWS = 2000;

const TASK_DATE_BASIS = [
    'created'   => 'Date created',
    'deadline'  => 'Deadline',
    'completed' => 'Date completed',
];

// =============================================================================
// Filters
// =============================================================================

/** Reads and validates the filters for a report type from the query string. */
function read_report_filters(string $type): array
{
    $filters = [
        'type'       => $type,
        'q'          => get_str('q', 100),
        'employee'   => get_int('employee'),
        'department' => get_int('department'),
        'from'       => get_str('from', 10),
        'to'         => get_str('to', 10),
        'status'     => get_str('status', 20),
        'priority'   => get_str('priority', 10),
        'basis'      => get_str('basis', 10),
        'sort'       => get_str('sort', 20),
    ];

    foreach (['from', 'to'] as $key) {
        if ($filters[$key] !== '' && !is_valid_date($filters[$key])) {
            $filters[$key] = '';
        }
    }

    // The accomplishment report always needs a period: default = this month to date
    if ($type === 'accomplishment') {
        if ($filters['from'] === '') {
            $filters['from'] = date('Y-m-01');
        }
        if ($filters['to'] === '') {
            $filters['to'] = date('Y-m-d');
        }
    }

    if ($filters['from'] !== '' && $filters['to'] !== '' && $filters['from'] > $filters['to']) {
        [$filters['from'], $filters['to']] = [$filters['to'], $filters['from']];
    }

    if ($type === 'productivity') {
        $filters['status'] = in_array($filters['status'], ['active', 'inactive', 'all'], true) ? $filters['status'] : 'active';
        $filters['sort'] = isset(PRODUCTIVITY_SORTS[$filters['sort']]) ? $filters['sort'] : 'name';
    } else {
        if (!in_array($filters['status'], TASK_STATUSES, true) && $filters['status'] !== 'open') {
            $filters['status'] = '';
        }
        $filters['sort'] = '';
    }
    if ($type === 'accomplishment') {
        $filters['status'] = '';
    }
    if (!in_array($filters['priority'], TASK_PRIORITIES, true) || $type === 'productivity') {
        $filters['priority'] = '';
    }
    $filters['basis'] = $type === 'tasks' && isset(TASK_DATE_BASIS[$filters['basis']]) ? $filters['basis'] : 'created';

    return $filters;
}

/** Filters as URL parameters (for links to print / CSV / paging). */
function report_filter_query(array $filters): array
{
    $query = [
        'q'          => $filters['q'],
        'employee'   => $filters['employee'] ?: '',
        'department' => $filters['department'] ?: '',
        'from'       => $filters['from'],
        'to'         => $filters['to'],
        'status'     => $filters['status'],
        'priority'   => $filters['priority'],
        'basis'      => $filters['type'] === 'tasks' && $filters['basis'] !== 'created' ? $filters['basis'] : '',
        'sort'       => $filters['type'] === 'productivity' && $filters['sort'] !== 'name' ? $filters['sort'] : '',
    ];
    if ($filters['type'] === 'productivity' && $filters['status'] === 'active') {
        $query['status'] = '';
    }
    return array_filter($query, fn ($v) => $v !== '');
}

/**
 * Human-readable description of the filters, for report headers.
 *
 * @return array<string,string> label => value
 */
function report_filter_summary(array $filters): array
{
    $summary = [];

    if ($filters['from'] !== '' || $filters['to'] !== '') {
        $range = ($filters['from'] !== '' ? format_date($filters['from'], 'F d, Y') : 'Beginning')
            . ' to ' . ($filters['to'] !== '' ? format_date($filters['to'], 'F d, Y') : 'Present');
        $basis = match ($filters['type']) {
            'tasks'          => ' (' . mb_strtolower(TASK_DATE_BASIS[$filters['basis']]) . ')',
            'productivity'   => ' (tasks created)',
            'accomplishment' => ' (date completed)',
            default          => '',
        };
        $summary['Date range'] = $range . $basis;
    } else {
        $summary['Date range'] = 'All dates';
    }

    if ($filters['employee'] > 0) {
        $stmt = db()->prepare('SELECT full_name, employee_id FROM users WHERE id = ?');
        $stmt->execute([$filters['employee']]);
        $emp = $stmt->fetch();
        $summary['Employee'] = $emp ? "{$emp['full_name']} ({$emp['employee_id']})" : '—';
    } else {
        $summary['Employee'] = 'All employees';
    }

    if ($filters['department'] > 0) {
        $stmt = db()->prepare('SELECT name FROM departments WHERE id = ?');
        $stmt->execute([$filters['department']]);
        $summary['Department'] = (string) ($stmt->fetchColumn() ?: '—');
    } else {
        $summary['Department'] = 'All departments';
    }

    if ($filters['type'] === 'tasks') {
        $summary['Status'] = match ($filters['status']) {
            ''      => 'All statuses',
            'open'  => 'All unfinished',
            default => $filters['status'],
        };
    }
    if ($filters['type'] === 'productivity') {
        $summary['Employees'] = ['active' => 'Active only', 'inactive' => 'Inactive only', 'all' => 'Active and inactive'][$filters['status']];
    }
    if ($filters['priority'] !== '') {
        $summary['Priority'] = $filters['priority'];
    }
    if ($filters['q'] !== '') {
        $summary['Search'] = '"' . $filters['q'] . '"';
    }
    return $summary;
}

// =============================================================================
// Data
// =============================================================================

/** Shared WHERE conditions for task-based reports. */
function report_task_conditions(array $filters, string $dateColumn): array
{
    $statusExpr = effective_status_sql('t');
    $where  = [];
    $params = [];

    if ($filters['q'] !== '') {
        $where[] = '(t.task_code LIKE ? OR t.title LIKE ? OR e.full_name LIKE ?)';
        $term = like_term($filters['q']);
        array_push($params, $term, $term, $term);
    }
    if ($filters['employee'] > 0) {
        $where[]  = 't.assigned_to = ?';
        $params[] = $filters['employee'];
    }
    if ($filters['department'] > 0) {
        $where[]  = 'e.department_id = ?';
        $params[] = $filters['department'];
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
    if ($filters['from'] !== '') {
        $where[]  = "{$dateColumn} >= ?";
        $params[] = $filters['from'] . ($dateColumn === 't.deadline' ? '' : ' 00:00:00');
    }
    if ($filters['to'] !== '') {
        $where[]  = "{$dateColumn} <= ?";
        $params[] = $filters['to'] . ($dateColumn === 't.deadline' ? '' : ' 23:59:59');
    }
    return [$where, $params];
}

/** Task Report rows. */
function fetch_task_report(array $filters): array
{
    $dateColumn = ['created' => 't.created_at', 'deadline' => 't.deadline', 'completed' => 't.completed_at'][$filters['basis']];
    [$where, $params] = report_task_conditions($filters, $dateColumn);
    $statusExpr = effective_status_sql('t');

    $stmt = db()->prepare(
        "SELECT t.id, t.task_code, t.title, t.priority, t.progress, t.deadline, t.created_at, t.completed_at,
                {$statusExpr} AS status, e.full_name AS employee_name, e.employee_id AS employee_code,
                d.name AS department, s.full_name AS supervisor_name
           FROM tasks t
           JOIN users e ON e.id = t.assigned_to
           JOIN users s ON s.id = t.assigned_by
           LEFT JOIN departments d ON d.id = e.department_id
           " . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . '
          ORDER BY t.created_at DESC, t.id DESC
          LIMIT ' . REPORT_MAX_ROWS
    );
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/** Accomplishment Report rows: tasks completed within the date range. */
function fetch_accomplishment_report(array $filters): array
{
    [$where, $params] = report_task_conditions($filters, 't.completed_at');
    $where[] = "t.status = 'Completed'";

    $stmt = db()->prepare(
        'SELECT t.id, t.task_code, t.title, t.priority, t.deadline, t.created_at, t.completed_at, t.remarks,
                DATEDIFF(DATE(t.completed_at), t.deadline) AS days_after_deadline,
                e.full_name AS employee_name, e.employee_id AS employee_code, e.position AS employee_position,
                d.name AS department, s.full_name AS supervisor_name
           FROM tasks t
           JOIN users e ON e.id = t.assigned_to
           JOIN users s ON s.id = t.assigned_by
           LEFT JOIN departments d ON d.id = e.department_id
          WHERE ' . implode(' AND ', $where) . '
          ORDER BY e.full_name, t.completed_at
          LIMIT ' . REPORT_MAX_ROWS
    );
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/** Employee Productivity Report rows (Algorithm 4). */
function fetch_productivity_report(array $filters): array
{
    return employee_productivity([
        'q'          => $filters['q'],
        'department' => $filters['department'],
        'employee'   => $filters['employee'],
        'status'     => $filters['status'] === 'all' ? '' : $filters['status'],
        'from'       => $filters['from'],
        'to'         => $filters['to'],
        'sort'       => $filters['sort'],
    ]);
}

/** Counts per status for a list of task rows (for summaries and charts). */
function count_by_status(array $rows): array
{
    $counts = array_fill_keys(TASK_STATUSES, 0);
    foreach ($rows as $row) {
        if (isset($counts[$row['status']])) {
            $counts[$row['status']]++;
        }
    }
    return $counts;
}

// =============================================================================
// Table layout shared by CSV export and print view
// =============================================================================

/**
 * Column headers and plain-text cell values for a report.
 *
 * @return array{headers: list<string>, rows: list<list<string>>, totals: list<string>|null}
 */
function report_table(string $type, array $rows): array
{
    if ($type === 'tasks') {
        return [
            'headers' => ['Task Code', 'Task', 'Employee', 'Department', 'Priority', 'Status', 'Progress', 'Deadline', 'Date Created', 'Date Completed'],
            'rows' => array_map(fn ($r) => [
                $r['task_code'], $r['title'], $r['employee_name'], (string) ($r['department'] ?? ''), $r['priority'],
                $r['status'], $r['progress'] . '%', format_date($r['deadline']), format_date($r['created_at']),
                $r['completed_at'] ? format_date($r['completed_at']) : '—',
            ], $rows),
            'totals' => null,
        ];
    }

    if ($type === 'accomplishment') {
        return [
            'headers' => ['Task Code', 'Task', 'Employee', 'Department', 'Priority', 'Deadline', 'Date Completed', 'Timeliness', 'Remarks'],
            'rows' => array_map(fn ($r) => [
                $r['task_code'], $r['title'], $r['employee_name'], (string) ($r['department'] ?? ''), $r['priority'],
                format_date($r['deadline']), format_date($r['completed_at']), timeliness_label((int) $r['days_after_deadline']),
                (string) ($r['remarks'] ?? ''),
            ], $rows),
            'totals' => null,
        ];
    }

    // productivity
    $totals = productivity_totals($rows);
    return [
        'headers' => ['Employee', 'Employee ID', 'Department', 'Total Tasks', 'Completed', 'Pending', 'In Progress', 'Overdue', 'Completion Rate', 'On-time Rate'],
        'rows' => array_map(fn ($r) => [
            $r['full_name'], $r['employee_id'], (string) ($r['department'] ?? ''), (string) $r['total'], (string) $r['completed'],
            (string) $r['pending'], (string) $r['in_progress'], (string) $r['overdue'], $r['completion_rate'] . '%',
            $r['completed'] > 0 ? $r['on_time_rate'] . '%' : '—',
        ], $rows),
        'totals' => [
            'TOTAL', '', '', (string) $totals['total'], (string) $totals['completed'], (string) $totals['pending'],
            (string) $totals['in_progress'], (string) $totals['overdue'], $totals['completion_rate'] . '%',
            $totals['completed'] > 0 ? $totals['on_time_rate'] . '%' : '—',
        ],
    ];
}

/** "On time", "2 days early", "3 days late". */
function timeliness_label(int $daysAfterDeadline): string
{
    return match (true) {
        $daysAfterDeadline > 0  => $daysAfterDeadline . ' day' . ($daysAfterDeadline === 1 ? '' : 's') . ' late',
        $daysAfterDeadline < 0  => abs($daysAfterDeadline) . ' day' . ($daysAfterDeadline === -1 ? '' : 's') . ' early',
        default                 => 'On the deadline',
    };
}

/** Records that a report was printed or exported. */
function log_report(string $type, string $format, int $rowCount): void
{
    log_activity(
        current_user_id(),
        'generate_report',
        'Generated ' . REPORT_TYPES[$type][0] . " ({$format}, {$rowCount} record" . ($rowCount === 1 ? '' : 's') . ').'
    );
}

/** Chart colours: the same status colours as the badges and bars (style.css design tokens). */
const STATUS_COLORS = [
    'Pending'     => '#6b778a',
    'In Progress' => '#2f65a0',
    'Completed'   => '#287a55',
    'Overdue'     => '#b93838',
];
const PRIORITY_COLORS = [
    'Low'    => '#8a94a6',
    'Medium' => '#3e5068',
    'High'   => '#946010',
    'Urgent' => '#b93838',
];

/** Task details page for the logged-in role (reports are for admin and supervisor). */
function report_task_link(int $taskId): string
{
    return url((has_role('admin') ? 'admin' : 'supervisor') . '/task_details.php?id=' . $taskId);
}

/** JSON for embedding chart data safely inside a <script type="application/json"> tag. */
function chart_json(array $data): string
{
    return json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
}
