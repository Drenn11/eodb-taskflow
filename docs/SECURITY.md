# Security Measures – EODB-TaskFlow

Each requirement from the specification (section 25) with how it is implemented and how it was verified. Test numbers refer to the automated final test in [TESTING_CHECKLIST.md](TESTING_CHECKLIST.md).

## 1. Requirements and implementation

| Requirement | Implementation | Verified by |
|---|---|---|
| **Password hashing** | `password_hash()` (bcrypt, `$2y$`, cost 10) on add user, reset, profile change, and setup; `password_verify()` on login; `password_needs_rehash()` upgrades old hashes automatically. Sample passwords are inserted as a non-password marker and hashed by `setup.php`, so the SQL file contains no passwords. | All passwords are 60-character `$2y$` hashes; plain-text check |
| **Prepared statements / SQL injection protection** | Every query that uses input goes through PDO prepared statements with real server-side preparation (`ATTR_EMULATE_PREPARES = false`). Dynamic SQL pieces (WHERE clauses, ORDER BY, LIMIT) are built only from fixed text, allow-lists, and integers. LIKE wildcards in search text are escaped. | `' OR '1'='1` login, search, filter value, and `id=1 OR 1=1` tests; source scan |
| **XSS protection** | All output passes through `e()` (`htmlspecialchars`, `ENT_QUOTES`, UTF-8). Chart data is embedded with `JSON_HEX_TAG` etc. A **Content Security Policy** allows scripts only from this system, so injected scripts would not run even if they reached a page. | Stored XSS in task title, reflected XSS in search; source scan of every `<?=` output |
| **Session authentication** | Hardened session: strict mode, cookies only, `HttpOnly`, `SameSite=Lax`, custom session name; new session ID on login (prevents fixation) and every 15 minutes; browser-signature check; 30-minute inactivity timeout (configurable); the user record is re-read on every request, so deactivation or a role change takes effect on the next click. | Cookie flags; deactivated-user sign-out; logout tests |
| **Role-based authorization** | `require_role()` / `require_login()` at the top of every page; 403 page and **Blocked Access** log entry for wrong roles; employees can only read and update tasks assigned to them; users can only see and change their own notifications. | 22 pages × 3 roles + anonymous access matrix; another employee's task; another user's notification |
| **Input validation** | Server-side validation for every form (required fields, formats, lengths, allowed values, real dates, unique username/email/Employee ID, password policy, progress/status consistency). Browser checks are only a convenience. | Add-user validation test; task create/update validation |
| **CSRF protection** | 64-character random token per session in every POST form; `require_csrf()` with `hash_equals()`; HTTP 400 when missing or wrong. Logout, delete, deactivate, mark-as-read are POST-only. | Delete task, add user, mark-as-read without token |
| **Secure logout** | POST with CSRF token only; clears session data, cookie, and server file; deletes the remember-me token; no-cache headers so the Back button does not show protected pages. | Logout, Back-button, GET-logout tests |
| **Access restrictions** | `config/`, `includes/`, `cron/`, `docs/` deny all web access; `.sql`, `.md`, hidden and backup files blocked; no folder listings; uploads folder cannot run scripts; `setup.php` only from the local computer; `cron/run_checks.php` only from the command line. | 8 direct-access tests (403) |
| **Safe error handling** | `APP_DEBUG = false`: errors go to the PHP error log, never to the screen. Database connection errors show a generic message. Friendly 403/404 pages without server details. Logging and notification failures never stop the user's action. | 404 test; credentials never in pages |
| **Never expose database credentials** | Credentials only in `config/database.php` (blocked from the web, never echoed). | Direct access test; page content check |
| **Never store plain-text passwords** | See password hashing; `setup.php` also repairs passwords that were typed into phpMyAdmin. | Database check |
| **Never trust user input** | All input read through helpers that check type and length (`get_str`, `post_int`, `filter_var`); IDs must be positive integers; uploaded files are checked by content. | Validation and injection tests |

## 2. Additional protections

| Protection | Details |
|---|---|
| Brute-force lockout | 5 failed logins (by username or computer) within 15 minutes → temporary lockout; both values configurable in Settings |
| No account discovery | Same message for unknown username and wrong password; a dummy hash check keeps the response time similar |
| Remember me | Random selector + validator; only the **SHA-256 hash** of the validator is stored; each token is **single-use**; a wrong validator for a valid selector (stolen cookie) signs that user out of every remembered device and logs a security event; tokens are removed on logout, password change, role change, and deactivation |
| Security headers | `Content-Security-Policy`, `X-Frame-Options: DENY` (clickjacking), `X-Content-Type-Options: nosniff`, `Referrer-Policy: same-origin`, `Permissions-Policy` |
| File uploads | JPG/PNG/WEBP only, checked with `fileinfo` and `getimagesize`; max 2 MB and 4000 px; random server-chosen file name; scripts and HTML blocked in the upload folder |
| CSV export | Cells starting with `=`, `+`, `-`, `@` are prefixed with `'` (formula injection) |
| Open redirect | "Return to page" values must be a page inside the system (`safe_return_path()`) |
| Data integrity | Foreign keys, CHECK constraints on progress, unique keys; users are deactivated instead of deleted; completed tasks cannot be edited or deleted; multi-step actions run in database transactions |
| Audit trail | Read-only activity log: logins, failed logins, logouts, all task and user changes, settings changes, reports, blocked access, security events |
| Idle sessions | The once-a-minute notification counter is a "passive" request and does not keep an idle session alive |
| Third-party libraries | Bootstrap, Font Awesome, and Chart.js are served locally; every file was verified against the hashes published by jsDelivr / cdnjs / npm |

## 3. Code review summary (Phase 10)

An automated scan of all 65 PHP files checked for:

| Check | Result |
|---|---|
| Pages without `require_login()` / `require_role()` | None (only the public login, logout, index, 403/404 pages and the unread-count endpoint, which checks login itself and returns 401) |
| POST handlers without a CSRF check | None |
| POST forms without a CSRF token | None |
| Unescaped variable output | None — all flagged lines print fixed text or numbers |
| SQL with PHP variables | Only fixed fragments, allow-listed column names, and integers; all user values are bound parameters |
| Dangerous functions (`eval`, `exec`, `system`, `unserialize`, …) | None |

## 4. Recommendations for a real deployment

These are outside the scope of a local XAMPP demonstration but should be done if the system is used on a network:

1. Set a **password for the MySQL `root` account** (or create a dedicated MySQL user with rights only on `eodb_taskflow`) and update `config/database.php`.
2. Use **HTTPS**; the session and remember-me cookies then automatically get the `Secure` flag.
3. Replace the demo passwords and remove unused sample accounts.
4. Restrict phpMyAdmin to the local computer (XAMPP default) and keep XAMPP updated.
5. Back up the database regularly (phpMyAdmin → Export).
