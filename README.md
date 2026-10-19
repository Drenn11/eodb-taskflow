# EODB-TaskFlow

**A Web-Based Streamlined Employee Task and Productivity Monitoring System in RHU Mulanay**

Version 1.0.0 · PHP 8 · MySQL/MariaDB · Bootstrap 5 · runs on XAMPP

---

## 1. System Description

EODB-TaskFlow is a centralized web system that the Rural Health Unit (RHU) Mulanay uses to **record, assign, monitor, update, and report employee tasks and accomplishments**. Supervisors assign tasks with a priority and deadline; employees update their progress and mark tasks as completed; the system detects overdue tasks automatically, sends notifications, computes productivity, and produces printable reports.

It covers **task monitoring and productivity recording only**. It does not handle payroll, salary, attendance, leave, finances, patient medical records, diagnosis, or inventory.

### Conceptual framework (IPO)

| Input | Process | Output |
|---|---|---|
| Employee and user account information, user role, task information, description, priority, deadline, status, progress, employee remarks | Authentication, task creation and assignment, recording and updating, status / progress / deadline monitoring, overdue detection, accomplishment recording, searching and filtering, productivity computation, report generation, data storage | Organized task records, updated status and progress, completed / pending / overdue task records, employee productivity summary, task monitoring reports |

## 2. Features

| Area | Features |
|---|---|
| **Login and security** | Username or email login, show/hide password, "Keep me signed in" (secure token), lockout after 5 failed attempts, 30-minute inactivity timeout, secure logout, bcrypt passwords, CSRF protection, Content Security Policy |
| **Role-based access** | Administrator, Supervisor, Employee; the sidebar shows only allowed pages; every page checks the role on the server (403 page otherwise) |
| **Administrator** | Dashboard, add / edit / view / deactivate / activate employees, manage roles (permission matrix), view all tasks (read-only), reports, activity logs, system settings and departments |
| **Supervisor** | Dashboard, create and assign tasks (automatic task code), edit / reassign / delete tasks, task monitoring with filters, employee performance, review completed tasks, reports |
| **Employee** | Dashboard with completion rate, My Tasks, task details, update status / progress / remarks, mark as completed, task history, personal productivity, profile |
| **Task rules** | Status follows progress (0% Pending, 1–99% In Progress, 100% Completed); a task past its deadline becomes **Overdue** automatically while keeping its progress; completed tasks are locked as accomplishment records |
| **Notifications** | New assignment, task update, completion, overdue, approaching deadline; bell with unread count (refreshes every minute); mark as read / mark all as read |
| **Reports** | Task Report, Employee Productivity Report, Accomplishment Report; charts; filters; **Print / Save as PDF** with RHU header and Prepared / Reviewed / Approved signature lines; **CSV export** |
| **Search and filter** | Quick search in the top bar; search and filters (status, priority, employee, department, date range) on every list |
| **Activity log** | Logins, logouts, task and user actions, report generation, security events (read-only) |
| **Profile** | Change email, upload profile photo, change password (all roles) |
| **Responsive** | Desktop, laptop, tablet, and mobile layouts (tables become cards on phones) |
| **Works offline** | Bootstrap, Font Awesome, and Chart.js are included in `assets/vendor/` |

## 3. Requirements

| Item | Version |
|---|---|
| XAMPP | 8.x (Apache 2.4, PHP 8.0 or newer, MariaDB 10.4+ / MySQL 8.0.16+) |
| PHP extensions | `pdo_mysql`, `mbstring`, `fileinfo` (enabled in XAMPP by default) |
| Browser | Chrome, Edge, or Firefox (current versions) |
| Internet | **Not required** |

Tested on XAMPP with Apache 2.4.58, PHP 8.2.12, and MariaDB 10.4.32 on Windows 11.

## 4. Installation

### 4.1 XAMPP setup

