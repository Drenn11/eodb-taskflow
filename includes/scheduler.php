<?php
/**
 * EODB-TaskFlow - Automatic background checks
 *
 * XAMPP has no built-in job scheduler, so the checks run while people use the
 * system: includes/auth.php calls run_scheduled_checks() on page loads by
 * logged-in users, at most once every SCHEDULED_CHECK_INTERVAL seconds.
 * The same checks can also be run from Windows Task Scheduler with
 * cron/run_checks.php.
 *
 * Checks:
 *   - Overdue detection (refresh_overdue_tasks)
 *   - Approaching-deadline reminders (send_deadline_reminders)
 */

declare(strict_types=1);

const SCHEDULED_CHECK_INTERVAL = 60; // seconds

/**
 * Claims the next run. Only one request per interval gets true: the UPDATE is
 * atomic, so simultaneous page loads cannot run the checks twice.
 */
function claim_scheduled_run(): bool
{
    $pdo = db();
    $pdo->exec(
        "INSERT IGNORE INTO settings (setting_key, setting_value, description)
         VALUES ('last_status_check', '2000-01-01 00:00:00', 'Set by the system: time of the last automatic overdue/deadline check')"
    );

    $stmt = $pdo->prepare(
        "UPDATE settings
            SET setting_value = DATE_FORMAT(NOW(), '%Y-%m-%d %H:%i:%s')
          WHERE setting_key = 'last_status_check'
            AND setting_value < DATE_FORMAT(NOW() - INTERVAL ? SECOND, '%Y-%m-%d %H:%i:%s')"
    );
    $stmt->execute([SCHEDULED_CHECK_INTERVAL]);

    return $stmt->rowCount() === 1;
}

/**
 * Runs the automatic checks.
 *
 * @param bool $force true = ignore the interval (used by cron/run_checks.php)
 * @return array{ran:bool, overdue:int, recalculated:int, reminders:int}
 */
function run_scheduled_checks(bool $force = false): array
{
    if (!$force && !claim_scheduled_run()) {
        return ['ran' => false, 'overdue' => 0, 'recalculated' => 0, 'reminders' => 0];
    }

    require_once __DIR__ . '/task_functions.php';
    $overdue = refresh_overdue_tasks();
    $reminders = send_deadline_reminders();

    return ['ran' => true] + $overdue + ['reminders' => $reminders];
}
