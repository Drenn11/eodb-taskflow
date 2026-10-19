<?php
/**
 * EODB-TaskFlow - Task functions
 *
 * Algorithm 2 (Task Assignment) helpers: task code generation, validation.
 * Algorithm 3 (Task Status): compute_task_status().
 * Task history: record_task_update().
 *
 * Used by the supervisor task pages (Phase 4) and the employee task pages
 * (Phase 5).
 */

declare(strict_types=1);

require_once __DIR__ . '/notification_functions.php';

const TASK_TITLE_MAX = 200;
const TASK_DESCRIPTION_MAX = 5000;
const TASK_REMARKS_MAX = 1000;

// =============================================================================
// ALGORITHM 3 - TASK STATUS
// =============================================================================

/**
 * IF progress = 100                  THEN status = Completed
 * ELSE IF current date > deadline    THEN status = Overdue   (progress is kept)
 * ELSE IF progress > 0               THEN status = In Progress
 * ELSE                                    status = Pending
 *
 * @param string      $deadline 'Y-m-d'
 * @param string|null $today    'Y-m-d' (defaults to today, Asia/Manila)
 */
function compute_task_status(int $progress, string $deadline, ?string $today = null): string
{
    $today ??= date('Y-m-d');

    if ($progress >= 100) {
        return 'Completed';
    }
    if ($today > $deadline) {
        return 'Overdue';
    }
    if ($progress > 0) {
        return 'In Progress';
    }
    return 'Pending';
}

// =============================================================================
// OVERDUE DETECTION (Algorithm 3 applied to the stored status)
// =============================================================================

/**
 * Marks every unfinished task whose deadline has passed as Overdue.
 * For each task (in its own transaction):
 *   - status -> Overdue (progress is kept)
 *   - task history row by "System (automatic)"
 *   - notification to the employee and to the supervisor who assigned it
 *   - activity log entry (action "system")
 * The UPDATE only succeeds if the status is still the one that was read, so
 * two requests running at the same moment never notify twice.
 *
 * Also repairs the opposite case: a task stored as Overdue whose deadline is
 * no longer in the past (e.g. a date corrected directly in the database).
 *
 * @return array{overdue:int, recalculated:int}
 */
function refresh_overdue_tasks(): array
{
    $pdo = db();
    $result = ['overdue' => 0, 'recalculated' => 0];

    $late = $pdo->query(
        "SELECT t.id, t.task_code, t.title, t.status, t.progress, t.deadline,
                t.assigned_to, t.assigned_by, e.full_name AS employee_name
           FROM tasks t
           JOIN users e ON e.id = t.assigned_to
          WHERE t.status IN ('Pending', 'In Progress')
            AND t.deadline < CURDATE()"
    )->fetchAll();

    $markOverdue = $pdo->prepare(
        "UPDATE tasks SET status = 'Overdue' WHERE id = ? AND status = ? AND deadline < CURDATE()"
    );

    foreach ($late as $task) {
        try {
            $pdo->beginTransaction();
            $markOverdue->execute([$task['id'], $task['status']]);

            if ($markOverdue->rowCount() !== 1) {
                $pdo->rollBack();   // already handled by another request
                continue;
            }

            $deadline = format_date($task['deadline']);
            record_task_update(
                (int) $task['id'],
                null,
                $task['status'],
                'Overdue',
                (int) $task['progress'],
                (int) $task['progress'],
                'Automatically marked as overdue: deadline has passed.'
            );
            create_notification(
                (int) $task['assigned_to'],
                (int) $task['id'],
                'Task Overdue',
                "Your task {$task['task_code']} is overdue. The deadline was {$deadline}. Please update its progress.",
                'task_overdue'
            );
            create_notification(
                (int) $task['assigned_by'],
                (int) $task['id'],
                'Task Overdue',
                "{$task['task_code']} assigned to {$task['employee_name']} is overdue (deadline {$deadline}, progress {$task['progress']}%).",
                'task_overdue'
            );
            log_activity(null, 'system', "Automatic overdue detection marked {$task['task_code']} as Overdue.");

            $pdo->commit();
            $result['overdue']++;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[EODB-TaskFlow] Overdue detection failed for task ' . $task['id'] . ': ' . $e->getMessage());
        }
    }

    // Stored as Overdue, but the deadline is today or later
    $stale = $pdo->query(
        "SELECT id, progress, deadline FROM tasks WHERE status = 'Overdue' AND deadline >= CURDATE()"
    )->fetchAll();
    $recalculate = $pdo->prepare("UPDATE tasks SET status = ? WHERE id = ? AND status = 'Overdue'");

    foreach ($stale as $task) {
        $newStatus = compute_task_status((int) $task['progress'], $task['deadline']);
        $recalculate->execute([$newStatus, $task['id']]);
        if ($recalculate->rowCount() === 1) {
            record_task_update(
                (int) $task['id'],
                null,
                'Overdue',
                $newStatus,
                (int) $task['progress'],
                (int) $task['progress'],
                'Status recalculated: the deadline is no longer in the past.'
            );
            $result['recalculated']++;
        }
    }

    return $result;
}

