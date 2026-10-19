# Phase 6 – Task Monitoring, Progress Tracking, and Overdue Detection

## 1. What Phase 6 adds

| Feature | Files |
|---|---|
| Automatic overdue detection (stored status, history, notifications, log) | `refresh_overdue_tasks()` in `includes/task_functions.php` |
| Automatic check runner (while the system is used) | `includes/scheduler.php`, hook at the end of `includes/auth.php` |
| Optional scheduled run (Windows Task Scheduler) | `cron/run_checks.php`, `cron/.htaccess` |
| Shared task monitoring list (filters, query, table) | `includes/task_list.php`, `includes/task_filter_bar.php`, `includes/task_table.php` |
| Administrator: View All Tasks (read-only) | `admin/tasks.php`, `admin/task_details.php` |
| Supervisor: Employee Performance | `supervisor/employees.php`, `supervisor/employee_details.php` |
| Productivity computation (Algorithm 4), reused by reports in Phase 8 | `includes/productivity_functions.php` |

`supervisor/tasks.php` was rewritten to use the shared list files; it behaves exactly as in Phase 4.

## 2. Overdue detection

### Rule (Algorithm 3)

```
IF progress = 100               THEN Completed
ELSE IF current date > deadline THEN Overdue      ← progress is preserved
ELSE IF progress > 0            THEN In Progress
ELSE                                 Pending
```

### What happens when a deadline passes

`refresh_overdue_tasks()` finds every task that is **Pending or In Progress** with `deadline < today`. For each one, in a single transaction, it:

1. changes `tasks.status` to **Overdue**, keeping `progress` unchanged;
2. adds a `task_updates` row by **System (automatic)**: "Automatically marked as overdue: deadline has passed.";
3. notifies the **employee** ("Your task … is overdue") and the **supervisor who assigned it** (`task_overdue`);
4. writes an activity log entry with action `system`.

The update only succeeds if the task still has the status that was read. If two page loads run the check at the same moment, only one of them notifies.

It also repairs the opposite case: a task stored as Overdue whose deadline is no longer in the past (for example, a date corrected directly in phpMyAdmin) gets its status recalculated.

### When the check runs

XAMPP has no built-in job scheduler, so two mechanisms are provided:

| Mechanism | How |
|---|---|
| **Automatic, while the system is used** | `includes/auth.php` calls `run_scheduled_checks()` on page loads by logged-in users, at most **once every 60 seconds**. The interval is controlled by the `last_status_check` row in `settings`, claimed with one atomic `UPDATE`, so simultaneous users never run it twice. |
| **Optional, scheduled** | Windows Task Scheduler → *Create Basic Task* → daily at 7:00 AM → *Start a program*: Program `C:\xampp\php\php.exe`, Arguments `C:\xampp\htdocs\eodb-taskflow\cron\run_checks.php` |

A failure in the check is written to the PHP error log and never stops the page the user opened.

Tasks extended by the supervisor (Edit Task → new deadline) leave Overdue immediately (Phase 4).

## 3. Task monitoring list (supervisor and administrator)

| | Supervisor (`supervisor/tasks.php`) | Administrator (`admin/tasks.php`) |
|---|---|---|
| Search: task code, title, employee | ✔ | ✔ |
| Filters: status, priority, employee, department, deadline range | ✔ | ✔ |
| "Only tasks I assigned" | ✔ | — |
| Actions | View, Edit, Delete | View only |
| Details page | `supervisor/task_details.php` | `admin/task_details.php` (read-only, link to the employee) |

Each row now also shows when the task was **last updated**, which helps spot tasks nobody has worked on.

## 4. Employee performance (supervisor)

`supervisor/employees.php` lists every employee with:

| Column | Formula |
|---|---|
| Total, Completed, In Progress, Pending, Overdue | counted with the Algorithm 3 rule |
| **Completion Rate** | Completed ÷ Total × 100 (Algorithm 4) |
| On Time | completed on or before the deadline ÷ completed × 100 |
| Avg. Progress (unfinished) | average progress of tasks not yet completed |

Filters: search (name, Employee ID, position), department, account status (active / inactive / all), **tasks created in a period**, and sort order (name, highest or lowest completion rate, most overdue, most tasks). Summary cards show the totals for the filtered employees.

`supervisor/employee_details.php` shows one employee: profile, productivity summary (each count opens the filtered task list), their tasks, and their latest progress updates.

These figures are for monitoring and reporting only, not an automatic disciplinary score.

## 5. Phase 6 test checklist

| # | Test | Expected result |
|---|---|---|
| 1 | In phpMyAdmin, set a Pending/In Progress task's `deadline` to yesterday. Wait 1 minute, then open any page | The task becomes Overdue; progress unchanged |
| 2 | Open that task's details | History shows "System (automatic) … Automatically marked as overdue" |
| 3 | Log in as the assigned employee | Bell shows "Your task … is overdue" |
| 4 | Log in as the supervisor | Bell shows "… assigned to … is overdue" |
| 5 | Reload pages several times | No duplicate notifications |
| 6 | Run `C:\xampp\php\php.exe C:\xampp\htdocs\eodb-taskflow\cron\run_checks.php` | Prints the number of tasks marked overdue |
| 7 | Open `http://localhost/eodb-taskflow/cron/run_checks.php` | 403 Forbidden |
| 8 | Supervisor → Edit the overdue task and extend its deadline | Status returns to In Progress / Pending |
| 9 | Admin → Tasks | All tasks, filters work, no Edit/Delete buttons |
| 10 | Admin → open `supervisor/edit_task.php?id=2` | 403 Access Denied |
| 11 | Supervisor → Employees | Completion rate, on-time rate, average progress per employee |
| 12 | Sort by "Completion rate (lowest)" | Employees with the lowest rate first |
| 13 | Click an employee | Performance details with tasks and latest updates |
| 14 | Employee → open `supervisor/employees.php` | 403 Access Denied |
