<?php

declare(strict_types=1);

require_once __DIR__ . '/Config.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/JwtValidator.php';
require_once __DIR__ . '/OIDCDiscovery.php';
require_once __DIR__ . '/Users.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/GitHub.php';
require_once __DIR__ . '/Releases.php';
require_once __DIR__ . '/Workflow.php';
require_once __DIR__ . '/View.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function env(string $name, string $default = ''): string
{
    $value = getenv($name);
    return $value === false || $value === '' ? $default : $value;
}