// =============================================================================
// Task code
// =============================================================================

/**
 * Generates the next task code for the current year, e.g. TASK-2026-0013.
 * Must be called inside a transaction: FOR UPDATE locks the matching rows so
 * two supervisors saving at the same moment cannot receive the same code.
 */
function generate_task_code(): string
{
    $prefix = 'TASK-' . date('Y') . '-';

    $stmt = db()->prepare(
        'SELECT MAX(CAST(SUBSTRING(task_code, ?) AS UNSIGNED))
           FROM tasks
          WHERE task_code LIKE ?
          FOR UPDATE'
    );
    $stmt->execute([strlen($prefix) + 1, $prefix . '%']);
    $next = (int) $stmt->fetchColumn() + 1;

    return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
}

// =============================================================================
// Reading tasks
// =============================================================================

/** One task with employee, supervisor, and department names, or null. */
function get_task(int $taskId): ?array
{
    $stmt = db()->prepare(
        'SELECT t.*,
                e.full_name   AS employee_name,
                e.employee_id AS employee_code,
                e.position    AS employee_position,
                e.status      AS employee_status,
                d.name        AS employee_department,
                s.full_name   AS supervisor_name,
                s.position    AS supervisor_position
           FROM tasks t
           JOIN users e ON e.id = t.assigned_to
           JOIN users s ON s.id = t.assigned_by
           LEFT JOIN departments d ON d.id = e.department_id
          WHERE t.id = ?
          LIMIT 1'
    );
    $stmt->execute([$taskId]);
    $task = $stmt->fetch();

    if ($task) {
        // Show the status the rules give today, even before the stored value is refreshed
        $task['status'] = compute_task_status((int) $task['progress'], $task['deadline']);
    }
    return $task ?: null;
}

/**
 * A task that belongs to the logged-in employee, or null. Another employee's
 * task is treated as "not found" (and logged), so task IDs cannot be probed.
 */
function get_own_task(int $taskId): ?array
{
    $task = $taskId > 0 ? get_task($taskId) : null;
    if ($task !== null && (int) $task['assigned_to'] !== current_user_id()) {
        log_activity(
            current_user_id(),
            'unauthorized_access',
            "Tried to open {$task['task_code']}, which is assigned to another employee."
        );
        return null;
    }
    return $task;
}

/** Task history (task_updates), newest first. */
function get_task_history(int $taskId): array
{
    $stmt = db()->prepare(
        'SELECT tu.*, u.full_name, u.role
           FROM task_updates tu
           LEFT JOIN users u ON u.id = tu.user_id
          WHERE tu.task_id = ?
          ORDER BY tu.updated_at DESC, tu.id DESC'
    );
    $stmt->execute([$taskId]);
    return $stmt->fetchAll();
}

/** Active employees for the "Assign to" dropdown, with their open task count. */
function get_assignable_employees(): array
{
    return db()->query(
        "SELECT u.id, u.full_name, u.employee_id, u.position, d.name AS department,
                (SELECT COUNT(*) FROM tasks t WHERE t.assigned_to = u.id AND t.status <> 'Completed') AS open_tasks
           FROM users u
           LEFT JOIN departments d ON d.id = u.department_id
          WHERE u.role = 'employee' AND u.status = 'active'
          ORDER BY d.name, u.full_name"
    )->fetchAll();
}

// =============================================================================
// Task history
// =============================================================================

/** Records one change in task_updates. $userId = null means an automatic system update. */
function record_task_update(
    int $taskId,
    ?int $userId,
    ?string $previousStatus,
    string $newStatus,
    ?int $previousProgress,
    int $newProgress,
    ?string $remarks
): void {
    $stmt = db()->prepare(
        'INSERT INTO task_updates
            (task_id, user_id, previous_status, new_status, previous_progress, new_progress, remarks)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $taskId,
        $userId,
        $previousStatus,
        $newStatus,
        $previousProgress,
        $newProgress,
        $remarks !== null && $remarks !== '' ? $remarks : null,
    ]);
}

// =============================================================================
// Task form (create / edit)
// =============================================================================

