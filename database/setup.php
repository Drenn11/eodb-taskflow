<?php
/**
 * EODB-TaskFlow - One-time database setup and verification
 *
 * Run ONCE after importing database/eodb_taskflow.sql:
 *   Browser : http://localhost/eodb-taskflow/database/setup.php
 *   CLI     : C:\xampp\php\php.exe C:\xampp\htdocs\eodb-taskflow\database\setup.php
 *
 * What it does:
 *   1. Checks the PHP version and the database connection.
 *   2. Confirms that every required table and the productivity view exist.
 *   3. Replaces the '!PENDING_SETUP!' marker of each sample account with a
 *      real bcrypt hash from password_hash(). Accounts that already have a
 *      hash are never touched, so running it again is harmless.
 *   4. Verifies the demo logins with password_verify().
 *   5. Prints the number of records per table.
 *
 * Only reachable from the local machine (127.0.0.1 / ::1) or the command line.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

const PENDING_MARKER = '!PENDING_SETUP!';

$isCli = PHP_SAPI === 'cli';

if (!$isCli) {
    $client = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!in_array($client, ['127.0.0.1', '::1'], true)) {
        http_response_code(403);
        exit('Forbidden: setup can only be run from the local machine.');
    }
    header('Content-Type: text/plain; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
}

function out(string $line = ''): void
{
    echo $line . PHP_EOL;
}

out('EODB-TaskFlow - Database Setup');
out(str_repeat('=', 60));

// 1. PHP version ------------------------------------------------------------
if (version_compare(PHP_VERSION, '8.0.0', '<')) {
    out('[FAIL] PHP 8.0 or newer is required. Current version: ' . PHP_VERSION);
    exit(1);
}
out('[ OK ] PHP version ' . PHP_VERSION);

// 2. Connection -------------------------------------------------------------
$pdo = db();
$serverVersion = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
out('[ OK ] Connected to database "' . DB_NAME . '" (server ' . $serverVersion . ')');

// 3. Required tables / view -------------------------------------------------
$required = [
    'departments', 'users', 'tasks', 'task_updates', 'notifications',
    'activity_logs', 'remember_tokens', 'login_attempts', 'settings',
    'vw_employee_productivity',
];

$existing = $pdo->query(
    'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()'
)->fetchAll(PDO::FETCH_COLUMN);

$missing = array_diff($required, $existing);
if ($missing) {
    out('[FAIL] Missing tables/views: ' . implode(', ', $missing));
    out('       Re-import database/eodb_taskflow.sql in phpMyAdmin, then run this again.');
    exit(1);
}
out('[ OK ] All ' . count($required) . ' required tables and views exist');

// 4. Hash pending sample passwords -------------------------------------------
// Demo passwords by username; every other sample account uses its role default.
$demoPasswords = [
    'admin'      => 'Admin123!',
    'supervisor' => 'Supervisor123!',
    'employee'   => 'Employee123!',
];
$roleDefaults = [
    'admin'      => 'Admin123!',
    'supervisor' => 'Supervisor123!',
    'employee'   => 'Employee123!',
];

$pending = $pdo->prepare('SELECT id, username, role FROM users WHERE password = ?');
$pending->execute([PENDING_MARKER]);
$pendingUsers = $pending->fetchAll();

if (!$pendingUsers) {
    out('[ OK ] No pending passwords - setup was already completed');
} else {
    $update = $pdo->prepare('UPDATE users SET password = ? WHERE id = ? AND password = ?');

    $pdo->beginTransaction();
    try {
        foreach ($pendingUsers as $user) {
            $plain = $demoPasswords[$user['username']] ?? $roleDefaults[$user['role']];
            $update->execute([password_hash($plain, PASSWORD_DEFAULT), $user['id'], PENDING_MARKER]);
        }

        $log = $pdo->prepare(
            'INSERT INTO activity_logs (user_id, action, description, ip_address) VALUES (NULL, ?, ?, ?)'
        );
        $log->execute([
            'system',
            'Initial setup: hashed passwords for ' . count($pendingUsers) . ' sample account(s).',
            $isCli ? null : ($_SERVER['REMOTE_ADDR'] ?? null),
        ]);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        error_log('[EODB-TaskFlow] setup.php failed: ' . $e->getMessage());
        out('[FAIL] Could not update passwords. See the PHP error log for details.');
        exit(1);
    }
    out('[ OK ] Hashed passwords for ' . count($pendingUsers) . ' sample account(s) using password_hash()');
}

// 4b. Repair plain-text passwords --------------------------------------------
// If a password was typed directly into phpMyAdmin, it is stored as plain text
// and nobody can log in with it. Hash it so the typed password works.
$plainRows = [];
foreach ($pdo->query('SELECT id, username, password FROM users') as $row) {
    if ($row['password'] !== PENDING_MARKER && password_get_info($row['password'])['algo'] === null) {
        $plainRows[] = $row;
    }
}

if (!$plainRows) {
    out('[ OK ] No plain-text passwords found');
} else {
    $repair = $pdo->prepare('UPDATE users SET password = ?, updated_at = updated_at WHERE id = ? AND password = ?');
    $log = $pdo->prepare(
        'INSERT INTO activity_logs (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)'
    );
    foreach ($plainRows as $row) {
        $repair->execute([password_hash($row['password'], PASSWORD_DEFAULT), $row['id'], $row['password']]);
        $log->execute([
            $row['id'],
            'security',
            'Setup found a plain-text password (edited outside the system) and replaced it with a secure hash.',
            $isCli ? null : ($_SERVER['REMOTE_ADDR'] ?? null),
        ]);
        out("[FIX ] '{$row['username']}' had a plain-text password. It is now hashed; log in with the password that was typed in phpMyAdmin.");
    }
}

// 5. Verify demo logins ------------------------------------------------------
$find = $pdo->prepare('SELECT password FROM users WHERE username = ?');
foreach ($demoPasswords as $username => $plain) {
    $find->execute([$username]);
    $hash = $find->fetchColumn();

    if ($hash === false) {
        out("[WARN] Demo account '{$username}' not found (it may have been renamed)");
        continue;
    }
    if (password_verify($plain, (string) $hash)) {
        out("[ OK ] Login check passed for '{$username}' (stored value is a bcrypt hash, not plain text)");
    } else {
        out("[WARN] Demo password for '{$username}' no longer matches (it was probably changed - that is fine)");
    }
}

// 6. Record counts -----------------------------------------------------------
out();
out('Record counts');
out(str_repeat('-', 60));
foreach (['departments', 'users', 'tasks', 'task_updates', 'notifications', 'activity_logs', 'settings'] as $table) {
    // Table names come from the fixed list above, never from user input.
    $count = (int) $pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
    out(str_pad($table, 20) . $count);
}

out();
out('Employee productivity (vw_employee_productivity)');
out(str_repeat('-', 60));
$rows = $pdo->query(
    'SELECT full_name, total_tasks, completed_tasks, completion_rate
       FROM vw_employee_productivity ORDER BY full_name'
)->fetchAll();
foreach ($rows as $row) {
    out(sprintf(
        '%-28s total %2d  completed %2d  rate %6.2f%%',
        $row['full_name'],
        $row['total_tasks'],
        $row['completed_tasks'],
        $row['completion_rate']
    ));
}

out();
out(str_repeat('=', 60));
out('Setup finished. Demo accounts:');
out('  admin      / Admin123!');
out('  supervisor / Supervisor123!');
out('  employee   / Employee123!');
out('  (other sample employees: areyes, mgarcia, kramos, rbautista / Employee123!)');
out('Login page will be available after Phase 2: ' . BASE_URL . '/login.php');
