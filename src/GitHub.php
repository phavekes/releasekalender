<?php

declare(strict_types=1);

/**
 * Queries the GitHub API for the releases of an application. The releases
 * URL from the config (https://github.com/<org>/<repo>/releases) is
 * translated to the API endpoint https://api.github.com/repos/<org>/<repo>/releases.
 */
class GitHub
{
    private HttpClient $httpClient;
    private string $token;

    public function __construct(string $token, HttpClient $httpClient)
    {
        $this->token = $token;
        $this->httpClient = $httpClient;
    }

    /**
     * @return array<int, array{tag: string, name: string, body: string, url: string, published_at: string}>|null
     *         null when the app has no GitHub URL configured or the request failed.
     */
    public function releases(string $githubReleasesUrl): ?array
    {
        if (!preg_match('#^https://github\.com/([^/]+)/([^/]+)/releases/?$#', $githubReleasesUrl, $m)) {
            return null;
        }
        [, $owner, $repo] = $m;

        $headers = [
            'Accept: application/vnd.github+json',
            'User-Agent: ReleaseKalender',
        ];
        if ($this->token !== '') {
            $headers[] = 'Authorization: Bearer ' . $this->token;
        }

        $url = 'https://api.github.com/repos/' . $owner . '/' . $repo . '/releases?per_page=20';
        $response = $this->httpClient->get($url, $headers);

        if ($response === null) {
            error_log('GitHub API: Failed to fetch releases for ' . $owner . '/' . $repo);
            return null;
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            error_log('GitHub API: Invalid JSON response for ' . $owner . '/' . $repo . ': ' . substr($response, 0, 200));
            return null;
        }

        $releases = [];
        foreach ($data as $release) {
            if (($release['draft'] ?? false) || ($release['prerelease'] ?? false)) {
                continue;
            }
            $releases[] = [
                'tag' => (string)($release['tag_name'] ?? ''),
                'name' => (string)($release['name'] ?? ''),
                'body' => (string)($release['body'] ?? ''),
                'url' => (string)($release['html_url'] ?? ''),
                'published_at' => (string)($release['published_at'] ?? ''),
            ];
        }
        return $releases;
    }

    /**
     * Static factory method for backward compatibility.
     */
    public static function create(string $token): self
    {
        return new self($token, new HttpClient());
    }
}
