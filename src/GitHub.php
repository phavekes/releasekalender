<?php

declare(strict_types=1);

/**
 * Queries the GitHub API for the releases of an application. The releases
 * URL from the config (https://github.com/<org>/<repo>/releases) is
 * translated to the API endpoint https://api.github.com/repos/<org>/<repo>/releases.
 */
class GitHub
{
    public function __construct(private string $token)
    {
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

        $ch = curl_init('https://api.github.com/repos/' . $owner . '/' . $repo . '/releases?per_page=20');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

        if ($status !== 200 || !is_string($response)) {
            return null;
        }
        $data = json_decode($response, true);
        if (!is_array($data)) {
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
}
