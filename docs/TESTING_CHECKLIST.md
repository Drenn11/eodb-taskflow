# Testing Checklist – EODB-TaskFlow

This document has two parts:

1. **Manual test checklist** (specification section 29) for the team to perform and sign off before the defense.
2. **Automated test results**: the tests that were run against the real system on XAMPP (Apache 2.4.58, PHP 8.2.12, MariaDB 10.4.32) during development.

---

## Part 1 – Manual test checklist

Use the demo accounts in the README. Mark each line ✔ or ✘ and write the date and tester.

### Login

| # | Test | Steps | Expected result | Result |
|---|---|---|---|---|
| L1 | Correct credentials | Log in as `admin` / `supervisor` / `employee` | Each opens its own dashboard | |
| L2 | Incorrect credentials | Log in with a wrong password | "Invalid username or password." | |
| L3 | Empty fields | Click Log In with empty fields | Messages under both fields; nothing sent | |
| L4 | Inactive account | Log in as `lfernandez` / `Employee123!` | "Your account is inactive…" | |
| L5 | Lockout | Enter a wrong password 5 times, then the correct one | "Too many failed login attempts" | |
| L6 | Show / hide password | Click the eye icon | Password becomes visible / hidden | |
| L7 | Keep me signed in | Tick it, log in, close the browser, open the system again | Still logged in | |
| L8 | Logout | Click Logout, then the browser Back button | Login page; protected page is not shown | |

### Authorization

| # | Test | Steps | Expected result | Result |
|---|---|---|---|---|
| A1 | Admin access | As admin open Employees, Tasks, Reports, Activity Logs, Settings | All open | |
| A2 | Supervisor access | As supervisor open Tasks, Create Task, Employees, Reports | All open | |
| A3 | Employee access | As employee open My Tasks, Task History, Profile | All open | |
| A4 | Unauthorized page access | As employee type `…/admin/users.php` and `…/supervisor/create_task.php` | 403 Access Denied | |
| A5 | Not logged in | Log out and type `…/admin/dashboard.php` | Login page | |
| A6 | Another employee's task | As `employee` open `…/employee/task_details.php?id=6` | "not found in your assigned tasks" | |
| A7 | Admin cannot create tasks | As admin open `…/supervisor/create_task.php` | 403 Access Denied | |

### Task

| # | Test | Steps | Expected result | Result |
|---|---|---|---|---|
| T1 | Create | Supervisor → Create Task, fill in, save | New code `TASK-YYYY-NNNN`, details page | |
| T2 | Assign | Log in as the assigned employee | Bell shows "New Task Assigned" | |
| T3 | Edit | Supervisor → Edit, change priority and deadline | Saved; history entry; employee notified | |
| T4 | Reassign | Edit, choose another employee | Both employees notified; history shows reassignment | |
| T5 | Update | Employee → Update, set 50% with remarks | In Progress 50%; supervisor notified | |
| T6 | Complete | Employee → Mark as Completed (remarks required) | Completed 100%, date completed set | |
| T7 | Completed task locked | Supervisor opens the completed task | No Edit / Delete; "accomplishment record locked" | |
| T8 | Delete | Supervisor deletes an unfinished task (confirm) | Removed; activity log "Delete Task" | |
| T9 | Overdue detection | phpMyAdmin: set an unfinished task's deadline to yesterday; wait 1 minute; open any page | Task is Overdue, progress unchanged, history "System (automatic)", employee and supervisor notified | |
| T10 | Extend deadline | Supervisor edits the overdue task to a future deadline | Status returns to In Progress / Pending | |
| T11 | Deadline reminder | Have an unfinished task due within 2 days; log in as its employee | "Deadline Approaching" notification | |

### Reports

