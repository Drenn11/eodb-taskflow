<?php
/**
 * EODB-TaskFlow - Application configuration
 *
 * Global constants and PHP runtime settings shared by every page.
 * Loaded before config/database.php.
 */

declare(strict_types=1);

// ---------------------------------------------------------------------------
// Application identity
// ---------------------------------------------------------------------------
define('APP_NAME', 'EODB-TaskFlow');
define('APP_FULL_NAME', 'Streamlined Employee Task and Productivity Monitoring System');
define('APP_OFFICE', 'Rural Health Unit - Mulanay');
define('APP_VERSION', '1.0.0');

// ---------------------------------------------------------------------------
// URL and paths
// BASE_URL must match the folder name inside C:\xampp\htdocs\
// e.g. http://localhost/eodb-taskflow/  ->  '/eodb-taskflow'
// ---------------------------------------------------------------------------
define('BASE_URL', '/eodb-taskflow');
define('BASE_PATH', dirname(__DIR__));
define('PROFILE_UPLOAD_DIR', BASE_PATH . '/assets/images/profiles');

// ---------------------------------------------------------------------------
// Environment
// APP_DEBUG = true shows PHP errors on screen (development only).
// Set to false for the demonstration/defense so users never see internals.
// ---------------------------------------------------------------------------
define('APP_DEBUG', false);

// ---------------------------------------------------------------------------
// Security defaults (can be overridden later by the `settings` table)
// ---------------------------------------------------------------------------
define('SESSION_NAME', 'EODBTASKFLOWSESSID');
define('SESSION_TIMEOUT_MINUTES', 30);
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_MINUTES', 15);
define('REMEMBER_ME_DAYS', 7);

// ---------------------------------------------------------------------------
// Task rules
// ---------------------------------------------------------------------------
define('TASK_STATUSES', ['Pending', 'In Progress', 'Completed', 'Overdue']);
define('TASK_PRIORITIES', ['Low', 'Medium', 'High', 'Urgent']);
define('USER_ROLES', ['admin', 'supervisor', 'employee']);
define('DEADLINE_REMINDER_DAYS', 2);

// ---------------------------------------------------------------------------
// Front-end libraries: Bootstrap 5.3.3, Font Awesome 6.5.2, Chart.js 4.4.1
//   'local' = files in assets/vendor/ (default; works without internet)
//   'cdn'   = load the same versions from public CDNs
// The constant names keep the CDN_ prefix used throughout the pages.
// ---------------------------------------------------------------------------
define('ASSET_SOURCE', 'local');

if (ASSET_SOURCE === 'cdn') {
    define('CDN_BOOTSTRAP_CSS', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css');
    define('CDN_BOOTSTRAP_JS', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js');
    define('CDN_FONTAWESOME_CSS', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css');
    define('CDN_CHARTJS', 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js');
} else {
    define('CDN_BOOTSTRAP_CSS', BASE_URL . '/assets/vendor/bootstrap/css/bootstrap.min.css');
    define('CDN_BOOTSTRAP_JS', BASE_URL . '/assets/vendor/bootstrap/js/bootstrap.bundle.min.js');
    define('CDN_FONTAWESOME_CSS', BASE_URL . '/assets/vendor/fontawesome/css/all.min.css');
    define('CDN_CHARTJS', BASE_URL . '/assets/vendor/chartjs/chart.umd.min.js');
}

// ---------------------------------------------------------------------------
// PHP runtime
// ---------------------------------------------------------------------------
date_default_timezone_set('Asia/Manila');

error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');