1. Install XAMPP from https://www.apachefriends.org (default folder `C:\xampp`).
2. Open the **XAMPP Control Panel** and click **Start** for **Apache** and **MySQL**.
3. Copy the whole `eodb-taskflow` folder to **`C:\xampp\htdocs\`** so that this file exists:
   `C:\xampp\htdocs\eodb-taskflow\index.php`

> The folder name must be `eodb-taskflow`. If you use another name, change `BASE_URL` in `config/app.php` **and** the two `ErrorDocument` lines in `.htaccess`.

### 4.2 Database setup

1. Open **http://localhost/phpmyadmin**.
2. Click **Import** → **Choose File** → select `eodb-taskflow\database\eodb_taskflow.sql` → **Import**.
   This creates the database `eodb_taskflow` with all tables, the productivity view, and the sample data.
3. Open **http://localhost/eodb-taskflow/database/setup.php** once.
   It turns the sample passwords into secure hashes and checks the installation. Every line should start with `[ OK ]`.

**Database connection settings** are in `config/database.php`:

| Setting | Value in this copy | Change when… |
|---|---|---|
| `DB_HOST` | `127.0.0.1` | MySQL runs on another computer |
| `DB_PORT` | `3307` | Your MySQL uses the standard port `3306` (see the port shown next to MySQL in the XAMPP Control Panel) |
| `DB_USER` / `DB_PASS` | `root` / *(empty)* | Your MySQL root account has a password |

> ⚠ Importing `eodb_taskflow.sql` again **deletes all data** and reloads the sample data. Use this to reset the demo before the defense, then run `setup.php` again.

## 5. How to Run

Open **http://localhost/eodb-taskflow/** and log in.

### Login credentials (demo only)

| Role | Username | Password |
|---|---|---|
| Administrator | `admin` | `Admin123!` |
| Supervisor | `supervisor` | `Supervisor123!` |
| Employee | `employee` | `Employee123!` |

Other sample employees: `areyes`, `mgarcia`, `kramos`, `rbautista` (password `Employee123!`). `lfernandez` is an **inactive** account (login is refused). You can also log in with the email address, e.g. `employee@rhumulanay.test`.

These are demonstration passwords; passwords are stored only as bcrypt hashes. Change them through **Employees → Edit → Reset Password** or **Profile → Change Password**. **Never type a password directly into phpMyAdmin**: it would be stored as plain text and the login would fail. (If that happens, open `database/setup.php` again; it converts plain-text passwords into hashes.)

### Sample data

6 departments, 8 users (1 administrator, 1 supervisor, 6 employees of whom one is inactive), and 12 tasks with history, notifications, and activity logs. Dates are relative to the day of import, so there are always pending, in-progress, completed, overdue, and due-soon tasks to demonstrate.

### Optional: daily automatic check

Overdue detection and deadline reminders run automatically while the system is used. To also run them every morning when nobody is logged in, create a **Windows Task Scheduler** task: Program `C:\xampp\php\php.exe`, Arguments `C:\xampp\htdocs\eodb-taskflow\cron\run_checks.php`, daily at 7:00 AM.

## 6. Folder Structure

```
eodb-taskflow/
├── .htaccess                 no folder listing, friendly 403/404 pages, blocks .md/.sql/hidden files
├── index.php                 sends each user to their dashboard (or the login page)
├── login.php  logout.php     login (Algorithm 1) and secure logout
├── unauthorized.php          403 Access Denied page (role not allowed)
├── error.php                 friendly 404 / 403 page for Apache
├── config/                   app.php (settings, library paths), database.php (PDO connection) — blocked from the web
├── includes/                 shared PHP — blocked from the web
│   ├── auth.php              sessions, login, remember me, lockout, require_login() / require_role()
│   ├── csrf.php              CSRF tokens
│   ├── functions.php         escaping, URLs, input, flash messages, settings, logging, badges, paging, security headers
│   ├── header.php  sidebar.php  navbar.php  footer.php      page layout
│   ├── task_functions.php    Algorithm 2 (task code), Algorithm 3 (status), overdue detection, task history
│   ├── notification_functions.php   Algorithm 5 (notifications, deadline reminders)
│   ├── productivity_functions.php   Algorithm 4 (completion rate)
│   ├── report_functions.php  report queries, CSV / print table layout
│   ├── scheduler.php         automatic checks (overdue, reminders)
│   ├── user_functions.php    user validation
│   └── task_list.php  task_table.php  task_filter_bar.php  task_form.php  task_details_view.php
│       user_form.php  report_filter_bar.php                 shared lists and forms
├── admin/                    dashboard, users (add/edit/view/deactivate), roles, tasks (read-only), activity logs, settings
├── supervisor/               dashboard, tasks, create/edit/delete task, task details, employee performance
├── employee/                 dashboard, my tasks, task details, update task, task history
├── notifications/            notification list, open, mark as read, unread count (JSON)
├── profile/                  my profile (all roles)
├── search/                   quick search (all roles)
├── reports/                  reports page, 3 reports, print view, CSV export
├── cron/                     run_checks.php (command line only)
├── assets/
│   ├── css/                  style.css (application), print.css (printed reports)
│   ├── js/                   app.js, login.js, task_form.js, update_task.js, reports.js, print.js
│   ├── images/               logo.svg, profiles/ (uploaded photos; scripts blocked)
│   └── vendor/               Bootstrap 5.3.3, Font Awesome 6.5.2, Chart.js 4.4.1 (verified copies)
├── database/                 eodb_taskflow.sql (schema + sample data), setup.php (one-time setup)
└── docs/                     design, algorithms, ERD, security, and testing documents (blocked from the web)
```

## 7. Database

Database `eodb_taskflow` (InnoDB, utf8mb4): `users`, `departments`, `tasks`, `task_updates`, `notifications`, `activity_logs`, `remember_tokens`, `login_attempts`, `settings`, and the view `vw_employee_productivity`, with 9 foreign keys and indexes on every lookup column. The ERD, relationships, and data dictionary are in [docs/PHASE1_SYSTEM_DESIGN.md](docs/PHASE1_SYSTEM_DESIGN.md).

## 8. Algorithms

| Algorithm | Where |
|---|---|
| 1 – Login Authentication | `login.php`, `attempt_login()` in `includes/auth.php` |
| 2 – Task Assignment | `supervisor/create_task.php`, `generate_task_code()` |
| 3 – Task Status (incl. overdue) | `compute_task_status()`, `refresh_overdue_tasks()` in `includes/task_functions.php` |
| 4 – Productivity Rate (Completed ÷ Total × 100) | `includes/productivity_functions.php` |
| 5 – Notification | `includes/notification_functions.php`, the task pages, `includes/scheduler.php` |

The completion rate is for **monitoring and reporting only**, not an automatic disciplinary score.

## 9. How to Test

1. Log in with each demo account and follow **[docs/TESTING_CHECKLIST.md](docs/TESTING_CHECKLIST.md)** (login, authorization, tasks, overdue detection, reports, security, responsive design). It also lists the results of the automated test runs.
2. Each phase document in `docs/` has a step-by-step checklist for its features.
3. To test responsiveness, press **F12 → Ctrl+Shift+M** in Chrome or Edge and choose a phone or tablet size.
4. To see overdue detection: in phpMyAdmin set an unfinished task's `deadline` to yesterday, wait one minute, and open any page (or run `cron/run_checks.php`).

Security measures are described in **[docs/SECURITY.md](docs/SECURITY.md)**; the final quality check is in **[docs/FINAL_QUALITY_CHECK.md](docs/FINAL_QUALITY_CHECK.md)**.

## 10. Troubleshooting

| Problem | Cause and solution |
|---|---|
| **Blank white page** | A PHP error. See the last lines of `C:\xampp\apache\logs\error.log`. Make sure *all* files were copied — a mix of old and new files causes "Undefined constant" errors. |
| **"System temporarily unavailable"** | The database connection failed. Start MySQL in XAMPP; check `DB_PORT` (3306 or 3307) and `DB_PASS` in `config/database.php`; make sure `eodb_taskflow.sql` was imported. |
| **"Invalid username or password" with the right password** | Run `database/setup.php` (sample passwords not yet hashed, or a password was typed into phpMyAdmin as plain text). |
| **"Too many failed login attempts"** | Wait 15 minutes, or run `DELETE FROM login_attempts;` in phpMyAdmin. |
| **404 for every page** | The folder is not `C:\xampp\htdocs\eodb-taskflow`, or `BASE_URL` in `config/app.php` does not match the folder name. |
| **Page looks unstyled / no icons** | `assets/vendor/` is missing — copy the complete folder again. |
| **Logged out while working** | The inactivity timeout (default 30 minutes); change it in **Settings → Session timeout**. |
| **Apache does not start** | Port 80 is used by another program (e.g. IIS, Skype). Stop it or change Apache's port in XAMPP. |
| **MySQL does not start** | Port conflict or an unclean shutdown. Check the MySQL log in XAMPP; this installation uses port 3307. |
| **Overdue status not updating** | The check runs at most once a minute while someone is logged in. Wait a minute and reload, or run `cron/run_checks.php`. |
| **Charts not shown** | JavaScript is disabled or `assets/vendor/chartjs/` is missing. The figures are still shown in the tables. |
| **Profile photo upload fails** | Only JPG, PNG, or WEBP files up to 2 MB are accepted. |

## 11. Documentation

| Document | Contents |
|---|---|
| [docs/PHASE1_SYSTEM_DESIGN.md](docs/PHASE1_SYSTEM_DESIGN.md) | Architecture, IPO mapping, access matrix, **ERD**, data dictionary |
| [docs/PHASE2_AUTHENTICATION.md](docs/PHASE2_AUTHENTICATION.md) | Algorithm 1, sessions, remember me, lockout |
| [docs/PHASE3_ADMIN_MODULE.md](docs/PHASE3_ADMIN_MODULE.md) | Layout, admin dashboard, user management rules |
| [docs/PHASE4_SUPERVISOR_TASKS.md](docs/PHASE4_SUPERVISOR_TASKS.md) | Algorithms 2 and 3, task rules |
| [docs/PHASE5_EMPLOYEE_MODULE.md](docs/PHASE5_EMPLOYEE_MODULE.md) | Task updating rules, profile and upload security |
| [docs/PHASE6_MONITORING.md](docs/PHASE6_MONITORING.md) | Automatic overdue detection, employee performance |
| [docs/PHASE7_NOTIFICATIONS_LOGS.md](docs/PHASE7_NOTIFICATIONS_LOGS.md) | Algorithm 5, activity logs, settings |
| [docs/PHASE8_REPORTS.md](docs/PHASE8_REPORTS.md) | Reports, charts, printing, CSV |
| [docs/PHASE9_SEARCH_RESPONSIVE.md](docs/PHASE9_SEARCH_RESPONSIVE.md) | Search, filters, responsive design |
| [docs/UI_DESIGN_SYSTEM.md](docs/UI_DESIGN_SYSTEM.md) | Colours, status colours, typography, components, layout |
| [docs/SECURITY.md](docs/SECURITY.md) | All security measures |
| [docs/TESTING_CHECKLIST.md](docs/TESTING_CHECKLIST.md) | Test checklist and results |
| [docs/FINAL_QUALITY_CHECK.md](docs/FINAL_QUALITY_CHECK.md) | Final quality check |
