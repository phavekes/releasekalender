<?php

declare(strict_types=1);

// Start output buffering to prevent accidental output before headers
ob_start();

// Error reporting
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', '/tmp/php-errors.log');

// Set error handler
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    error_log(sprintf("[%s] PHP Error [%d]: %s in %s:%d", 
        date('c'), $errno, $errstr, $errfile, $errline));
    return false; // Let PHP handle it
});

set_exception_handler(function ($e) {
    error_log(sprintf("[%s] PHP Exception: %s in %s:%d: %s", 
        date('c'), get_class($e), $e->getFile(), $e->getLine(), $e->getMessage()));
});

// Content Security Policy header
$csp = "default-src 'self'; " .
    "script-src 'self' 'unsafe-inline'; " .
    "style-src 'self' 'unsafe-inline'; " .
    "img-src 'self' data:; " .
    "font-src 'self'; " .
    "frame-ancestors 'none'; " .
    "base-uri 'self'";

// Add OIDC URL to form-action if configured
$configFile = dirname(__DIR__) . '/config/config.php';
if (file_exists($configFile)) {
    $config = require $configFile;
    $csp .= " form-action 'self' ";
    if (!empty($config['oidc']['url'])) {
        $csp .= $config['oidc']['url'];
    }
}

header("Content-Security-Policy: " . $csp);

// XSS Protection
header("X-XSS-Protection: 1; mode=block");

// Other security headers
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Permissions-Policy: geolocation=(), microphone=(), camera=()");

// Session configuration
session_set_cookie_params([
    'lifetime' => 3600,
    'path' => '/',
    'secure' => ($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off',
    'httponly' => true,
    'samesite' => 'Lax'
]);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Composer autoload
require_once dirname(__DIR__) . '/vendor/autoload.php';

/**
 * Escape output for HTML.
 */
function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/**
 * Get an environment variable with type awareness.
 */
function env(string $name, string|int|bool|array $default = ''): mixed
{
    $value = getenv($name);
    if ($value === false) {
        return $default;
    }
    if (is_bool($default)) {
        return in_array(strtolower($value), ['1', 'true', 'yes'], true);
    }
    if (is_int($default)) {
        return (int)$value;
    }
    if (is_array($default)) {
        return json_decode($value, true) ?: $default;
    }
    return $value === '' ? $default : $value;
}

/**
 * Rate limiting for sensitive operations.
 * Returns true if request should be allowed, false if rate limited.
 */
function rateLimit(string $key, int $maxAttempts = 5, int $windowSeconds = 60): bool
{
    $sessionKey = "rate_limit_$key";
    $now = time();
    
    if (!isset($_SESSION[$sessionKey])) {
        $_SESSION[$sessionKey] = ['attempts' => 1, 'window_start' => $now];
        return true;
    }
    
    $limit = $_SESSION[$sessionKey];
    
    // Reset window if expired
    if ($now - $limit['window_start'] >= $windowSeconds) {
        $_SESSION[$sessionKey] = ['attempts' => 1, 'window_start' => $now];
        return true;
    }
    
    // Check if limit exceeded
    if ($limit['attempts'] >= $maxAttempts) {
        // Calculate time until window reset
        $retryAfter = $limit['window_start'] + $windowSeconds - $now;
        header("Retry-After: $retryAfter");
        error_log("Rate limit exceeded for key: $key");
        return false;
    }
    
    $_SESSION[$sessionKey]['attempts']++;
    return true;
}

// Flush output buffer at the end
register_shutdown_function(function () {
    ob_end_flush();
});
