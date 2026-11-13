<?php

declare(strict_types=1);

class Config
{
    private array $config;

    public function __construct()
    {
        $this->config = require dirname(__DIR__) . '/config/config.php';
    }

    public function applications(): array
    {
        return $this->config['applications'];
    }

    public function applicationLabel(string $key): string
    {
        return $this->config['applications'][$key]['label'] ?? $key;
    }

    public function githubReleasesUrl(string $appKey): string
    {
        return $this->config['applications'][$appKey]['github_releases_url'] ?? '';
    }

    public function deployName(string $appKey): string
    {
        return $this->config['applications'][$appKey]['deploy_name'] ?? $appKey;
    }

    public function environments(): array
    {
        return $this->config['environments'];
    }

    public function environmentNames(): array
    {
        return array_keys($this->config['environments']);
    }

    /** @return string[] default planned-date items for a new release */
    public function defaultPlanItems(): array
    {
        $items = $this->config['default_plan_items'] ?? [];
        return is_array($items) && $items ? array_values($items) : $this->environmentNames();
    }

    public function isProduction(string $environment): bool
    {
        return (bool)($this->config['environments'][$environment]['production'] ?? false);
    }

    public function environmentLabel(string $environment): string
    {
        if ($environment === 'LB 50/50') {
            return 'LB 50/50';
        }
        return $this->config['environments'][$environment]['label'] ?? $environment;
    }

    public function githubToken(): string
    {
        return env('GITHUB_TOKEN', $this->config['github']['token'] ?? '');
    }

    public function oidc(): array
    {
        $oidc = $this->config['oidc'];
        $oidc['url'] = env('OIDC_URL', $oidc['url']);
        $oidc['client_id'] = env('OIDC_CLIENT_ID', $oidc['client_id']);
        $oidc['client_secret'] = env('OIDC_CLIENT_SECRET', $oidc['client_secret']);
        $oidc['dev_bypass'] = (bool)env('DEV_BYPASS', $oidc['dev_bypass'] ? '1' : '');
        return $oidc;
    }

    public function baseUrl(): string
    {
        $https = ($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off';
        $scheme = $https ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $scheme . '://' . $host;
    }
}
