<?php

declare(strict_types=1);

/**
 * Configuration management for the Release Calendar.
 */
class Config
{
    private array $config;

    public function __construct(?string $configPath = null)
    {
        $path = $configPath ?? dirname(__DIR__) . '/config/config.php';
        if (!file_exists($path)) {
            throw new RuntimeException("Configuration file not found: $path");
        }
        $this->config = require $path;
        $this->validate();
    }

    /**
     * Validate the configuration.
     */
    private function validate(): void
    {
        if (!isset($this->config['applications']) || !is_array($this->config['applications'])) {
            throw new RuntimeException('Missing or invalid "applications" configuration.');
        }

        if (!isset($this->config['environments']) || !is_array($this->config['environments'])) {
            throw new RuntimeException('Missing or invalid "environments" configuration.');
        }

        foreach ($this->config['environments'] as $name => $env) {
            if (!is_array($env)) {
                throw new RuntimeException("Invalid environment configuration for " . var_export($name, true));
            }
        }

        if (!isset($this->config['oidc']) || !is_array($this->config['oidc'])) {
            throw new RuntimeException('Missing or invalid "oidc" configuration.');
        }
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
        if ($environment === Constants::ENV_LB_50_50) {
            return Constants::ENV_LB_50_50;
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

    /**
     * Get a specific application configuration.
     */
    public function application(string $key): array
    {
        return $this->config['applications'][$key] ?? [];
    }

    /**
     * Check if an application exists.
     */
    public function hasApplication(string $key): bool
    {
        return isset($this->config['applications'][$key]);
    }

    /**
     * Check if an environment exists.
     */
    public function hasEnvironment(string $name): bool
    {
        return isset($this->config['environments'][$name]);
    }
}
