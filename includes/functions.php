<?php
/**
 * EODB-TaskFlow - General helper functions
 *
 * Output escaping, URLs/redirects, input reading, flash messages,
 * system settings, activity logging, and formatting helpers.
 * Loaded automatically by includes/auth.php.
 */

declare(strict_types=1);

// ---------------------------------------------------------------------------
// Output escaping (XSS protection)
// ---------------------------------------------------------------------------

/** Escapes any value for safe output inside HTML text or attributes. */
function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ---------------------------------------------------------------------------
// URLs and redirects
// ---------------------------------------------------------------------------

/** Builds an application URL, e.g. url('admin/users.php') -> /eodb-taskflow/admin/users.php */
function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

/**
 * Builds a URL to a file inside assets/. The file's modification time is added
 * (?v=...) so browsers load the new version right after a file is updated.
 */
function asset(string $path): string
{
    $path = ltrim($path, '/');
    $file = BASE_PATH . '/assets/' . $path;
    $version = is_file($file) ? '?v=' . filemtime($file) : '';
    return url('assets/' . $path) . $version;
}

/** Redirects to an application path and stops the script. */
function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

// ---------------------------------------------------------------------------
// Request input (never trust user input - always read through these helpers)
// ---------------------------------------------------------------------------

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/** Returns a trimmed string from $_POST, cut to $maxLength characters. */
function post_str(string $key, int $maxLength = 255): string
{
    $value = $_POST[$key] ?? '';
    if (!is_string($value)) {
        return '';
    }
    return mb_substr(trim($value), 0, $maxLength);
}

/** Returns a trimmed string from $_GET, cut to $maxLength characters. */
function get_str(string $key, int $maxLength = 255): string
{
    $value = $_GET[$key] ?? '';
    if (!is_string($value)) {
        return '';
    }
    return mb_substr(trim($value), 0, $maxLength);
}

