<?php

declare(strict_types=1);

/**
 * CSRF protection using tokens stored in session.
 */
class Csrf
{
    private const TOKEN_LENGTH = 32;
    private const SESSION_KEY = 'csrf_token';
    private const HEADER_NAME = 'X-CSRF-Token';

    /**
     * Generate a new CSRF token.
     *
     * @return string The generated token
     */
    public static function generate(): string
    {
        if (empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(self::TOKEN_LENGTH));
        }
        return $_SESSION[self::SESSION_KEY];
    }

    /**
     * Get the current CSRF token (generates if not exists).
     *
     * @return string The token
     */
    public static function token(): string
    {
        return self::generate();
    }

    /**
     * Validate a CSRF token.
     *
     * @param string|null $token The token to validate
     * @return bool True if valid
     */
    public static function validate(?string $token): bool
    {
        if ($token === null) {
            return false;
        }

        $sessionToken = $_SESSION[self::SESSION_KEY] ?? null;
        if ($sessionToken === null) {
            return false;
        }

        // Use hash_equals for timing-safe comparison
        return hash_equals($sessionToken, $token);
    }

    /**
     * Validate CSRF token from POST data.
     *
     * @return bool True if valid
     */
    public static function validatePost(): bool
    {
        $token = $_POST['csrf_token'] ?? null;
        if ($token === null) {
            // Also check header for API requests
            $token = $_SERVER[self::HEADER_NAME] ?? null;
        }
        return self::validate($token);
    }

    /**
     * Generate a hidden input field for CSRF token.
     *
     * @return string HTML input field
     */
    public static function field(): string
    {
        return sprintf('<input type="hidden" name="csrf_token" value="%s">', e(self::token()));
    }

    /**
     * Regenerate the CSRF token (call after login to prevent session fixation).
     */
    public static function regenerate(): void
    {
        unset($_SESSION[self::SESSION_KEY]);
        self::generate();
    }
}
