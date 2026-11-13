<?php

declare(strict_types=1);

/**
 * Database connection management.
 */
class Database
{
    private static ?PDO $pdo = null;
    private PDO $connection;

    /**
     * Create a new Database instance.
     *
     * @param PDO $pdo Optional PDO instance (for testing)
     */
    public function __construct(?PDO $pdo = null)
    {
        if ($pdo !== null) {
            $this->connection = $pdo;
            return;
        }
        $this->connection = self::createPdo();
    }

    /**
     * Get the PDO instance.
     */
    public function pdo(): PDO
    {
        return $this->connection;
    }

    /**
     * Get a static PDO instance (for backward compatibility).
     *
     * @return PDO
     */
    public static function pdoStatic(): PDO
    {
        if (self::$pdo === null) {
            self::$pdo = self::createPdo();
        }
        return self::$pdo;
    }

    /**
     * Create a new PDO connection.
     *
     * @return PDO
     * @throws PDOException If connection fails
     */
    private static function createPdo(): PDO
    {
        $host = env('DB_HOST', 'db');
        $port = env('DB_PORT', '3306');
        $name = env('DB_NAME', 'releasekalender');
        $user = env('DB_USER', 'releasekalender');
        $pass = env('DB_PASS', 'releasekalender');
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name);

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];

        try {
            return new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Check if the database is connected and accessible.
     *
     * @return bool
     */
    public static function connected(): bool
    {
        try {
            self::pdoStatic()->query('SELECT 1');
            return true;
        } catch (PDOException $e) {
            error_log("Database connection check failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Reset the static PDO instance (useful for testing).
     */
    public static function reset(): void
    {
        self::$pdo = null;
    }
}
