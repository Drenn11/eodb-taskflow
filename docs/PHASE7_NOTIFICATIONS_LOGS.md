# Phase 7 – Notifications, Activity Logs, and System Settings

## 1. What Phase 7 adds

| Feature | Files |
|---|---|
| Notifications page (all roles): list, unread filter, type filter, open, mark as read, mark all as read | `notifications/index.php`, `notifications/open.php`, `notifications/mark_read.php` |
| Clickable bell dropdown with "Mark all read" | `includes/navbar.php` |
| Live unread badge (refreshes every 60 seconds) | `notifications/fetch.php`, `assets/js/app.js` |
| Approaching-deadline reminders (Algorithm 5) | `send_deadline_reminders()` in `includes/notification_functions.php`, called by `includes/scheduler.php` |
| Activity Logs page (Administrator) | `admin/activity_logs.php` |
| System Settings and departments (Administrator) | `admin/settings.php` |
| Configurable system and office name | `app_name()`, `office_name()` in `includes/functions.php` |
| Safe "return to page" links | `safe_return_path()` in `includes/functions.php` |

## 2. Algorithm 5 – Notification

| Event | Recipient | Title / message | Created by |
|---|---|---|---|
| New task assignment | Employee | "New task assigned to you: TASK-… – title" | Create Task, reassignment (Phase 4) |
| Task update by supervisor | Employee | "Your task … was updated by your supervisor" | Edit Task (Phase 4) |
| Task update by employee | Supervisor | "… updated TASK-… progress to 60%" | Update Task (Phase 5) |
| Completed task | Supervisor | "… marked TASK-… as completed" | Update Task (Phase 5) |
| Overdue task | Employee and supervisor | "Your task … is overdue" | Automatic check (Phase 6) |
| **Upcoming deadline** | Employee | "Task deadline is approaching: TASK-… is due in 2 days / tomorrow / today" | **Automatic check (Phase 7)** |

**Deadline reminders.** For each Pending or In Progress task whose deadline is within *Deadline reminder (days before)* (Settings, default 2), the assigned **active** employee gets one reminder per day. The query skips tasks that already have a reminder created today, so repeated checks never duplicate it.

## 3. Using notifications

- **Bell icon:** unread count, the latest 6 notifications (unread first), and **Mark all read**. Clicking a notification marks it as read and opens the related task on the correct page for the user's role (employee, supervisor, or admin task details).
- **Notifications page:** filters *All / Unread* and by type; per-item **Open task** and **Mark as read**; **Mark All as Read**; paging.
- **Live badge:** `app.js` asks `notifications/fetch.php` for the unread count every 60 seconds (skipped while the browser tab is hidden).

**Security**

- Users can only read, open, or mark their **own** notifications (every query includes `user_id = logged-in user`).
- Open and mark-as-read are **POST requests with a CSRF token**.
- An employee can only open a task that is currently assigned to them. If it was reassigned or deleted, they see a message instead.
- The `return` address after "mark as read" must be a page inside the system (`safe_return_path()`); outside addresses are ignored.
- `fetch.php` is a **passive request** (`EODB_PASSIVE_REQUEST`): it does not reset the inactivity timer, so an idle session still expires after the configured timeout. After the session ends it returns HTTP 401 and the page stops polling.

## 4. Activity logs

`admin/activity_logs.php`: **User, Action, Description, Date, Time, IP Address**, newest first, 20+ rows per page.

| Filter | Matches |
|---|---|
| Search | description or user name |
| User | one user |
| Action | any recorded action code |
| From / To | date range |

Recorded actions: Login, Failed Login, Logout, Create Task, Assign Task, Update Task, Complete Task, Delete Task, Add User, Edit User, Deactivate User, Activate User, Update Profile, Update Settings, Blocked Access, Security, System (automatic overdue detection, setup).

The log is **read-only**: no page can edit or delete entries.

## 5. System settings

| Setting | Allowed | Effect |
|---|---|---|
| System name | 3–60 characters | Header, sidebar, login page, footer, page titles |
| Office name | 3–100 characters | Login page, footer, sidebar, printed reports |
| Deadline reminder (days before) | 1–14 | When upcoming-deadline reminders start |
| Rows per page | 5–100 | Paging in all tables |
| Failed logins before lockout | 3–20 | Brute-force protection |
| Lockout duration (minutes) | 1–1440 | Brute-force protection |
| Session timeout (minutes) | 5–480 | Automatic logout after inactivity |

System-managed values (such as `last_status_check`) are not shown and cannot be edited. The page also shows when the last automatic check ran.

**Departments:** add, rename, and delete. Names must be unique. A department that still has users cannot be deleted.

Every change is logged as **Update Settings** with the old and new values.

## 6. Phase 7 test checklist

| # | Test | Expected result |
|---|---|---|
| 1 | Log in as `employee` when a task is due within 2 days | Bell shows "Deadline Approaching … is due tomorrow" |
| 2 | Reload pages / run `cron/run_checks.php` again the same day | No duplicate reminder |
| 3 | Click the reminder in the bell | Opens the task details; the notification becomes read; badge decreases |
| 4 | Notifications → Unread | Only unread items |
| 5 | Notifications → Type = Overdue | Only overdue notifications |
| 6 | Mark All as Read | Badge disappears |
| 7 | Leave a page open; in another tab create a task for this user | Badge updates within about a minute |
| 8 | Admin → Activity Logs → Action = Create Task | Only task creation entries |
| 9 | Activity Logs → search a task code | Entries mentioning that task |
| 10 | Settings → Rows per page = 500 | Error: must be 5 to 100 |
| 11 | Settings → Rows per page = 5 | Tables show 5 rows with paging; log shows "Rows per page: 10 → 5" |
| 12 | Settings → System name = "EODB-TaskFlow RHU" | New name in the sidebar and page titles |
| 13 | Settings → add a department, rename it, delete it | All work; deleting "Nursing Services" (has users) is refused |
| 14 | Employee or supervisor opens `admin/settings.php` or `admin/activity_logs.php` | 403 Access Denied |
