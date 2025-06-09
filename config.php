<?php
// Production environment settings
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Load environment variables from Vercel
function getEnvVar($key, $default = null) {
    return $_ENV[$key] ?? getenv($key) ?? $default;
}

// Set timezone
date_default_timezone_set('UTC');

// Log file configuration for Vercel
define('LOG_FILE', '/tmp/app.log');
define('ERROR_LOG', '/tmp/error.log');

// Debug mode (only enable temporarily if needed)
define('DEBUG_MODE', false);

// Error handling for Vercel
function customErrorHandler($errno, $errstr, $errfile, $errline) {
    $timestamp = date('Y-m-d H:i:s');
    $message = "[$timestamp] Error ($errno): $errstr in $errfile on line $errline\n";
    error_log($message);
    
    if (DEBUG_MODE) {
        echo "An error occurred. Please check the error log for details.";
    } else {
        echo "An unexpected error occurred. Please try again later.";
    }
    
    return true;
}

// Set custom error handler
set_error_handler('customErrorHandler');

// Function to log messages
function log_message($message, $type = 'info') {
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$timestamp] [$type] $message\n";
    error_log($log_message);
    
    if (DEBUG_MODE) {
        echo $log_message;
    }
}

// Vercel-specific directory paths
define('CODES_DIR', __DIR__ . '/codes');
define('EMAILS_FILE', __DIR__ . '/registered_emails.txt');

// Create necessary directories
if (!is_dir(CODES_DIR)) {
    mkdir(CODES_DIR, 0777, true);
}

// Load environment variables from .env file
$env_path = __DIR__ . '/.env';
if (file_exists($env_path)) {
    $lines = file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
            putenv(sprintf('%s=%s', trim($key), trim($value)));
        }
    }
}

// GitHub API Configuration
define('GITHUB_API_TOKEN', getenv('GITHUB_TOKEN') ?: '');

// Email Configuration
if (!defined('FROM_EMAIL')) define('FROM_EMAIL', getenv('FROM_EMAIL') ?: 'noreply@yourdomain.com');
if (!defined('FROM_NAME')) define('FROM_NAME', getenv('FROM_NAME') ?: 'GitHub Timeline Updates');

// SMTP Configuration
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', getenv('SMTP_USERNAME'));
define('SMTP_PASSWORD', getenv('SMTP_PASSWORD'));
define('SMTP_SECURE', 'tls');

// Application Configuration

// Validate configuration
function validateConfig() {
    $required = [
        'GitHub API Token' => GITHUB_API_TOKEN,
        'SMTP Username' => SMTP_USERNAME,
        'SMTP Password' => SMTP_PASSWORD
    ];

    $missing = [];
    foreach ($required as $name => $value) {
        if (empty($value)) {
            $missing[] = $name;
        }
    }

    if (!empty($missing)) {
        die('Error: Missing required configuration: ' . implode(', ', $missing) . '. Please check your .env file.');
    }
}

