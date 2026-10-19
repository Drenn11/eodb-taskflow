# EODB-TaskFlow — Phase 2: Login, Authentication, Sessions, and Role-Based Access

## 1. Files in This Phase

| File | Purpose |
|---|---|
| `login.php` | Login page: branding, username/email, password, show/hide, remember me, error messages |
| `logout.php` | Secure logout. Works only through POST with a CSRF token; opening it directly shows a confirmation button |
| `index.php` | Entry point: sends logged-in users to their dashboard, everyone else to the login page |
| `unauthorized.php` | 403 Access Denied page |
| `includes/auth.php` | Sessions, Algorithm 1, remember me, lockout, `require_login()`, `require_role()` |
| `includes/functions.php` | Escaping `e()`, `url()`, `redirect()`, input helpers, flash messages, `get_setting()`, `log_activity()` |
| `includes/csrf.php` | `csrf_field()`, `csrf_valid()`, `require_csrf()` |
| `includes/temp_dashboard.php` | **Temporary** test dashboard (removed once the real dashboards exist) |
| `admin/`, `supervisor/`, `employee/` `dashboard.php` | Protected by role; temporary content until Phases 3–5 |
| `assets/css/style.css`, `assets/js/login.js`, `assets/images/logo.svg` | Styling, login page behaviour, logo |
| `includes/.htaccess` | Blocks direct browser access to include files |

`config/app.php` was extended with CDN constants for Bootstrap 5.3.3 and Font Awesome 6.5.2. No database fields or file names were changed.

## 2. Algorithm 1 — Login Authentication (as implemented)

| Step | Implementation |
|---|---|
| 1. User enters username and password | `login.php` form (field accepts username **or** email) |
| 2. System validates the input | Non-empty fields, length limits, CSRF token, lockout check (`login_is_locked()`) |
| 3. System searches the database | `SELECT ... FROM users WHERE username = ? OR email = ?` (prepared statement) |
| 4. System verifies the hashed password | `password_verify()`; the account must also be `active` |
| 5. System identifies the user's role | `role` column, reloaded from the database on every request |
| 6. System creates a secure session | `session_regenerate_id(true)`, new CSRF token, browser signature, last activity time |
| 7. Redirect to the correct dashboard | `dashboard_path($role)` → `admin/`, `supervisor/`, or `employee/dashboard.php` |
| 8. If invalid, display an error | A generic "Invalid username or password." that never says which part was wrong |

## 3. Security Measures

| Threat | Protection |
|---|---|
| Stolen database | bcrypt hashes (`password_hash`), automatic rehash; remember-me validators stored as SHA-256 |
| SQL injection | PDO prepared statements with emulation turned off |
| XSS | Every output passes through `e()` (`htmlspecialchars`) |
| CSRF | Token on every POST form, including login and logout; checked with `hash_equals` |
| Brute force | After 5 failed attempts (per IP or username) within 15 minutes, login is locked (`settings` table) |
| Username discovery | Same message for an unknown user and a wrong password; a dummy hash check equalizes timing |
| Session fixation | New session ID on login, strict mode, cookie-only sessions |
| Session hijacking | HttpOnly + SameSite cookies, browser-signature check, ID rotated every 15 minutes |
| Idle sessions | Automatic logout after 30 minutes of inactivity (`session_timeout_minutes`) |
| Deactivated users | User status is re-checked on every request, so a deactivated user is logged out immediately |
| Stolen remember-me cookie | Single-use tokens; a mismatched validator signs the user out of every device |
| Back button after logout | `Cache-Control: no-store` on protected pages |
| Clickjacking / MIME sniffing | `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff` |
| Direct URL access by the wrong role | `require_role()` on the server returns 403 and logs the attempt |

New activity log actions: `login_failed`, `unauthorized_access`, `security`.

## 4. Protecting a Page (pattern for all later phases)

```php
<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_role('supervisor');          // or require_role('admin', 'supervisor');
// ... page code ...
// In forms:          <?= csrf_field() ?>
// When processing:   if (is_post()) { require_csrf(); ... }
```

## 5. Phase 2 Test Checklist

Start Apache and MySQL, then open `http://localhost/eodb-taskflow/`.

| # | Test | Steps | Expected Result |
|---|---|---|---|
| 1 | Redirect to login | Open `/eodb-taskflow/` while logged out | Login page |
| 2 | Empty fields | Click Log In with both fields empty | Inline "Please enter..." messages; form not submitted |
| 3 | Wrong password | `admin` / `wrong` | "Invalid username or password." |
| 4 | Unknown user | `nobody` / `abc` | The same message as test 3 |
| 5 | Admin login | `admin` / `Admin123!` | Administrator Dashboard |
| 6 | Supervisor login | `supervisor` / `Supervisor123!` | Supervisor Dashboard |
| 7 | Employee login | `employee` / `Employee123!` | Employee Dashboard |
| 8 | Login with email | `employee@rhumulanay.test` / `Employee123!` | Employee Dashboard |
| 9 | Inactive account | `lfernandez` / `Employee123!` | "Your account is inactive..." |
| 10 | Show/hide password | Click the eye icon | Password becomes visible, then hidden again |
| 11 | Unauthorized access | Logged in as employee, open `/eodb-taskflow/admin/dashboard.php` | 403 Access Denied |
| 12 | Unauthorized access | Logged in as supervisor, open the admin dashboard | 403 Access Denied |
| 13 | Not logged in | Log out, then open `/eodb-taskflow/supervisor/dashboard.php` | Redirected to login |
| 14 | Include protection | Open `/eodb-taskflow/includes/auth.php` and `/eodb-taskflow/config/database.php` | 403 Forbidden (Apache) |
| 15 | Logout | Click Logout | Login page with "logged out successfully" |
| 16 | Back button | After logout, press the browser Back button | Login page, not the dashboard |
| 17 | Lockout | Enter a wrong password 5 times | "Too many failed login attempts..." even with the correct password |
| 18 | Remember me | Log in with "Keep me signed in", close the whole browser, reopen `/eodb-taskflow/` | Logged in automatically |
| 19 | Session timeout | Set `session_timeout_minutes` to `5` in the `settings` table, log in, wait 6 minutes, refresh | Login page with "session expired" (without remember me) |
| 20 | Deactivate while logged in | Log in as `areyes`; in phpMyAdmin set that user's status to `inactive`; refresh | Logged out with the "no longer active" message |
| 21 | SQL injection | Username `' OR '1'='1` and any password | "Invalid username or password." |
| 22 | XSS | Username `<script>alert(1)</script>` | Text is shown escaped in the field; no popup |
| 23 | Activity log | In phpMyAdmin: `SELECT * FROM activity_logs ORDER BY id DESC;` | login, login_failed, logout, unauthorized_access rows |

**To clear a lockout during testing:** `DELETE FROM login_attempts;`
