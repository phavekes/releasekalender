<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/bootstrap.php';

$config = new Config();
$releases = new Releases(Database::pdoStatic(), $config);
$github = new GitHub($config->githubToken());

$app = $_GET['app'] ?? '';
if (!isset($config->applications()[$app])) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unknown application']);
    exit;
}

$url = $config->githubReleasesUrl($app);
if ($url === '') {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'No GitHub releases URL configured', 'releases' => []]);
    exit;
}

$githubReleases = $github->releases($url);
if ($githubReleases === null) {
    http_response_code(502);
    header('Content-Type: application/json');
    error_log('GitHub releases API request failed for URL: ' . $url);
    echo json_encode(['error' => 'GitHub API request failed']);
    exit;
}

// Filter out releases that are already in the calendar.
$existing = $releases->existingVersions($app);
$available = array_values(array_filter($githubReleases, function (array $release) use ($existing) {
    return !in_array($release['tag'], $existing, true)
        && !in_array(ltrim($release['tag'], 'v'), $existing, true);
}));

header('Content-Type: application/json');
echo json_encode(['releases' => $available]);
