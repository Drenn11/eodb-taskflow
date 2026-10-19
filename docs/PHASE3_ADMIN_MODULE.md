# Phase 3 – Admin Dashboard, Main Layout, and User Management

## 1. What Phase 3 adds

| Feature | Files |
|---|---|
| Main layout: dark sidebar, top bar, footer | `includes/header.php`, `includes/sidebar.php`, `includes/navbar.php`, `includes/footer.php`, `assets/js/app.js` |
| Role-based sidebar | `sidebar_menu()` in `includes/functions.php` |
| Notification bell with unread count and the latest 5 notifications | `includes/navbar.php` (marking as read comes in Phase 7) |
| Administrator dashboard | `admin/dashboard.php` |
| Employee list with search, filters, and pagination | `admin/users.php` |
| Add, edit, view, deactivate/activate employee | `admin/add_user.php`, `admin/edit_user.php`, `admin/view_user.php`, `admin/toggle_user_status.php` |
| Manage roles (permission matrix; roles are changed on Edit Employee) | `admin/roles.php` |
| Shared validation and form | `includes/user_functions.php`, `includes/user_form.php` |
| Plain-text password repair | `database/setup.php` step 4b |

## 2. How a page uses the layout

```php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$pageTitle  = 'Employees';    // browser tab title
$activeMenu = 'employees';    // highlighted sidebar item
require __DIR__ . '/../includes/header.php';
// ... page content ...
require __DIR__ . '/../includes/footer.php';
```

## 3. Role-based sidebar

`sidebar_menu($role)` lists the menu for each role. It also hides any page that does not exist yet, so the menu never shows a broken link while the system is built phase by phase. As later phases add pages (Tasks, Reports, Notifications, Activity Logs, Profile, Settings), they appear automatically.

| Administrator | Supervisor | Employee |
|---|---|---|
| Dashboard, Tasks, Employees, Reports, Notifications, Activity Logs, Profile, Settings, Logout | Dashboard, Tasks, Employees, Reports, Notifications, Profile, Logout | Dashboard, My Tasks, Task History, Notifications, Profile, Logout |

Hiding a link is only for convenience. Every page still checks the role on the server with `require_role()`.

## 4. Administrator dashboard figures

| Card | Query rule |
|---|---|
| Total Employees | users with role `employee` |
| Active Employees | role `employee` and status `active` |
| Total / Completed / Pending / Overdue Tasks | counted from `tasks` using `effective_status_sql()` |

`effective_status_sql()` applies the Algorithm 3 overdue rule inside the query: a task that is not completed and whose deadline has passed is counted as **Overdue**, even before the stored status is refreshed. Phase 6 adds the automatic update of the stored status.

The dashboard also shows a task status overview, tasks due in the next 3 days, active employees per department, and the 8 most recent activities.

## 5. User management rules

**Validation (server side, `validate_user_form()`):**

| Field | Rule |
|---|---|
| Employee ID | required, 2–20 letters/numbers/hyphens, unique, saved in upper case |
| Full name | required, letters, spaces, `.` `'` `-` only; repeated spaces removed |
| Department | required, must exist in `departments` |
| Email | required, valid format, unique, saved in lower case |
| Username | required, 3–50 letters/numbers/`.`/`_`, unique |
| Role / Status | must be one of the allowed values |
| Password | required when adding, optional when editing; 8–72 characters with upper case, lower case, number, and special character; not equal to the username; must match the confirmation |

**Safeguards:**

- Accounts are **deactivated, never deleted**, so task history and reports stay complete.
- An administrator cannot change their own role or deactivate their own account.
- The last active administrator cannot be demoted or deactivated.
- Deactivating a user, changing their role, or resetting their password removes their "Remember me" logins. A deactivated user is signed out on their next click.
- When a user with unfinished tasks is deactivated, the admin is warned to reassign those tasks.
- All changes use POST with a CSRF token and prepared statements.

**Activity log entries:** `add_user`, `edit_user` (lists what changed), `deactivate_user`, `activate_user`.

## 6. Important: never type a password directly into phpMyAdmin

The `users.password` column must contain a bcrypt hash (starting with `$2y$`). A password typed directly into phpMyAdmin is stored as plain text, and **nobody can log in with it**.

To change a password, use **Employees → Edit → Reset Password**. If a password was already typed in phpMyAdmin, run `database/setup.php` again. It detects plain-text passwords and converts them into hashes, so the typed password starts working.

## 7. Phase 3 test checklist

| # | Test | Expected result |
|---|---|---|
| 1 | Log in as admin | Dashboard shows 6 cards, status overview, due-soon tasks, departments, recent activities |
| 2 | Resize the browser to phone width | Sidebar hides; the ☰ button opens it; clicking outside closes it |
| 3 | Click the bell icon | Dropdown shows unread count and latest notifications |
| 4 | Employees → search `garcia` | Only Mark Anthony L. Garcia |
| 5 | Filter Status = Inactive | Only Liza M. Fernandez |
| 6 | Filter Role = Supervisor | Only Elena R. Mendoza |
| 7 | Search `' OR 1=1 --` | "No users match" (SQL injection has no effect) |
| 8 | Add Employee with an existing username, bad email, and weak password | Each field shows its own error; nothing is saved |
| 9 | Add a valid employee, then log in as that employee | Account works; activity log shows `add_user` |
| 10 | Edit that employee and change the role to Supervisor | Change saved; the user immediately sees the supervisor menu |
| 11 | Edit your own admin account | Role and status fields are locked |
| 12 | Deactivate a user who is logged in elsewhere | They are signed out on their next click and cannot log in |
| 13 | Activate the user again | They can log in again |
| 14 | Open Roles & Permissions | Role counts and permission matrix are shown |
| 15 | Log in as employee and open `admin/users.php` | 403 Access Denied |
| 16 | Log in as supervisor and open `admin/add_user.php` | 403 Access Denied |
