<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

$config = new Config();
Auth::checkRateLimit();

try {
    Auth::handleCallbackStatic($config);
} catch (Throwable $e) {
    error_log("OIDC callback error: " . $e->getMessage());
    header('Location: /login?error=1');
    exit;
}