| # | Test | Steps | Expected result | Result |
|---|---|---|---|---|
| R1 | Generate | Reports → each of the 3 reports | Tables, summaries, charts with database figures | |
| R2 | Filter | Task Report → Status Overdue, a department, a date range | Only matching tasks | |
| R3 | Search | Task Report → search a task code or employee | Only matching tasks | |
| R4 | Print | Print / Save as PDF | Header, report info, table, summary, 3 signature lines | |
| R5 | PDF | In the print dialog choose "Save as PDF" | PDF file created | |
| R6 | Export | Export CSV and open in Excel | Title block, headers, all rows; names display correctly | |

### Security

| # | Test | Steps | Expected result | Result |
|---|---|---|---|---|
| S1 | SQL injection | Username `' OR '1'='1`, any password | Login fails | |
| S2 | SQL injection in search | Search `' OR 1=1 --` | No results | |
| S3 | XSS | Create a task titled `<script>alert(1)</script>` | Title shown as text; no pop-up | |
| S4 | Unauthorized access | Open `…/config/database.php`, `…/database/eodb_taskflow.sql` | 403 / blocked | |
| S5 | Session handling | Stay idle longer than the session timeout, then click | Login page with "session expired" | |
| S6 | Deactivation | Admin deactivates a user who is logged in elsewhere | That user is signed out on the next click | |
| S7 | Input validation | Add Employee with an existing username, bad email, weak password | Field errors; nothing saved | |
| S8 | Passwords hashed | phpMyAdmin → `users` table | Every password starts with `$2y$` | |
| S9 | File upload | Profile → upload a `.php` file renamed to `.png` | "Only JPG, PNG, or WEBP images are allowed." | |

### Responsive design

| # | Device | Steps (Chrome/Edge: F12 → Ctrl+Shift+M) | Expected result | Result |
|---|---|---|---|---|
| D1 | Desktop (1920 px) | Open dashboards, Tasks, Reports | Sidebar, top bar with search, multi-column layout | |
| D2 | Laptop (1366 px) | Same pages | No sideways scrolling | |
| D3 | Tablet (768 px) | Same pages | Menu button (☰) opens the sidebar; no sideways page scrolling | |
| D4 | Mobile (375 px) | Same pages, Login, Update Task | Two stat cards per row; tables shown as cards; buttons full width | |

Tested by: ____________________  Date: ______________  Reviewed by: ____________________

---

## Part 2 – Automated test results

### 2.1 Final system test on a fresh installation (Phase 10)

The database was **imported from `database/eodb_taskflow.sql`**, `setup.php` was run, and the whole system was tested over HTTP exactly as a browser uses it. Afterwards the working database was restored from a backup.

**Result: 75 of 75 passed.**

### Installation (fresh import) (7/7)

| # | Test | Result |
|---|---|---|
| 1 | database/eodb_taskflow.sql imports without errors | ✔ Pass |
| 2 | Sample accounts start with the not-a-password marker | ✔ Pass |
| 3 | setup.php completes with no FAIL lines | ✔ Pass |
| 4 | All passwords stored as bcrypt hashes ($2y$) | ✔ Pass |
| 5 | Sample data: 8 users, 12 tasks, 6 departments | ✔ Pass |
| 6 | All 9 foreign keys exist | ✔ Pass |
| 7 | Running setup.php again changes nothing | ✔ Pass |

### Login (18/18)

