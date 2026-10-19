<?php
/**
 * EODB-TaskFlow - Authentication, sessions, and role-based access
 *
 * Include this file at the top of EVERY page:
 *
 *   require_once __DIR__ . '/../includes/auth.php';
 *   require_role('supervisor');            // or require_login();
 *
 * Including it will:
 *   - load the configuration, database connection, helpers, and CSRF helpers
 *   - start a hardened PHP session
 *   - enforce the inactivity timeout and session integrity checks
 *   - reload the logged-in user from the database (so deactivation and role
 *     changes take effect immediately)
 *   - restore a login from a valid "Remember me" cookie
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/csrf.php';

const REMEMBER_COOKIE = 'eodb_remember';
const SESSION_REGENERATE_SECONDS = 900; // new session ID every 15 minutes

// =============================================================================
// Session handling
// =============================================================================

function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');   // reject session IDs not created by the server
    ini_set('session.use_only_cookies', '1');  // never accept session IDs from the URL
    ini_set('session.use_trans_sid', '0');

    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => 0,                // ends when the browser closes
        'path'     => BASE_URL . '/',
        'domain'   => '',
        'secure'   => is_https(),
        'httponly' => true,             // not readable by JavaScript
        'samesite' => 'Lax',
    ]);

    session_start();
}

/** Destroys the current session completely (data, cookie, and server file). */
function end_session(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $params['path'],
            'domain'   => $params['domain'],
            'secure'   => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Lax',
        ]);
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }

    $GLOBALS['eodb_current_user'] = null;
}

