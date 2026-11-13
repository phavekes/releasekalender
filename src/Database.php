<?php

declare(strict_types=1);

class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $host = env('DB_HOST', 'db');
            $port = env('DB_PORT', '3306');
            $name = env('DB_NAME', 'releasekalender');
            $user = env('DB_USER', 'releasekalender');
            $pass = env('DB_PASS', 'releasekalender');
            $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name);
            self::$pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        }
        return self::$pdo;
    }

    public static function connected(): bool
    {
        try {
            self::pdo()->query('SELECT 1');
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }
}
