<?php
/**
 * EODB-TaskFlow - CSRF protection
 *
 * Every form that changes data must include csrf_field(), and the page that
 * processes it must call require_csrf() (or csrf_valid()) before doing anything.
 */

declare(strict_types=1);

/** Returns the session's CSRF token, creating it if needed. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Hidden input to place inside every POST form. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** Compares a submitted token with the session token in constant time. */
function csrf_valid(mixed $token): bool
{
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && is_string($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

/** Issues a new token (called after login so a token cannot be fixed in advance). */
function regenerate_csrf_token(): void
{
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/** Stops the request with HTTP 400 if the POSTed token is missing or wrong. */
function require_csrf(): void
{
    if (csrf_valid($_POST['csrf_token'] ?? null)) {
        return;
    }

    http_response_code(400);
    log_activity(
        isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null,
        'security',
        'Rejected a form submission with an invalid CSRF token on ' . ($_SERVER['REQUEST_URI'] ?? '')
    );

    $back = e(url('index.php'));
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Request Expired</title>'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<link rel="stylesheet" href="' . e(CDN_BOOTSTRAP_CSS) . '"></head>'
        . '<body class="bg-light d-flex align-items-center" style="min-height:100vh">'
        . '<div class="container" style="max-width:480px"><div class="card shadow-sm"><div class="card-body p-4 text-center">'
        . '<h1 class="h4">Request expired</h1>'
        . '<p class="text-muted">Your form session has expired or the request could not be verified. '
        . 'Please go back, reload the page, and try again.</p>'
        . '<a class="btn btn-primary" href="' . $back . '">Return to the system</a>'
        . '</div></div></div></body></html>';
    exit;
}
