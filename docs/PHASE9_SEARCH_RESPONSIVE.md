# Phase 9 – Search, Filtering, and Responsive Design

## 1. What Phase 9 adds or changes

| Change | Files |
|---|---|
| **Quick search** in the top bar (all roles) with a results page | `includes/navbar.php`, `search/index.php` |
| Task searches also match the **description** | `includes/task_list.php`, `employee/my_tasks.php` |
| **Deadline range** filter on My Tasks | `employee/my_tasks.php` |
| **Mobile card layout** for the main tables (rows become labelled cards on phones) | `assets/css/style.css`, `includes/task_table.php`, `employee/my_tasks.php`, `admin/users.php` |
| **Two stat cards per row** on phones, compact cards | `assets/css/style.css`, the three dashboards |
| Print view: wide tables scroll on tablets instead of widening the page | `reports/print_report.php`, `assets/css/print.css` |
| **Bug fix:** login page showed both "Please enter…" messages before typing | `assets/css/style.css` (`[hidden] { display: none !important; }`) |
| Names may contain **commas** (e.g. "Ana Cruz, RN", "…, MD, FPCAM") | `includes/user_functions.php` |
| System name visible in the top bar on phones; subtitle only on wide screens | `includes/navbar.php` |

## 2. Search

### Quick search (top bar)

| Role | Searches | Opens |
|---|---|---|
| Employee | own tasks only: task code, title, description | `employee/task_details.php` |
| Supervisor | all tasks (code, title, description, employee) and employees (name, Employee ID, position, department) | supervisor task details / employee performance |
| Administrator | all tasks and all user accounts (also username and email) | read-only admin task details / view user |

Up to 15 results per section, an exact task-code match first, and an "Open in list" link to the full filtered list. At least 2 characters are required.

### Search and filters on every list

| Page | Search | Filters |
|---|---|---|
| Admin → Employees | name, Employee ID, username, email, position | role, department, status |
| Supervisor / Admin → Tasks | task code, title, description, employee | status, priority, employee, department, deadline range (+ "only tasks I assigned") |
| Employee → My Tasks | task code, title, description | status, priority, deadline range |
| Employee → Task History | task code, title | date range |
| Supervisor → Employees | name, Employee ID, position | department, active/inactive, period, sort |
| Notifications | — | unread, type |
| Activity Logs | description, user name | user, action, date range |
| Reports (3) | task / employee | employee, department, status, priority, date range |

This covers all items in the specification: **Search** employee, task, task code; **Filters** status, priority, employee, department, date range.

### Security and efficiency

- Every search uses **prepared statements**; typed text is never part of the SQL.
- `%` and `_` typed by the user are escaped (`like_term()`), so "100%" searches for the text "100%" instead of matching everything.
- Search results are **escaped** before display, so `<script>` is shown as text.
- Filter values are checked against allowed lists (statuses, priorities, roles, valid dates); anything else is ignored.
- Results respect role access: an employee's search never returns another employee's task.

**Indexes used** (checked with `EXPLAIN`):

| Query | Index |
|---|---|
| An employee's tasks / open task count | `idx_tasks_assigned_to_status` |
| Unread notification count | `idx_notifications_user_read` (index only) |
| Tasks due in the next days | `idx_tasks_deadline` |
| Task history of a task | `idx_task_updates_task` |
| Login, username/email lookup | unique keys `uq_users_username`, `uq_users_email` |

With only a few dozen rows, MySQL may choose a full table scan because it is faster at that size; the indexes on `status`, `deadline`, and `created_at` are used automatically as the data grows. Searches with `LIKE '%text%'` cannot use an index, which is acceptable for the size of one RHU (hundreds to a few thousand tasks per year).

## 3. Responsive design

| Width | Layout |
|---|---|
| **Desktop** (≥ 1200 px) | Fixed dark sidebar, full top bar with subtitle and search box, multi-column dashboards |
| **Laptop** (992–1199 px) | Fixed sidebar, search box, 3-column stat cards |
| **Tablet** (768–991 px) | Sidebar becomes a slide-in menu (☰), tables in normal table form with horizontal scrolling inside the card |
| **Mobile** (< 768 px) | Slide-in menu, search icon, **two compact stat cards per row**, **task and user tables shown as stacked cards**, full-width buttons, filters stacked two per row |

### How it was tested

A test script drove **Microsoft Edge (headless)** through every page of all three roles plus the login and 403 pages: **36 pages × 4 widths = 144 combinations** (375 px phone, 768 px tablet, 1366 px laptop, 1920 px desktop). For each one it measured whether anything sticks out past the right edge of the screen (content inside intentionally scrollable tables excluded) and took screenshots for visual review.

| Run | Pages with horizontal overflow |
|---|---|
| Before Phase 9 fixes | 1 (print view on tablet) |
| After Phase 9 fixes | **0** |

The screenshot review also found the login-page message bug and the long single-column dashboards on phones, both fixed above.

## 4. Phase 9 test checklist

| # | Test | Expected result |
|---|---|---|
| 1 | Supervisor: type `juan` in the top-bar search | Juan's 4 tasks and Juan as an employee |
| 2 | Search `TASK-2026-0007` (or lower case) | That task listed first |
| 3 | Employee: search `dengue` (another employee's task) | No results |
| 4 | Search `handouts` | TASK-2026-0012 (matched in its description) |
| 5 | Search `<script>alert(1)</script>` | Shown as text, nothing runs |
| 6 | Search `' OR 1=1 --` | No results |
| 7 | My Tasks → Deadline from/to | Only tasks due in that range |
| 8 | Tasks → Status Overdue + a department | Only overdue tasks of that department |
| 9 | Open the login page on a phone | No error messages until you press Log In with empty fields |
| 10 | Phone width (browser DevTools, Ctrl+Shift+M, 375 px): dashboard | Two stat cards per row |
| 11 | Phone width: Tasks / My Tasks / Employees | Each row is a card with labels (Task Code, Deadline, Status, …) |
| 12 | Phone width: ☰ button | Sidebar slides in; tap outside or press Esc to close |
| 13 | Tablet width (768 px): any page | No sideways scrolling of the whole page |
| 14 | Desktop: all pages | Sidebar, top bar with search, multi-column layout |
| 15 | Add Employee with name "Ana Cruz, RN" | Accepted |
