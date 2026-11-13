<?php

declare(strict_types=1);

/**
 * All users that have ever logged in (via OIDC or the local dev bypass).
 * The nickname is used as display name.
 */
class Users
{
    public function __construct(private PDO $pdo)
    {
    }

    public function upsert(array $user): void
    {
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
    }

    /** @return array<string, array{nickname: string, name: ?string, email: ?string}> */
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
}