function read_task_form(): array
{
    return [
        'title'            => preg_replace('/\s+/u', ' ', post_str('title', TASK_TITLE_MAX)),
        'description'      => post_str('description', TASK_DESCRIPTION_MAX),
        'assigned_to'      => post_int('assigned_to'),
        'priority'         => post_str('priority', 10),
        'deadline'         => post_str('deadline', 10),
        'initial_status'   => post_str('initial_status', 20),
        'initial_progress' => filter_var($_POST['initial_progress'] ?? '', FILTER_VALIDATE_INT),
        'remarks'          => post_str('remarks', TASK_REMARKS_MAX),
    ];
}

/** True for a real calendar date in Y-m-d format. */
function is_valid_date(string $value): bool
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
}

/**
 * Validates the task form (Algorithm 2, step 3).
 *
 * @param array|null $existing current task when editing, null when creating
 * @return array<string,string> field => error message
 */
function validate_task_form(array $data, ?array $existing): array
{
    $errors = [];
    $today = date('Y-m-d');

    if ($data['title'] === '') {
        $errors['title'] = 'Task title is required.';
    } elseif (mb_strlen($data['title']) < 5) {
        $errors['title'] = 'Task title must be at least 5 characters.';
    }

    // Assigned employee: must be an active employee account
    if ($data['assigned_to'] === 0) {
        $errors['assigned_to'] = 'Please select the employee who will do this task.';
    } else {
        $stmt = db()->prepare("SELECT status FROM users WHERE id = ? AND role = 'employee' LIMIT 1");
        $stmt->execute([$data['assigned_to']]);
        $status = $stmt->fetchColumn();
        $unchanged = $existing !== null && (int) $existing['assigned_to'] === $data['assigned_to'];
        if ($status === false) {
            $errors['assigned_to'] = 'Please select a valid employee.';
        } elseif ($status !== 'active' && !$unchanged) {
            $errors['assigned_to'] = 'Tasks can only be assigned to active employees.';
        }
    }

    if (!in_array($data['priority'], TASK_PRIORITIES, true)) {
        $errors['priority'] = 'Please select a priority.';
    }

    // Deadline: a valid date, not in the past (an unchanged old deadline is allowed when editing)
    if ($data['deadline'] === '') {
        $errors['deadline'] = 'Deadline is required.';
    } elseif (!is_valid_date($data['deadline'])) {
        $errors['deadline'] = 'Please enter a valid date.';
    } elseif ($data['deadline'] < $today && !($existing !== null && $existing['deadline'] === $data['deadline'])) {
        $errors['deadline'] = 'The deadline cannot be earlier than today.';
    } elseif ($data['deadline'] > date('Y-m-d', strtotime('+2 years'))) {
        $errors['deadline'] = 'The deadline must be within the next 2 years.';
    }

    // Initial status and progress (create only), consistent with Algorithm 3
    if ($existing === null) {
        if (!in_array($data['initial_status'], ['Pending', 'In Progress'], true)) {
            $errors['initial_status'] = 'Initial status must be Pending or In Progress.';
        } elseif ($data['initial_status'] === 'In Progress') {
            $progress = $data['initial_progress'];
            if ($progress === false || $progress < 1 || $progress > 99) {
                $errors['initial_progress'] = 'For an In Progress task, enter a progress from 1 to 99%.';
            }
        }
    }

    return $errors;
}

/** Initial progress implied by the create form (Pending = 0%). */
function initial_progress_from_form(array $data): int
{
    return $data['initial_status'] === 'In Progress' ? (int) $data['initial_progress'] : 0;
}

// =============================================================================
// Display helpers
// =============================================================================

/** Short deadline note, e.g. "Due today", "Due in 3 days", "5 days overdue". */
function deadline_note(string $deadline, string $status): string
{
    if ($status === 'Completed') {
        return '';
    }
    $days = (int) (new DateTimeImmutable(date('Y-m-d')))->diff(new DateTimeImmutable($deadline))->format('%r%a');

    return match (true) {
        $days < 0   => abs($days) . ' day' . (abs($days) === 1 ? '' : 's') . ' overdue',
        $days === 0 => 'Due today',
        $days === 1 => 'Due tomorrow',
        default     => "Due in {$days} days",
    };
}

/** Progress bar coloured by status. */
function progress_bar(int $progress, string $status, bool $showLabel = true): string
{
    $class = match ($status) {
        'Completed' => 'bar-completed',
        'Overdue'   => 'bar-overdue',
        default     => 'bar-progress',
    };
    $progress = max(0, min(100, $progress));

    return '<div class="progress-cell">'
        . ($showLabel ? '<div class="small text-muted mb-1">' . $progress . '%</div>' : '')
        . '<div class="progress" role="progressbar" aria-label="Task progress" aria-valuenow="' . $progress
        . '" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar ' . $class
        . '" style="width: ' . $progress . '%"></div></div></div>';
}
