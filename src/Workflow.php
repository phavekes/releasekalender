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
                'env' => Constants::ENV_TEST2,
                'commands' => [
                    './scripts/appdeploy ' . Constants::ENV_TEST2 . ' ' . $app,
                ],
            ],
            [
                'title' => 'TEST',
                'env' => Constants::ENV_TEST,
                'commands' => [
                    './scripts/appdeploy ' . Constants::ENV_TEST . ' ' . $app,
                ],
            ],
            [
                'title' => 'ACCBLAUW',
                'env' => Constants::ENV_ACCBLAUW,
                'commands' => [
                    './scripts/blauw acc ' . $app . ' 0',
                    './scripts/appdeploy ' . Constants::ENV_ACCBLAUW . ' ' . $app,
                    './scripts/blauw acc ' . $app . ' 100',
                ],
            ],
            [
                'title' => 'ACCROOD',
                'env' => Constants::ENV_ACCROOD,
                'commands' => [
                    './scripts/appdeploy ' . Constants::ENV_ACCROOD . ' ' . $app,
                    './scripts/blauw acc ' . $app . ' 50',
                ],
            ],
            [
                'title' => '4E SET',
                'env' => Constants::ENV_4ESET,
                'commands' => [
                    './scripts/appdeploy ' . Constants::ENV_4ESET . ' ' . $app,
                ],
            ],
            [
                'title' => 'PRODBLAUW',
                'env' => Constants::ENV_PRODBLAUW,
                'commands' => [
                    './scripts/blauw prod ' . $app . ' 0',
                    './scripts/appdeploy ' . Constants::ENV_PRODBLAUW . ' ' . $app,
                ],
            ],
            [
                'title' => 'PRODROOD',
                'env' => Constants::ENV_PRODROOD,
                'commands' => [
                    './scripts/blauw prod ' . $app . ' 100',
                    './scripts/appdeploy ' . Constants::ENV_PRODROOD . ' ' . $app,
                ],
            ],
            [
                'title' => 'PROD LB 50/50',
                'env' => Constants::ENV_LB_50_50,
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