function user_agent_hash(): string
{
    return hash('sha256', (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
}

function session_timeout_seconds(): int
{
    $minutes = (int) get_setting('session_timeout_minutes', SESSION_TIMEOUT_MINUTES);
    return max(5, $minutes) * 60;
}

// =============================================================================
// Current user
// =============================================================================

/** The logged-in user's record, or null when nobody is logged in. */
function current_user(): ?array
{
    return $GLOBALS['eodb_current_user'] ?? null;
}

function current_user_id(): int
{
    return (int) (current_user()['id'] ?? 0);
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

/** True if the logged-in user has one of the given roles. */
function has_role(string ...$roles): bool
{
    $user = current_user();
    return $user !== null && in_array($user['role'], $roles, true);
}

function fetch_user_by_id(int $id): ?array
{
    $stmt = db()->prepare(
        'SELECT u.id, u.employee_id, u.full_name, u.username, u.email, u.role,
                u.department_id, d.name AS department, u.position, u.status,
                u.profile_photo, u.last_login_at, u.created_at
           FROM users u
           LEFT JOIN departments d ON d.id = u.department_id
          WHERE u.id = ?
          LIMIT 1'
    );
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    return $user ?: null;
}

// =============================================================================
// Request bootstrap: validates the session on every page load
// =============================================================================

function enforce_session_rules(): void
{
    $GLOBALS['eodb_current_user'] = null;
    $GLOBALS['eodb_logout_reason'] = '';

    if (!empty($_SESSION['user_id'])) {
        $now = time();

        // Inactivity timeout
        if ($now - (int) ($_SESSION['last_activity'] ?? 0) > session_timeout_seconds()) {
            log_activity((int) $_SESSION['user_id'], 'logout', 'Session expired after inactivity.');
            end_session();
            start_secure_session();
            $GLOBALS['eodb_logout_reason'] = 'timeout';
        }
        // Session hijacking check: the browser must stay the same
        elseif (!hash_equals((string) ($_SESSION['ua_hash'] ?? ''), user_agent_hash())) {
            log_activity((int) $_SESSION['user_id'], 'security', 'Session ended: browser signature changed.');
            end_session();
            start_secure_session();
            $GLOBALS['eodb_logout_reason'] = 'session';
        } else {
            $user = fetch_user_by_id((int) $_SESSION['user_id']);

            if ($user === null || $user['status'] !== 'active') {
                // Account was deactivated or removed while logged in
                if ($user !== null) {
                    delete_remember_tokens_for_user((int) $user['id']);
                }
                clear_remember_cookie();
                end_session();
                start_secure_session();
                $GLOBALS['eodb_logout_reason'] = 'inactive';
            } else {
                $GLOBALS['eodb_current_user'] = $user;

                // Background requests (e.g. the notification counter that refreshes
                // every minute) must not keep an idle session alive.
                $passive = defined('EODB_PASSIVE_REQUEST');
                if (!$passive) {
                    $_SESSION['last_activity'] = $now;
                }

                // Periodic session ID rotation limits the value of a stolen ID
                if (!$passive && $now - (int) ($_SESSION['last_regenerated'] ?? 0) > SESSION_REGENERATE_SECONDS) {
                    session_regenerate_id(true);
                    $_SESSION['last_regenerated'] = $now;
                }
            }
        }
    }

    // Not logged in: try the "Remember me" cookie
    if ($GLOBALS['eodb_current_user'] === null && isset($_COOKIE[REMEMBER_COOKIE])) {
        attempt_remember_login();
    }
}

// =============================================================================
// ALGORITHM 1 - LOGIN AUTHENTICATION
// =============================================================================

/**
 * Authenticates a user by username or email.
 *
 * @return true|string  true on success, otherwise an error message for the user
 */
function attempt_login(string $login, string $password, bool $remember): bool|string
{
    $ip = client_ip();
    $loginKey = mb_strtolower($login);

    // Step 2: Validate the input
    if ($login === '' || $password === '') {
        return 'Please enter your username or email and your password.';
    }
    if (mb_strlen($login) > 150 || strlen($password) > 255) {
        return 'Invalid username or password.';
    }

    // Brute-force protection: temporary lockout after repeated failures
    if (login_is_locked($loginKey, $ip)) {
        $minutes = (int) get_setting('login_lockout_minutes', LOGIN_LOCKOUT_MINUTES);
        return "Too many failed login attempts. Please wait up to {$minutes} minute(s) and try again.";
    }

    // Step 3: Search the database (prepared statement prevents SQL injection)
    $stmt = db()->prepare(
        'SELECT id, full_name, username, password, role, status
           FROM users
          WHERE username = ? OR email = ?
          LIMIT 1'
    );
    $stmt->execute([$login, $login]);
    $user = $stmt->fetch();

    // Step 4: Verify the hashed password
    if ($user === false) {
        // Run a hash check anyway so a missing account takes about as long as
        // a wrong password (prevents discovering usernames by timing).
        password_verify($password, password_hash('dummy-password-for-timing', PASSWORD_DEFAULT));
        record_failed_login($loginKey, $ip);
        log_activity(null, 'login_failed', 'Failed login attempt for unknown account "' . $login . '".');
        return 'Invalid username or password.';
    }

    if (!password_verify($password, $user['password'])) {
        record_failed_login($loginKey, $ip);
        log_activity((int) $user['id'], 'login_failed', 'Failed login attempt (incorrect password).');
        return 'Invalid username or password.';
    }

    if ($user['status'] !== 'active') {
        log_activity((int) $user['id'], 'login_failed', 'Login blocked: account is inactive.');
        return 'Your account is inactive. Please contact the system administrator.';
    }

    // Upgrade the hash automatically if PHP's default algorithm/cost changes
    if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
        $rehash = db()->prepare('UPDATE users SET password = ?, updated_at = updated_at WHERE id = ?');
        $rehash->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
    }

    clear_login_attempts($loginKey);

    // Steps 5-6: Identify the role and create a secure session
    $fullUser = fetch_user_by_id((int) $user['id']);
    if ($fullUser === null) {
        return 'Invalid username or password.';
    }
    log_in_user($fullUser, $remember, 'password');

    // Step 7 (redirect to the role's dashboard) is done by login.php
    return true;
}

/** Creates the authenticated session for a verified user. */
function log_in_user(array $user, bool $remember, string $method): void
{
    // Prevent session fixation: discard old data and issue a new session ID
    $_SESSION = [];
    session_regenerate_id(true);

    $now = time();
    $_SESSION['user_id']          = (int) $user['id'];
    $_SESSION['login_time']       = $now;
    $_SESSION['last_activity']    = $now;
    $_SESSION['last_regenerated'] = $now;
    $_SESSION['ua_hash']          = user_agent_hash();
    regenerate_csrf_token();

    // updated_at = updated_at keeps the "last modified" date of the account unchanged
    $stmt = db()->prepare('UPDATE users SET last_login_at = NOW(), updated_at = updated_at WHERE id = ?');
    $stmt->execute([$user['id']]);

    if ($remember) {
        issue_remember_token((int) $user['id']);
    }

    $GLOBALS['eodb_current_user'] = fetch_user_by_id((int) $user['id']);

    log_activity(
        (int) $user['id'],
        'login',
        $method === 'remember'
            ? 'Logged in automatically from a remembered device.'
            : 'Logged in as ' . role_label($user['role']) . '.'
    );
}

/** Secure logout: removes the remember-me token, logs the action, and destroys the session. */
function logout_user(): void
{
    $user = current_user();
    if ($user !== null) {
        log_activity((int) $user['id'], 'logout', 'Logged out.');
    }

    $parts = parse_remember_cookie();
    if ($parts !== null) {
        $stmt = db()->prepare('DELETE FROM remember_tokens WHERE selector = ?');
        $stmt->execute([$parts['selector']]);
    }
    clear_remember_cookie();
    end_session();
}

// =============================================================================
// Failed-login tracking (brute-force protection)
// =============================================================================

function login_is_locked(string $loginKey, string $ip): bool
{
    $max     = max(1, (int) get_setting('login_max_attempts', LOGIN_MAX_ATTEMPTS));
    $minutes = max(1, (int) get_setting('login_lockout_minutes', LOGIN_LOCKOUT_MINUTES));

    $stmt = db()->prepare(
        'SELECT COUNT(*) FROM login_attempts
          WHERE (ip_address = ? OR username = ?)
            AND attempted_at > NOW() - INTERVAL ? MINUTE'
    );
    $stmt->execute([$ip, $loginKey, $minutes]);

    return (int) $stmt->fetchColumn() >= $max;
}

function record_failed_login(string $loginKey, string $ip): void
{
    $stmt = db()->prepare('INSERT INTO login_attempts (username, ip_address) VALUES (?, ?)');
    $stmt->execute([mb_substr($loginKey, 0, 150), $ip]);

    // Housekeeping: old attempts are no longer needed
    db()->exec('DELETE FROM login_attempts WHERE attempted_at < NOW() - INTERVAL 1 DAY');
}

function clear_login_attempts(string $loginKey): void
{
    $stmt = db()->prepare('DELETE FROM login_attempts WHERE username = ?');
    $stmt->execute([$loginKey]);
}

// =============================================================================
// "Remember me" (selector + hashed validator)
// The cookie holds "selector:validator". The database stores only the SHA-256
// hash of the validator, so a leaked database cannot be used to log in.
// =============================================================================

function remember_cookie_options(int $expires): array
{
    return [
        'expires'  => $expires,
        'path'     => BASE_URL . '/',
        'domain'   => '',
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

function issue_remember_token(int $userId): void
{
    $selector  = bin2hex(random_bytes(12)); // 24 hex characters
    $validator = bin2hex(random_bytes(32)); // 64 hex characters
    $expires   = time() + REMEMBER_ME_DAYS * 86400;

    db()->exec('DELETE FROM remember_tokens WHERE expires_at < NOW()');

    $stmt = db()->prepare(
        'INSERT INTO remember_tokens (user_id, selector, hashed_validator, expires_at) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$userId, $selector, hash('sha256', $validator), date('Y-m-d H:i:s', $expires)]);

    setcookie(REMEMBER_COOKIE, $selector . ':' . $validator, remember_cookie_options($expires));
}

/** @return array{selector: string, validator: string}|null */
function parse_remember_cookie(): ?array
{
    $raw = $_COOKIE[REMEMBER_COOKIE] ?? null;
    if (!is_string($raw) || !preg_match('/^([a-f0-9]{24}):([a-f0-9]{64})$/', $raw, $m)) {
        return null;
    }
    return ['selector' => $m[1], 'validator' => $m[2]];
}

function clear_remember_cookie(): void
{
    if (isset($_COOKIE[REMEMBER_COOKIE]) && !headers_sent()) {
        setcookie(REMEMBER_COOKIE, '', remember_cookie_options(time() - 3600));
    }
    unset($_COOKIE[REMEMBER_COOKIE]);
}

function delete_remember_tokens_for_user(int $userId): void
{
    $stmt = db()->prepare('DELETE FROM remember_tokens WHERE user_id = ?');
    $stmt->execute([$userId]);
}

function attempt_remember_login(): bool
{
    $parts = parse_remember_cookie();
    if ($parts === null) {
        clear_remember_cookie();
        return false;
    }

    $stmt = db()->prepare(
        'SELECT id, user_id, hashed_validator FROM remember_tokens
          WHERE selector = ? AND expires_at > NOW()
          LIMIT 1'
    );
    $stmt->execute([$parts['selector']]);
    $token = $stmt->fetch();

    if ($token === false) {
        clear_remember_cookie();
        return false;
    }

    if (!hash_equals($token['hashed_validator'], hash('sha256', $parts['validator']))) {
        // Correct selector but wrong validator: the cookie may have been stolen.
        // Sign this user out of every remembered device.
        delete_remember_tokens_for_user((int) $token['user_id']);
        clear_remember_cookie();
        log_activity(
            (int) $token['user_id'],
            'security',
            'Invalid remember-me token detected. All remembered devices were signed out.'
        );
        return false;
    }

    // Each token is single-use; log_in_user() issues a fresh one
    $delete = db()->prepare('DELETE FROM remember_tokens WHERE id = ?');
    $delete->execute([$token['id']]);

    $user = fetch_user_by_id((int) $token['user_id']);
    if ($user === null || $user['status'] !== 'active') {
        clear_remember_cookie();
        return false;
    }

    log_in_user($user, true, 'remember');
    return true;
}

// =============================================================================
// Access control (use at the top of pages)
// =============================================================================

/** Sends visitors who are not logged in to the login page. */
function require_login(): void
{
    if (!is_logged_in()) {
        $reason = $GLOBALS['eodb_logout_reason'] ?? '';
        redirect('login.php' . ($reason !== '' ? '?reason=' . urlencode($reason) : ''));
    }
    send_no_cache_headers();
}

/**
 * Allows only the listed roles. Anyone else gets HTTP 403, and the attempt
 * is written to the activity log.
 *
 * Example: require_role('admin');  require_role('admin', 'supervisor');
 */
function require_role(string ...$roles): void
{
    require_login();

    if (!has_role(...$roles)) {
        deny_access();
    }
}

function deny_access(): void
{
    $user = current_user();
    log_activity(
        $user ? (int) $user['id'] : null,
        'unauthorized_access',
        'Blocked access to ' . mb_substr((string) ($_SERVER['REQUEST_URI'] ?? ''), 0, 300)
            . ($user ? ' (role: ' . $user['role'] . ')' : '')
    );

    http_response_code(403);
    require BASE_PATH . '/unauthorized.php';
    exit;
}

// =============================================================================
// Run on every request
// =============================================================================

start_secure_session();
send_security_headers();
enforce_session_rules();

// Automatic overdue detection while the system is in use (see includes/scheduler.php).
// A failure here is logged but never stops the page.
if (is_logged_in()) {
    try {
        require_once __DIR__ . '/scheduler.php';
        run_scheduled_checks();
    } catch (Throwable $e) {
        error_log('[EODB-TaskFlow] Scheduled checks failed: ' . $e->getMessage());
    }
}
