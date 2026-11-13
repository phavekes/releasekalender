<?php

declare(strict_types=1);

/**
 * All users that have ever logged in (via OIDC or the local dev bypass).
 * The nickname is used as display name.
 */
class Users
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function upsert(array $user): void
    {
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        
        if ($driver === 'mysql') {
            $stmt = $this->pdo->prepare(
                'INSERT INTO users (sub, nickname, name, email)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                   nickname = VALUES(nickname),
                   name = VALUES(name),
                   email = VALUES(email)'
            );
            $stmt->execute([
                $user['sub'],
                $user['nickname'] ?? ($user['name'] ?? $user['sub']),
                $user['name'] ?? null,
                $user['email'] ?? null,
            ]);
        } else {
            // SQLite doesn't support ON DUPLICATE KEY UPDATE, use INSERT OR REPLACE
            $this->pdo->exec('CREATE TABLE IF NOT EXISTS users (sub TEXT PRIMARY KEY, nickname TEXT, name TEXT, email TEXT)');
            $stmt = $this->pdo->prepare(
                'INSERT OR REPLACE INTO users (sub, nickname, name, email)
                 VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([
                $user['sub'],
                $user['nickname'] ?? ($user['name'] ?? $user['sub']),
                $user['name'] ?? null,
                $user['email'] ?? null,
            ]);
        }
    }

    /**
     * @return array<string, array{nickname: string, name: ?string, email: ?string}> */
    public function all(): array
    {
        $rows = $this->pdo->query(
            'SELECT sub, nickname, name, email FROM users ORDER BY nickname'
        )->fetchAll();

        $users = [];
        foreach ($rows as $row) {
            $users[$row['sub']] = $row;
        }
        return $users;
    }

    public function nickname(string $sub): string
    {
        $users = $this->all();
        return $users[$sub]['nickname'] ?? '';
    }

    /**
     * Find a user by sub.
     */
    public function find(string $sub): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE sub = ?');
        $stmt->execute([$sub]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Delete a user.
     */
    public function delete(string $sub): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM users WHERE sub = ?');
        $stmt->execute([$sub]);
    }

    /**
     * Get user count.
     */
    public function count(): int
    {
        return (int)$this->pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    }

    /**
     * Static factory for backward compatibility.
     */
    public static function create(): self
    {
        return new self(Database::pdoStatic());
    }
}
