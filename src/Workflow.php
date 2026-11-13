<?php

declare(strict_types=1);

/**
 * The standard release workflow ("Deployment (The new way!)" on the old
 * Confluence pages): numbered steps with copy-able bash commands, generated
 * from the release (application, tags) and the config (deploy_name).
 */
class Workflow
{
    /**
     * @return array<int, array{title: string, env: ?string, commands: array<int, string>}>
     */
    public static function steps(Config $config, array $release): array
    {
        $app = $config->deployName($release['application']);
        $nr = (string)$release['release_nr'];
        $deployTag = trim((string)($release['deploy_tag'] ?? '')) ?: $nr;
        $envTag = trim((string)($release['environments_tag'] ?? '')) ?: $nr;

        return [
            [
                'title' => 'Check out the deploy tags',
                'env' => null,
                'commands' => [
                    'cd ~/surfconext-deployment-environments',
                    './scripts/repo-update ' . $envTag,
                    'cd ~/OpenConext-deploy',
                    'git checkout ' . $deployTag,
                ],
            ],
            [
                'title' => 'TEST2',
                'env' => 'test2',
                'commands' => [
                    './scripts/appdeploy test2 ' . $app,
                ],
            ],
            [
                'title' => 'TEST',
                'env' => 'test',
                'commands' => [
                    './scripts/appdeploy test ' . $app,
                ],
            ],
            [
                'title' => 'ACCBLAUW',
                'env' => 'accblauw',
                'commands' => [
                    './scripts/blauw acc ' . $app . ' 0',
                    './scripts/appdeploy accblauw ' . $app,
                    './scripts/blauw acc ' . $app . ' 100',
                ],
            ],
            [
                'title' => 'ACCROOD',
                'env' => 'accrood',
                'commands' => [
                    './scripts/appdeploy accrood ' . $app,
                    './scripts/blauw acc ' . $app . ' 50',
                ],
            ],
            [
                'title' => '4E SET',
                'env' => '4eset',
                'commands' => [
                    './scripts/appdeploy 4eset ' . $app,
                ],
            ],
            [
                'title' => 'PRODBLAUW',
                'env' => 'prodblauw',
                'commands' => [
                    './scripts/blauw prod ' . $app . ' 0',
                    './scripts/appdeploy prodblauw ' . $app,
                ],
            ],
            [
                'title' => 'PRODROOD',
                'env' => 'prodrood',
                'commands' => [
                    './scripts/blauw prod ' . $app . ' 100',
                    './scripts/appdeploy prodrood ' . $app,
                ],
            ],
            [
                'title' => 'PROD LB 50/50',
                'env' => 'LB 50/50',
                'commands' => [
                    './scripts/blauw prod ' . $app . ' 50',
                ],
            ],
        ];
    }

    /**
     * Whether a workflow step is completed, based on the planned-date items
     * of the release (a matching item that is installed or has a deployed date).
     */
    public static function stepDone(array $release, ?string $env): bool
    {
        if ($env === null) {
            return false;
        }
        foreach ($release['environments'] as $item) {
            if (($item['environment'] ?? null) === $env) {
                return (bool)($item['installed'] ?? 0) || (bool)($item['deployed_at']);
            }
        }
        return false;
    }
}
