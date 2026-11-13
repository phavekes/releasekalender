<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/bootstrap.php';

$config = new Config();
Auth::requireLogin();

$app = $_GET['app'] ?? '';
if (!isset($config->applications()[$app])) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unknown application']);
    exit;
}

$steps = Workflow::steps($config, [
    'application' => $app,
    'release_nr' => $_GET['release_nr'] ?? '',
    'deploy_tag' => $_GET['deploy_tag'] ?? '',
    'environments_tag' => $_GET['environments_tag'] ?? '',
]);

header('Content-Type: application/json');
echo json_encode(['steps' => $steps]);
