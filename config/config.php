<?php

/*
 * Configuration for the Release Calendar.
 *
 * Everything not stored in the database lives here: managed applications,
 * available environments, GitHub releases URLs and the OIDC client settings.
 *
 * The file is mounted into the container (see docker-compose.yml) and can be
 * changed without rebuilding the image.
 */

return [

    // Applications managed by this release calendar. The key is stored in the
    // database, the label is displayed. github_releases_url is optional: when
    // set, the add-form lists the GitHub releases for this application.
    // deploy_name is the component name used in the deploy scripts
    // (./scripts/appdeploy <env> <deploy_name>) of the standard workflow.
    'applications' => [
        'eduID' => [
            'label' => 'eduID (MyConext)',
            'deploy_name' => 'myconext',
            'github_releases_url' => 'https://github.com/OpenConext/OpenConext-myconext/releases',
        ],
        'mujina' => [
            'label' => 'Mujina',
            'deploy_name' => 'mujina-idp',
            'github_releases_url' => 'https://github.com/OpenConext/Mujina/releases',
        ],
        'manage' => [
            'label' => 'Manage',
            'deploy_name' => 'manage',
            'github_releases_url' => 'https://github.com/OpenConext/OpenConext-manage/releases',
        ],
        'engineblock' => [
            'label' => 'EngineBlock',
            'deploy_name' => 'engineblock',
            'github_releases_url' => 'https://github.com/OpenConext/OpenConext-EngineBlock/releases',
        ],
        'profile' => [
            'label' => 'Profile',
            'deploy_name' => 'profile',
            'github_releases_url' => 'https://github.com/OpenConext/OpenConext-profile/releases',
        ],
        'aa' => [
            'label' => 'Attribute Authority',
            'deploy_name' => 'aa',
            'github_releases_url' => 'https://github.com/OpenConext/OpenConext-attribute-aggregation/releases',
        ],
        'pdp' => [
            'label' => 'PDP',
            'deploy_name' => 'pdp',
            'github_releases_url' => 'https://github.com/OpenConext/OpenConext-PDP/releases',
        ],
        'invite' => [
            'label' => 'Invite',
            'deploy_name' => 'invite',
            'github_releases_url' => 'https://github.com/OpenConext/OpenConext-invite/releases',
        ],
        'stepup-gateway' => [
            'label' => 'Stepup-Gateway',
            'deploy_name' => 'gateway',
            'github_releases_url' => 'https://github.com/OpenConext/Stepup-Gateway/releases',
        ],
        'stepup-ra' => [
            'label' => 'Stepup-RA',
            'deploy_name' => 'ra',
            'github_releases_url' => 'https://github.com/OpenConext/Stepup-RA/releases',
        ],
        'stepup-middleware' => [
            'label' => 'Stepup-Middleware / Selfservice',
            'deploy_name' => 'middleware',
            'github_releases_url' => 'https://github.com/OpenConext/Stepup-middleware/releases',
        ],
        'sp-dashboard' => [
            'label' => 'SP Dashboard',
            'deploy_name' => 'spdashboard',
            'github_releases_url' => '',
        ],
        'platform' => [
            'label' => 'Platform / Infra',
            'deploy_name' => 'platform',
            'github_releases_url' => '',
        ],
    ],

    // Environments a release can be deployed to, in deployment order.
    // These mirror the steps of the standard deployment workflow. Progress of a
    // release = the number of environments with the "installed" checkbox,
    // e.g. all 7 checked = 100%, only test2 = 1/7th.
    // The production environments (prodblauw / prodrood) drive the
    // "behind" and "overlap" alerts: a release is behind when the earliest
    // planned production date has passed without the application being
    // installed there, and releases sharing a planned production date are
    // flagged as overlap.
    'environments' => [
        'test2'     => ['label' => 'test2',     'production' => false],
        'test'      => ['label' => 'test',      'production' => false],
        'accrood'   => ['label' => 'accrood',   'production' => false],
        'accblauw'  => ['label' => 'accblauw',  'production' => false],
        '4eset'     => ['label' => '4eset',     'production' => false],
        'prodblauw' => ['label' => 'prodblauw', 'production' => true],
        'prodrood'  => ['label' => 'prodrood',  'production' => true],
    ],

    // Default items of the "Planned dates" list when creating a new release.
    // The list is editable per release (add / remove items).
    'default_plan_items' => [
        'test2', 'test', 'accrood', 'accblauw', '4eset',
        'prodblauw', 'prodrood', 'LB 50/50',
    ],

    'github' => [
        // Optional personal access token to raise the rate limit (60/hour
        // anonymous, 5000/hour with a token). Can also be set via the
        // GITHUB_TOKEN environment variable.
        'token' => '',
    ],

    'oidc' => [
        // Issuer / base URL of the OpenID Connect provider, e.g.
        // 'https://oidc.example.org'. Empty when dev_bypass is used.
        'url' => '',
        'client_id' => '',
        'client_secret' => '',
        // Redirect URI; derived from the request host when empty.
        'redirect_uri' => '',
        // When true, the login page offers a local login without an OIDC
        // provider. Intended for local development only.
        'dev_bypass' => true,
    ],
];
