<?php
/**
 * EODB-TaskFlow - Run the automatic checks from the command line
 *
 * The checks already run automatically while people use the system. Use this
 * script to also run them when nobody is logged in (e.g. every morning), with
 * Windows Task Scheduler:
 *
 *   Program: C:\xampp\php\php.exe
 *   Arguments: C:\xampp\htdocs\eodb-taskflow\cron\run_checks.php
 *
 * Command line only; it cannot be opened in a browser.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/scheduler.php';

$result = run_scheduled_checks(true);

echo '[' . date('Y-m-d H:i:s') . '] EODB-TaskFlow checks: '
    . $result['overdue'] . ' task(s) marked overdue, '
    . $result['recalculated'] . ' status(es) recalculated, '
    . $result['reminders'] . ' deadline reminder(s) sent.' . PHP_EOL;