/** Returns a positive integer from $_GET, or 0 if missing/invalid. */
function get_int(string $key): int
{
    $value = filter_var($_GET[$key] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $value === false || $value === null ? 0 : (int) $value;
}

/** Returns a positive integer from $_POST, or 0 if missing/invalid. */
function post_int(string $key): int
{
    $value = filter_var($_POST[$key] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $value === false || $value === null ? 0 : (int) $value;
}

/** The client's IP address. Proxy headers are ignored because they can be forged. */
function client_ip(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

function is_https(): bool
{
    return !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
}

// ---------------------------------------------------------------------------
// Flash messages (one-time messages shown after a redirect)
// ---------------------------------------------------------------------------

/** @param string $type Bootstrap alert type: success, danger, warning, info */
function set_flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/** Returns and clears all pending flash messages. */
function get_flashes(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return is_array($messages) ? $messages : [];
}

// ---------------------------------------------------------------------------
// System settings (settings table)
// ---------------------------------------------------------------------------

/** Reads a value from the settings table (cached for the current request). */
function get_setting(string $key, string|int|null $default = null): string|int|null
{
    static $settings = null;

    if ($settings === null) {
        $settings = [];
        try {
            foreach (db()->query('SELECT setting_key, setting_value FROM settings') as $row) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        } catch (PDOException $e) {
            error_log('[EODB-TaskFlow] Could not load settings: ' . $e->getMessage());
        }
    }

    return $settings[$key] ?? $default;
}

/** System name shown in the interface (Settings → System name), defaulting to APP_NAME. */
function app_name(): string
{
    return (string) get_setting('system_name', APP_NAME);
}

/** Office name shown on the login page and printed reports (Settings → Office name). */
function office_name(): string
{
    return (string) get_setting('office_name', APP_OFFICE);
}

/**
 * Accepts only a relative page inside this application (e.g. "notifications/index.php?page=2"),
 * so a "return" parameter can never send users to another website.
 */
function safe_return_path(string $path, string $default): string
{
    return preg_match('#^[a-z_]+(/[a-z_]+)*\.php(\?[A-Za-z0-9_=&%.\-]*)?$#', $path) ? $path : $default;
}

// ---------------------------------------------------------------------------
// Activity log
// ---------------------------------------------------------------------------

/**
 * Records an action in activity_logs. A logging failure is written to the
 * PHP error log but never stops the user's action.
 */
function log_activity(?int $userId, string $action, string $description): void
{
    try {
        $stmt = db()->prepare(
            'INSERT INTO activity_logs (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([
            $userId,
            mb_substr($action, 0, 50),
            mb_substr($description, 0, 500),
            PHP_SAPI === 'cli' ? null : client_ip(),
        ]);
    } catch (PDOException $e) {
        error_log('[EODB-TaskFlow] Activity log failed: ' . $e->getMessage());
    }
}

// ---------------------------------------------------------------------------
// Roles
// ---------------------------------------------------------------------------

function role_label(string $role): string
{
    return match ($role) {
        'admin'      => 'Administrator',
        'supervisor' => 'Supervisor',
        'employee'   => 'Employee',
        default      => 'Unknown',
    };
}

/** The dashboard page for a role, relative to BASE_URL. */
function dashboard_path(string $role): string
{
    return match ($role) {
        'admin'      => 'admin/dashboard.php',
        'supervisor' => 'supervisor/dashboard.php',
        default      => 'employee/dashboard.php',
    };
}

// ---------------------------------------------------------------------------
// Formatting
// ---------------------------------------------------------------------------

function format_date(?string $value, string $format = 'M d, Y'): string
{
    if ($value === null || $value === '') {
        return '—';
    }
    $time = strtotime($value);
    return $time === false ? '—' : date($format, $time);
}

function format_datetime(?string $value): string
{
    return format_date($value, 'M d, Y h:i A');
}

/** Two-letter initials for avatars, e.g. "Juan M. Dela Cruz" -> "JC" (middle initials are skipped). */
function initials(string $name): string
{
    $parts = preg_split('/\s+/u', trim($name)) ?: [];
    $parts = array_values(array_filter($parts, fn ($p) => !str_ends_with($p, '.') || mb_strlen($p) > 2));
    if (!$parts) {
        return '?';
    }
    $first = mb_substr($parts[0], 0, 1);
    $last  = count($parts) > 1 ? mb_substr($parts[count($parts) - 1], 0, 1) : '';
    return mb_strtoupper($first . $last);
}

/** URL of a user's profile photo, or null when they have none. */
function profile_photo_url(?string $photo): ?string
{
    if ($photo === null || $photo === '' || basename($photo) !== $photo) {
        return null;
    }
    return is_file(PROFILE_UPLOAD_DIR . '/' . $photo) ? asset('images/profiles/' . $photo) : null;
}

// ---------------------------------------------------------------------------
// Badges (status, priority, role)
// ---------------------------------------------------------------------------

function task_status_badge(string $status): string
{
    $class = match ($status) {
        'Completed'   => 'badge-status-completed',
        'In Progress' => 'badge-status-progress',
        'Overdue'     => 'badge-status-overdue',
        default       => 'badge-status-pending',
    };
    return '<span class="badge ' . $class . '">' . e($status) . '</span>';
}

function priority_badge(string $priority): string
{
    $class = match ($priority) {
        'Urgent' => 'badge-priority-urgent',
        'High'   => 'badge-priority-high',
        'Medium' => 'badge-priority-medium',
        default  => 'badge-priority-low',
    };
    return '<span class="badge ' . $class . '"><i class="fa-solid fa-flag" aria-hidden="true"></i>' . e($priority) . '</span>';
}

function role_badge(string $role): string
{
    $class = match ($role) {
        'admin'      => 'text-bg-dark',
        'supervisor' => 'text-bg-secondary',
        default      => 'text-bg-light border',
    };
    return '<span class="badge ' . $class . '">' . e(role_label($role)) . '</span>';
}

function account_status_badge(string $status): string
{
    return $status === 'active'
        ? '<span class="badge badge-account-active">Active</span>'
        : '<span class="badge badge-account-inactive">Inactive</span>';
}

/** Readable label for an activity_logs.action code. */
function action_label(string $action): string
{
    return match ($action) {
        'login'               => 'Login',
        'login_failed'        => 'Failed Login',
        'logout'              => 'Logout',
        'create_task'         => 'Create Task',
        'assign_task'         => 'Assign Task',
        'update_task'         => 'Update Task',
        'complete_task'       => 'Complete Task',
        'delete_task'         => 'Delete Task',
        'add_user'            => 'Add User',
        'edit_user'           => 'Edit User',
        'deactivate_user'     => 'Deactivate User',
        'activate_user'       => 'Activate User',
        'update_profile'      => 'Update Profile',
        'update_settings'     => 'Update Settings',
        'generate_report'     => 'Generate Report',
        'unauthorized_access' => 'Blocked Access',
        'security'            => 'Security',
        'system'              => 'System',
        default               => ucwords(str_replace('_', ' ', $action)),
    };
}

// ---------------------------------------------------------------------------
// Task status (read-only)
// ---------------------------------------------------------------------------

/**
 * SQL expression for a task's current status using the Algorithm 3 rule:
 * a task that is not completed and whose deadline has passed counts as Overdue,
 * even before the stored status has been refreshed.
 */
function effective_status_sql(string $alias = 't'): string
{
    return "(CASE WHEN {$alias}.status <> 'Completed' AND {$alias}.deadline < CURDATE()"
        . " THEN 'Overdue' ELSE {$alias}.status END)";
}

// ---------------------------------------------------------------------------
// Search and pagination
// ---------------------------------------------------------------------------

/** Wraps a search term for a LIKE '%term%' match, escaping % and _ wildcards. */
function like_term(string $term): string
{
    return '%' . addcslashes($term, '%_\\') . '%';
}

function records_per_page(): int
{
    return max(5, min(100, (int) get_setting('records_per_page', 10)));
}

/** @return array{total:int, per_page:int, page:int, pages:int, offset:int} */
function paginate(int $total, int $perPage, int $page): array
{
    $perPage = max(1, $perPage);
    $pages   = max(1, (int) ceil($total / $perPage));
    $page    = min(max(1, $page), $pages);

    return [
        'total'    => $total,
        'per_page' => $perPage,
        'page'     => $page,
        'pages'    => $pages,
        'offset'   => ($page - 1) * $perPage,
    ];
}

/** Bootstrap pagination links that keep the current filters in the URL. */
function render_pagination(array $pagination, array $query = []): string
{
    if ($pagination['pages'] <= 1) {
        return '';
    }

    $link = function (int $page) use ($query): string {
        return '?' . http_build_query(array_merge($query, ['page' => $page]));
    };

    $current = $pagination['page'];
    $last    = $pagination['pages'];
    $start   = max(1, $current - 2);
    $end     = min($last, $current + 2);

    $html = '<nav aria-label="Pagination"><ul class="pagination pagination-sm mb-0">';
    $html .= '<li class="page-item' . ($current === 1 ? ' disabled' : '') . '">'
        . '<a class="page-link" href="' . e($link(max(1, $current - 1))) . '" aria-label="Previous">&laquo;</a></li>';

    for ($i = $start; $i <= $end; $i++) {
        $html .= '<li class="page-item' . ($i === $current ? ' active' : '') . '">'
            . '<a class="page-link" href="' . e($link($i)) . '">' . $i . '</a></li>';
    }

    $html .= '<li class="page-item' . ($current === $last ? ' disabled' : '') . '">'
        . '<a class="page-link" href="' . e($link(min($last, $current + 1))) . '" aria-label="Next">&raquo;</a></li>';
    $html .= '</ul></nav>';

    return $html;
}

/** "Showing 1 to 10 of 25 records" */
function pagination_summary(array $pagination, int $rowsOnPage): string
{
    if ($pagination['total'] === 0) {
        return 'No records found';
    }
    $from = $pagination['offset'] + 1;
    $to   = $pagination['offset'] + $rowsOnPage;
    return "Showing {$from} to {$to} of {$pagination['total']} record" . ($pagination['total'] === 1 ? '' : 's');
}

// ---------------------------------------------------------------------------
// Navigation
// ---------------------------------------------------------------------------

/**
 * Sidebar menu for a role. Pages that are not built yet (later phases) are
 * hidden automatically, so the menu never shows a broken link.
 *
 * Items are grouped under sidebar headings. Each role gets exactly the pages
 * its permissions allow (every page also checks the role itself).
 *
 * @return list<array{key:string, label:string, icon:string, path:string, group:string}>
 */
function sidebar_menu(string $role): array
{
    $menus = [
        'admin' => [
            ['dashboard',     'Dashboard',     'fa-gauge-high',          'admin/dashboard.php',      'Overview'],
            ['tasks',         'All Tasks',     'fa-list-check',          'admin/tasks.php',          'Task Management'],
            ['employees',     'Employees',     'fa-users',               'admin/users.php',          'People & Administration'],
            ['settings',      'Settings',      'fa-gear',                'admin/settings.php',       'People & Administration'],
            ['reports',       'Reports',       'fa-chart-column',        'reports/index.php',        'Reports'],
            ['activity_logs', 'Activity Logs', 'fa-clock-rotate-left',   'admin/activity_logs.php',  'Reports'],
            ['notifications', 'Notifications', 'fa-bell',                'notifications/index.php',  'Account'],
            ['profile',       'Profile',       'fa-id-badge',            'profile/index.php',        'Account'],
        ],
        'supervisor' => [
            ['dashboard',     'Dashboard',     'fa-gauge-high',          'supervisor/dashboard.php', 'Overview'],
            ['tasks',         'Tasks',         'fa-list-check',          'supervisor/tasks.php',     'Task Management'],
            ['create_task',   'Assign Task',   'fa-square-plus',         'supervisor/create_task.php', 'Task Management'],
            ['employees',     'Employees',     'fa-users',               'supervisor/employees.php', 'Task Management'],
            ['reports',       'Reports',       'fa-chart-column',        'reports/index.php',        'Reports'],
            ['notifications', 'Notifications', 'fa-bell',                'notifications/index.php',  'Account'],
            ['profile',       'Profile',       'fa-id-badge',            'profile/index.php',        'Account'],
        ],
        'employee' => [
            ['dashboard',     'Dashboard',     'fa-gauge-high',          'employee/dashboard.php',   'Overview'],
            ['tasks',         'My Tasks',      'fa-list-check',          'employee/my_tasks.php',    'My Work'],
            ['history',       'Task History',  'fa-clock-rotate-left',   'employee/history.php',     'My Work'],
            ['notifications', 'Notifications', 'fa-bell',                'notifications/index.php',  'Account'],
            ['profile',       'Profile',       'fa-id-badge',            'profile/index.php',        'Account'],
        ],
    ];

    $items = [];
    foreach ($menus[$role] ?? [] as [$key, $label, $icon, $path, $group]) {
        if (is_file(BASE_PATH . '/' . $path)) {
            $items[] = ['key' => $key, 'label' => $label, 'icon' => $icon, 'path' => $path, 'group' => $group];
        }
    }
    return $items;
}

/** True if an application page exists (used to hide links to later-phase pages). */
function page_exists(string $path): bool
{
    return is_file(BASE_PATH . '/' . ltrim($path, '/'));
}

// ---------------------------------------------------------------------------
// HTTP security headers
// ---------------------------------------------------------------------------

function send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header('Content-Security-Policy: ' . content_security_policy());
}

/**
 * Content Security Policy: the browser only runs scripts and loads files from
 * this system (and the CDNs when ASSET_SOURCE = 'cdn'). Injected <script> code
 * and inline event handlers would be blocked even if they reached a page.
 * 'unsafe-inline' is allowed for styles only (progress bar widths, print page size).
 */
function content_security_policy(): string
{
    $cdn = ASSET_SOURCE === 'cdn' ? ' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com' : '';

    return implode('; ', [
        "default-src 'self'",
        "script-src 'self'" . $cdn,
        "style-src 'self' 'unsafe-inline'" . $cdn,
        "font-src 'self' data:" . $cdn,
        "img-src 'self' data:",
        "connect-src 'self'",
        "object-src 'none'",
        "base-uri 'self'",
        "form-action 'self'",
        "frame-ancestors 'none'",
    ]);
}

/** Prevents the browser from caching protected pages (Back button after logout). */
function send_no_cache_headers(): void
{
    if (headers_sent()) {
        return;
    }
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}
