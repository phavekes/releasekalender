<?php

declare(strict_types=1);

/*
 * Creates the database schema on startup and, when SEED_EXAMPLE_DATA=1,
 * loads the example data based on the Confluence export.
 */

$host = getenv('DB_HOST') ?: 'db';
$port = getenv('DB_PORT') ?: '3306';
$name = getenv('DB_NAME') ?: 'releasekalender';
$user = getenv('DB_USER') ?: 'releasekalender';
$pass = getenv('DB_PASS') ?: 'releasekalender';

$waitForDb = function () use ($host, $port, $user, $pass) {
    for ($i = 0; $i < 60; $i++) {
        try {
            $pdo = new PDO(
                "mysql:host=$host;port=$port;charset=utf8mb4",
                $user,
                $pass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            return $pdo;
        } catch (PDOException $e) {
            sleep(1);
        }
    }
    fwrite(STDERR, "Database not reachable after 60s\n");
    exit(1);
};

$pdo = $waitForDb();

$pdo->exec(file_get_contents(__DIR__ . '/../sql/schema.sql'));

$seed = getenv('SEED_EXAMPLE_DATA');
if ($seed === '1') {
    $stmt = $pdo->query("SELECT COUNT(*) FROM $name.releases");
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("USE $name");
        $pdo->exec(file_get_contents(__DIR__ . '/../sql/seed.sql'));
        echo "Example data loaded.\n";
    }
}

echo "Database ready.\n";
