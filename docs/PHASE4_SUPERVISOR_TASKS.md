# Phase 4 – Supervisor Dashboard, Task Creation, and Task Assignment

## 1. What Phase 4 adds

| Feature | Files |
|---|---|
| Supervisor dashboard | `supervisor/dashboard.php` |
| Task monitoring list with search and filters | `supervisor/tasks.php` |
| Create and assign task (Algorithm 2) | `supervisor/create_task.php` |
| Edit / reassign task | `supervisor/edit_task.php` |
| Task details with progress bar and history timeline | `supervisor/task_details.php`, `includes/task_details_view.php` |
| Delete task | `supervisor/delete_task.php` |
| Task status rule (Algorithm 3), task code, validation, history | `includes/task_functions.php` |
| Creating notifications (Algorithm 5, first part) | `includes/notification_functions.php` |
| Shared task form | `includes/task_form.php`, `assets/js/task_form.js` |

## 2. Algorithm 2 – Task Assignment

Implemented in `supervisor/create_task.php`:

| Step | Implementation |
|---|---|
| 1. Supervisor opens Create Task | Form with title, description, employee, priority, deadline, initial status, remarks |
| 2. Supervisor enters task details | `read_task_form()` reads and trims the input |
| 3. System validates the data | `validate_task_form()`: title ≥ 5 characters; employee must be an **active** employee; priority from the list; deadline a real date, not in the past, within 2 years; In Progress requires 1–99% progress |
| 4. System saves the task | `INSERT INTO tasks` with a prepared statement |
| 5. System generates a task code | `generate_task_code()` → `TASK-YYYY-NNNN`, numbered per year |
| 6. System assigns the task | `assigned_to` = selected employee, `assigned_by` = logged-in supervisor; a first `task_updates` history row is written |
| 7. System creates a notification | `create_notification()` → "New task assigned to you: TASK-… – title" |
| 8. System records the activity | `log_activity('create_task', …)` |

Steps 4–8 run in one **database transaction**: if any step fails, nothing is saved. The task code query uses `SELECT … FOR UPDATE`, so two supervisors saving at the same moment cannot get the same code.

## 3. Algorithm 3 – Task Status

`compute_task_status($progress, $deadline)` in `includes/task_functions.php`:

```
IF progress = 100               THEN status = Completed
ELSE IF current date > deadline THEN status = Overdue      (progress is kept)
ELSE IF progress > 0            THEN status = In Progress
ELSE                                 status = Pending
```

It is used:

- when a task is **created**: the initial status must agree with the progress (Pending = 0%, In Progress = 1–99%);
- when a task is **edited**: changing the deadline recalculates the status. For example, extending the deadline of an Overdue task at 30% makes it In Progress again, still at 30%;
- when a task is **displayed** (`get_task()`) and in **filters and counts** (`effective_status_sql()`), so a task that has just passed its deadline is shown as Overdue right away.

Phase 6 adds the automatic update of the *stored* status, the overdue history entry, and overdue notifications.

## 4. Business rules

| Rule | Reason |
|---|---|
| Tasks can only be assigned to **active employees** | Inactive accounts cannot log in to work on them |
| The employee list shows each person's **open task count** | Helps the supervisor balance the workload |
| **Completed tasks are locked** (no edit, no delete) | They are accomplishment records used in reports |
| Deleting a task removes its history (`ON DELETE CASCADE`); its notifications are kept with `task_id = NULL` | Keeps the database consistent while keeping what users were told |
| Reassigning notifies **both** employees and is logged as `assign_task` | Clear accountability |
| Other edits notify the employee (`task_updated`) and are logged as `update_task` | The employee always knows what changed |
| Delete is a POST with CSRF token and a confirmation prompt | Prevents accidental or forged deletion |

## 5. Supervisor dashboard

| Section | Content |
|---|---|
| Cards (tasks assigned by this supervisor) | Assigned Tasks, Pending, In Progress, Completed, Overdue. Each card opens the filtered task list |
| Needs Attention | Overdue tasks and tasks due within the reminder period (setting `deadline_reminder_days`, default 2) |
| Recently Completed (for Review) | The latest 5 completed tasks, marked *On time* or *Late* |
| Employee Productivity | Per active employee: total, completed, in progress, pending, overdue, completion rate (Completed ÷ Total × 100), with an **Assign** shortcut |

Completion rates are for monitoring and reporting only, not a disciplinary score.

## 6. Task monitoring list

Columns: Task Code · Task · Employee · Priority · Deadline · Status · Progress · Action (View, Edit, Delete).

| Search / filter | Matches |
|---|---|
| Search | task code, task title, employee name |
| Status | All, All unfinished, Pending, In Progress, Completed, Overdue |
| Priority | Low, Medium, High, Urgent |
| Employee, Department | the assigned employee and their department |
| Deadline from / to | deadline date range |
| Only tasks I assigned | `assigned_by` = current supervisor |

Ordering: Overdue → In Progress → Pending → Completed, then nearest deadline, then highest priority.

The "Update" action from the proposal (updating progress) belongs to the employee in Phase 5.

## 7. Phase 4 test checklist

| # | Test | Expected result |
|---|---|---|
| 1 | Log in as supervisor | Dashboard shows 5 cards, Needs Attention, Recently Completed, Employee Productivity |
| 2 | Click the Overdue card | Task list filtered to your overdue tasks |
| 3 | Tasks → Status = Overdue | Tasks past their deadline and not completed, even if not yet marked overdue |
| 4 | Search `dengue` | Only TASK-2026-0004 |
| 5 | Filter Employee = Mark Anthony L. Garcia | Only TASK-2026-0006 and 0007 |
| 6 | Create Task with a 3-letter title, past deadline, In Progress at 0% | Each field shows an error; nothing is saved |
| 7 | Create a valid task for Ana P. Reyes | New code TASK-YYYY-0013; details page shows history "Task created as …" |
| 8 | Log in as `areyes` | The bell shows "New Task Assigned" |
| 9 | Edit the task: change employee to Juan and extend the deadline | Both employees notified; history shows "Reassigned from … to …" |
| 10 | Edit an Overdue task and extend its deadline | Status becomes In Progress (or Pending at 0%); progress unchanged |
| 11 | Open a Completed task | No Edit/Delete buttons; "accomplishment record locked" |
| 12 | Delete the task created in step 7 | Confirmation prompt; task removed; activity log shows `delete_task` |
| 13 | Type `<script>alert(1)</script>` in a description | Shown as text, never executed |
| 14 | Log in as employee and open `supervisor/create_task.php` | 403 Access Denied |
| 15 | Log in as admin and open `supervisor/create_task.php` | 403 Access Denied (only supervisors create tasks) |
