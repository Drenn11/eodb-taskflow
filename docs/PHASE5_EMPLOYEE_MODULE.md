# Phase 5 – Employee Dashboard, Task Updating, Task Completion, and Profile

## 1. What Phase 5 adds

| Feature | Files |
|---|---|
| Employee dashboard | `employee/dashboard.php` |
| My Tasks (own tasks only) with search and filters | `employee/my_tasks.php` |
| Task details (own tasks only) | `employee/task_details.php` (uses `includes/task_details_view.php`) |
| Update status, progress, remarks; mark as completed | `employee/update_task.php`, `assets/js/update_task.js` |
| Task history | `employee/history.php` |
| My Profile for all roles (email, photo, password) | `profile/index.php`, `assets/images/profiles/.htaccess` |
| Ownership check | `get_own_task()` in `includes/task_functions.php` |

The temporary Phase 2 dashboard (`includes/temp_dashboard.php`) was removed. All three roles now have their real dashboards.

## 2. Employee dashboard

| Card | Meaning |
|---|---|
| My Total Tasks | all tasks assigned to the employee |
| Pending / In Progress / Completed / Overdue | counted with the Algorithm 3 rule |
| Completion Rate | Completed ÷ Total × 100 (Algorithm 4) |

Each card opens My Tasks with that status filter. The page also shows the **tasks to work on** (overdue first, then the nearest deadline), **My Productivity** (completion rate, on-time completion rate, completed this month), and **recent updates** on the employee's tasks.

The completion rate is for monitoring and reporting only, not a disciplinary score.

## 3. Updating a task

The update form has three linked inputs: **status buttons** (Pending, In Progress, Completed), a **progress slider and box** (0–100%), and **remarks**.

| Rule (checked on the server) | Message when broken |
|---|---|
| Pending must be 0%, In Progress 1–99%, Completed 100% | "A Pending task must have 0% progress." etc. |
| Progress is a whole number from 0 to 100 | "Progress must be a whole number from 0 to 100." |
| Something must change (progress or a remark) | "Nothing changed." |
| Remarks are required when **completing** | "Please describe what was accomplished…" |
| Remarks are required when **lowering** progress | "Please explain why the progress is lower…" |
| The task must not have changed since the form was opened | "This task was updated elsewhere…" |
| Only the employee's own, unfinished task can be updated | "not found in your assigned tasks" / "can no longer be updated" |

The saved status always comes from **Algorithm 3** (`compute_task_status()`). If the deadline has passed, the task stays **Overdue** until it reaches 100%, and the new progress is still saved. The form shows a warning and a live "Status after saving" preview.

**What one update does** (in a single transaction):

1. Updates `tasks.progress`, `tasks.status`, `tasks.remarks`; sets `completed_at` when the task reaches 100%.
2. Adds a `task_updates` history row (previous → new status and progress, remarks).
3. Notifies the supervisor who assigned the task: **Task Updated** (`task_updated`) or **Task Completed** (`task_completed`, noting if it was after the deadline).
4. Records `update_task` or `complete_task` in the activity log.

Completed tasks are locked for the employee, as they are for the supervisor.

## 4. Privacy between employees

Every employee page filters by `assigned_to = logged-in user`. Opening another employee's task by changing the `id` in the address bar shows "not found in your assigned tasks", reveals nothing about the task, and is logged as `unauthorized_access`.

## 5. Task history

`employee/history.php` lists every `task_updates` row for the employee's tasks: created, progress updates, reassignment, deadline changes, and automatic overdue entries. Columns: Date & Time, Task, Updated By, Status (previous → new), Progress (previous → new), Remarks. It can be searched by task code or title and filtered by date range.

## 6. My Profile (all roles)

| Action | Rules |
|---|---|
| View account details | Name, Employee ID, role, department, and position are read-only (managed by the Administrator) |
| Change email | Valid format, not used by another account; saved in lower case |
| Upload photo | JPG, PNG, or WEBP; up to 2 MB; 32–4000 px. The **real file content** is checked (fileinfo + image size), not the file name. Saved under a random server-chosen name, e.g. `u3_9f2c…​.png`. The old photo file is deleted |
| Remove photo | Deletes the database value and the file |
| Change password | Current password required; same strength policy as Add Employee; must differ from the current one. Afterwards the session ID is renewed and "Remember me" logins on other devices are signed out |

`assets/images/profiles/.htaccess` blocks scripts (`.php`, `.phtml`, …), HTML/SVG files, and folder listing in the upload folder. A PHP file renamed to `.png` is rejected because its content is not an image.

Each change is logged as `update_profile`.

## 7. Phase 5 test checklist

| # | Test | Expected result |
|---|---|---|
| 1 | Log in as `employee` | Dashboard: 6 cards (total 4, completion rate 25%), tasks to work on, My Productivity, recent updates |
| 2 | My Tasks | Only TASK-2026-0001, 0002, 0003, 0012 |
| 3 | Change the address to `employee/task_details.php?id=6` | "not found in your assigned tasks" |
| 4 | Update 0002: choose Pending while progress is 50% | Error: Pending must be 0% |
| 5 | Update 0002 to 75% with a remark | Saved; history shows 60% → 75%; supervisor gets "Task Updated" |
| 6 | Update overdue 0003 to 60% | Warning shown; status stays Overdue; progress saved as 60% |
| 7 | Mark 0012 as Completed without remarks | Error: describe what was accomplished |
| 8 | Mark 0012 as Completed with a remark | Completed, date completed set; supervisor gets "Task Completed"; completion rate 50% |
| 9 | Open Update for 0012 again | "can no longer be updated" |
| 10 | Task History | Shows the updates above, newest first |
| 11 | Profile → upload a JPG/PNG photo | Photo appears on the profile and in the top bar |
| 12 | Profile → upload a `.php` file renamed to `.png` | "Only JPG, PNG, or WEBP images are allowed." |
| 13 | Profile → change password with a wrong current password | Error |
| 14 | Profile → change password correctly, log out, log in with the new password | Works |
| 15 | Log in as supervisor and open `employee/my_tasks.php` | 403 Access Denied |

Note: tests 5–8 and 14 change the sample data. Re-import `database/eodb_taskflow.sql` and run `database/setup.php` to reset the demo data before the defense.
