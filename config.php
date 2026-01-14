<?php
// Configuration file for Mail Dispatch System

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'christemb_xend');
define('DB_PASSWORD', 'Bitmonster11*');
define('DB_NAME', 'christemb_xend');

// Session Configuration
define('SESSION_TIMEOUT', 3600); // 1 hour in seconds

// Upload Configuration
define('MAX_CSV_SIZE', 5242880); // 5MB
define('ALLOWED_MIME_TYPES', ['text/csv', 'text/plain', 'application/vnd.ms-excel']);

// SMTP Configuration defaults
define('DEFAULT_SMTP_PORT', 587);
define('DEFAULT_USE_TLS', true);

// Email Configuration
define('SENDER_EMAIL', 'noreply@dispatch.local');
define('SENDER_NAME', 'Mail Dispatch System');

// Security
define('PASSWORD_MIN_LENGTH', 8);

// Pagination
define('ITEMS_PER_PAGE', 20);

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');

// Create logs directory if it doesn't exist
if (!is_dir(__DIR__ . '/logs')) {
    mkdir(__DIR__ . '/logs', 0755, true);
}

// Timezone
date_default_timezone_set('UTC');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