| # | Test | Result |
|---|---|---|
| 8 | Admin with correct credentials -> admin dashboard | ✔ Pass |
| 9 | Supervisor with correct credentials -> supervisor dashboard | ✔ Pass |
| 10 | Employee logs in with email instead of username -> employee dashboard | ✔ Pass |
| 11 | Incorrect password -> generic error | ✔ Pass |
| 12 | Unknown username -> same generic error (no account discovery) | ✔ Pass |
| 13 | Empty fields -> "Please enter your username or email and your password." | ✔ Pass |
| 14 | Inactive account is refused | ✔ Pass |
| 15 | SQL injection in login (' OR '1'='1) fails | ✔ Pass |
| 16 | Lockout after 5 failed attempts (even with the right password) | ✔ Pass |
| 17 | After the lockout is cleared, login works again | ✔ Pass |
| 18 | Failed logins are recorded in the activity log | ✔ Pass |
| 19 | Session cookie is HttpOnly and SameSite=Lax | ✔ Pass |
| 20 | "Keep me signed in" sets an HttpOnly selector:validator cookie (7 days) | ✔ Pass |
| 21 | Database stores only the SHA-256 hash of the validator, never the validator itself | ✔ Pass |
| 22 | Remembered device is logged in without a password | ✔ Pass |
| 23 | Remember token is single-use: replaced by a new one after use | ✔ Pass |
| 24 | Forged cookie (real selector, wrong validator) is rejected | ✔ Pass |
| 25 | Theft protection: all remembered devices of that user signed out and logged | ✔ Pass |

### Logout and session handling (5/5)

| # | Test | Result |
|---|---|---|
| 26 | Protected pages send no-cache headers (Back button after logout) | ✔ Pass |
| 27 | Logout returns to the login page with a message | ✔ Pass |
| 28 | After logout the dashboard requires login again | ✔ Pass |
| 29 | Logout through a plain link (GET) only shows a confirmation | ✔ Pass |
| 30 | Deactivated while logged in -> signed out on the next click | ✔ Pass |

### Authorization (role-based access) (14/14)

| # | Test | Result |
|---|---|---|
| 31 | Access matrix: 22 pages x 3 roles + anonymous all correct | ✔ Pass |
| 32 | Blocked attempts are logged as Blocked Access | ✔ Pass |
| 33 | Employee cannot open another employee's task (id=6) | ✔ Pass |
| 34 | Non-numeric id in URL is rejected safely (id=1 OR 1=1) | ✔ Pass |
| 35 | Direct access to config/database.php is blocked (403) | ✔ Pass |
| 36 | Direct access to config/app.php is blocked (403) | ✔ Pass |
| 37 | Direct access to includes/auth.php is blocked (403) | ✔ Pass |
| 38 | Direct access to database/eodb_taskflow.sql is blocked (403) | ✔ Pass |
| 39 | Direct access to cron/run_checks.php is blocked (403) | ✔ Pass |
| 40 | Direct access to docs/PHASE1_SYSTEM_DESIGN.md is blocked (403) | ✔ Pass |
| 41 | Direct access to README.md is blocked (403) | ✔ Pass |
| 42 | Direct access to assets/images/profiles/ is blocked (403) | ✔ Pass |
| 43 | Unknown page shows the friendly 404 page (no server details) | ✔ Pass |
| 44 | Friendly 404 page content | ✔ Pass |

### Task management (13/13)

| # | Test | Result |
|---|---|---|
| 45 | Create task -> code TASK-YYYY-0013, Pending 0% | ✔ Pass |
| 46 | Stored XSS: script in the title is shown as text | ✔ Pass |
| 47 | Assign: employee notified, activity logged | ✔ Pass |
| 48 | Edit + reassign: saved, history entry, both employees notified | ✔ Pass |
| 49 | Employee update: 45% In Progress, supervisor notified | ✔ Pass |
| 50 | Employee completes task: Completed 100%, completed_at set, supervisor notified | ✔ Pass |
| 51 | Completed task is locked (no edit by supervisor) | ✔ Pass |
| 52 | Completed task cannot be deleted | ✔ Pass |
| 53 | Delete unfinished task: removed, logged | ✔ Pass |
| 54 | Delete without CSRF token is rejected (400) | ✔ Pass |
| 55 | Overdue detection: 0002 becomes Overdue, 60% progress kept | ✔ Pass |
| 56 | Overdue: automatic history entry + employee and supervisor notified | ✔ Pass |
| 57 | Running the check again sends no duplicates | ✔ Pass |

### Reports (8/8)

