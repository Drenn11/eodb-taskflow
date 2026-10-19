<?php
/**
 * EODB-TaskFlow - Database connection
 *
 * Provides db(): a single shared PDO connection configured for
 * prepared statements, exceptions, and the Asia/Manila timezone.
 *
 * Usage:
 *   require_once __DIR__ . '/../config/app.php';
 *   require_once __DIR__ . '/../config/database.php';
 *   $stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
 *   $stmt->execute([$id]);
 */

declare(strict_types=1);

// Default XAMPP credentials. Change these if your MySQL root account has a password.
define('DB_HOST', 'sql12.freesqldatabase.com');
define('DB_PORT', 3306);
define('DB_NAME', 'sql12839099');
define('DB_USER', 'sql12839099');
define('DB_PASS', 'dMV7WpdFAX');
define('DB_CHARSET', 'utf8mb4');

/**
 * Returns the shared PDO connection, creating it on first use.
 * On failure, the real error is written to the PHP error log and the user
 * only sees a generic message (credentials and SQL are never shown).
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        DB_HOST,
        DB_PORT,
        DB_NAME,
        DB_CHARSET
    );

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false, // real server-side prepared statements
        PDO::ATTR_STRINGIFY_FETCHES  => false,
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        // Keep CURDATE()/NOW() in Philippine time so overdue detection matches PHP.
        $pdo->exec("SET time_zone = '+08:00'");
    } catch (PDOException $e) {
        error_log('[EODB-TaskFlow] Database connection failed: ' . $e->getMessage());

        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, "Database connection failed. Check that MySQL is running and that the\n"
                . "'" . DB_NAME . "' database was imported. Details were written to the PHP error log.\n");
            exit(1);
        }

        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Service Unavailable</title>'
            . '<style>body{font-family:Arial,sans-serif;background:#f5f5f5;color:#333;display:flex;'
            . 'align-items:center;justify-content:center;height:100vh;margin:0}'
            . '.box{background:#fff;border:1px solid #ddd;padding:32px;max-width:480px;text-align:center}</style>'
            . '</head><body><div class="box"><h2>System temporarily unavailable</h2>'
            . '<p>The system could not connect to the database. Please contact the system administrator.</p>'
            . '</div></body></html>';
        exit;
    }

    return $pdo;
}
