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
define('DB_HOST', 'db.wayruqnckafsnewqjryx.supabase.co');
define('DB_PORT', '5432');
define('DB_NAME', 'postgres');
define('DB_USER', 'postgres');
define('DB_PASS', 'TaskFlow2026');

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
        'pgsql:host=%s;port=%d;dbname=%s',
        DB_HOST,
        DB_PORT,
        DB_NAME,
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
    
    http_response_code(500);
    echo "<div style='padding: 20px; color: red; font-family: sans-serif;'>";
    echo "<h3>Database Connection Error:</h3>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    echo "</div>";
    exit;
}

    return $pdo;
}