| # | Test | Result |
|---|---|---|
| 58 | Task Report generates (13 tasks incl. the completed test task) | ✔ Pass |
| 59 | Task Report filter: Status = Overdue -> 0002, 0003, 0007 | ✔ Pass |
| 60 | Task Report search "dengue" -> 0004 | ✔ Pass |
| 61 | Productivity Report: totals 13 tasks, 5 completed (38.5%) | ✔ Pass |
| 62 | Accomplishment Report: 5 completed tasks in the last 60 days | ✔ Pass |
| 63 | Print view: header, signature lines | ✔ Pass |
| 64 | CSV export downloads with all rows | ✔ Pass |
| 65 | Reports are logged (Generate Report) | ✔ Pass |

### Security (10/10)

| # | Test | Result |
|---|---|---|
| 66 | Security headers: CSP, X-Frame-Options DENY, nosniff, Referrer-Policy | ✔ Pass |
| 67 | CSP allows scripts only from this system | ✔ Pass |
| 68 | SQL injection in search box has no effect | ✔ Pass |
| 69 | SQL injection in a filter value is ignored | ✔ Pass |
| 70 | Form without CSRF token is rejected (add user) | ✔ Pass |
| 71 | Input validation rejects bad user data (8 fields) | ✔ Pass |
| 72 | Employee cannot mark another user's notification as read | ✔ Pass |
| 73 | No plain-text passwords in the database | ✔ Pass |
| 74 | Database credentials never appear in pages | ✔ Pass |
| 75 | APP_DEBUG is off (errors are not shown to users) | ✔ Pass |

### 2.2 Responsive design and browser check (Phases 9–10)

Microsoft Edge (headless) loaded every page of all three roles plus the login and 403 pages — **36 pages at 4 widths = 144 combinations** — measured whether anything extends past the right edge of the screen, collected JavaScript errors and Content-Security-Policy violations, and took screenshots for review.

| Width | Device | Pages with sideways overflow | JS errors / CSP violations |
|---|---|---|---|
| 375 px | Mobile | 0 | 0 |
| 768 px | Tablet | 0 | 0 |
| 1366 px | Laptop | 0 | 0 |
| 1920 px | Desktop | 0 | 0 |

(The only console entry was the intended HTTP 403 status of the Access Denied page.)

Issues found and fixed during this testing: login-page validation messages visible before typing; one-column stat cards on phones; wide tables on phones (now cards); print view wider than a tablet screen; system name hidden on phones.

### 2.3 Feature test suites run after each phase

| Phase | Area | Automated checks | Result |
|---|---|---|---|
| 2 | Login, role redirects, inactive account, wrong password, 403, protected config | 7 | all passed |
| 3 | Admin dashboard, user management, search/filters, validation, deactivation, access control | 30 | all passed |
| 4 | Supervisor dashboard, task create/edit/reassign/delete, Algorithm 2 and 3, filters, XSS, CSRF | 40 | all passed |
| 5 | Employee dashboard, task update rules, completion, history, profile, photo upload, password change | 52 | all passed |
| 6 | Automatic overdue detection, scheduler, admin task view, employee performance | 40 | all passed |
| 7 | Notifications, deadline reminders, mark as read, activity logs, settings, departments | 39 | all passed |
| 8 | Reports, charts, filters, print view, CSV export, formula-injection protection | 45 | all passed |
| 9 | Quick search, role scoping, filters, responsive markup | 23 | all passed |
| 10 | Fresh-install system test (above) | 75 | all passed |

Where a first run reported a failure, the cause was investigated each time; problems in the system were fixed (e.g. the login-page message bug), and problems in the test scripts themselves were corrected and the suite re-run. All final runs passed.

### 2.4 Static code checks (Phase 10)

* PHP syntax check (`php -l`) of all 65 PHP files: **no errors**.
* Security scan of the source: no page without an access check, no POST handler without a CSRF check, no unescaped output of user data, no SQL built from user input, no dangerous functions. Details in [SECURITY.md](SECURITY.md).
