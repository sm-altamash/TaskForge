<?php
/**
 * Application Bootstrap
 *
 * Loads environment variables, configuration, database class,
 * starts the session, and sets the global exception handler.
 */

// ──────────────────────────────────
// 1. Load .env file
// ──────────────────────────────────
$envPath = __DIR__ . '/../.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (str_starts_with(trim($line), '#')) {
            continue;
        }
        putenv(trim($line));
    }
}

// ──────────────────────────────────
// 2. Load Configuration
// ──────────────────────────────────
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

// ──────────────────────────────────
// 3. Load Core Database Class
// ──────────────────────────────────
require_once __DIR__ . '/Database.php';

// ──────────────────────────────────
// 4. Session Configuration (Native PHP)
// ──────────────────────────────────
$is_production = (defined('ENVIRONMENT') && ENVIRONMENT === 'production');

ini_set('session.gc_maxlifetime', 604800); // 7 days

session_set_cookie_params([
    'lifetime' => 604800,
    'path'     => '/',
    'domain'   => '',
    'secure'   => $is_production,
    'httponly'  => true,
    'samesite'  => 'Strict'
]);

session_start();

// ──────────────────────────────────
// 5. Global Headers & Exception Handler
// ──────────────────────────────────
header('Content-Type: application/json');

set_exception_handler(function ($exception) {
    http_response_code(500);
    $response = [
        'error'   => 'An unexpected server error occurred.',
        'message' => $exception->getMessage()
    ];

    if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
        $response['trace'] = $exception->getTraceAsString();
    }

    echo json_encode($response, JSON_PRETTY_PRINT);
});
