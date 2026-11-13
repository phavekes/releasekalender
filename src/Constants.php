<?php

declare(strict_types=1);

/**
 * Application constants to replace magic strings/numbers.
 */
final class Constants
{
    // Environment names
    public const ENV_TEST2 = 'test2';
    public const ENV_TEST = 'test';
    public const ENV_ACCROOD = 'accrood';
    public const ENV_ACCBLAUW = 'accblauw';
    public const ENV_4ESET = '4eset';
    public const ENV_PRODBLAUW = 'prodblauw';
    public const ENV_PRODROOD = 'prodrood';
    public const ENV_LB_50_50 = 'LB 50/50';

    // Release status values
    public const STATUS_COMPLETE = 'Complete';
    public const STATUS_NOT_STARTED = 'Not started';

    // Alert flags
    public const ALERT_BEHIND = 'behind';
    public const ALERT_OVERLAP = 'overlap';

    // Session keys
    public const SESSION_USER = 'user';
    public const SESSION_OIDC_STATE = 'oidc_state';
    public const SESSION_OIDC_NONCE = 'oidc_nonce';
    public const SESSION_OIDC_VERIFIER = 'oidc_verifier';

    // Filter values
    public const FILTER_ALL = 'all';
    public const FILTER_BEHIND = 'behind';
    public const FILTER_OVERLAP = 'overlap';
    public const FILTER_OPEN = 'open';
    public const FILTER_MINE = 'mine';

    // Application name
    public const APP_NAME = 'ReleaseCalendar';

    // Pagination
    public const DEFAULT_PER_PAGE = 50;

    // Default starting release number
    public const DEFAULT_RELEASE_NR = 700;

    // Date format
    public const DATE_FORMAT = 'Y-m-d';
    public const DATE_DISPLAY_FORMAT = 'd M Y';

    // Environment variable names
    public const ENV_DB_HOST = 'DB_HOST';
    public const ENV_DB_PORT = 'DB_PORT';
    public const ENV_DB_NAME = 'DB_NAME';
    public const ENV_DB_USER = 'DB_USER';
    public const ENV_DB_PASS = 'DB_PASS';
    public const ENV_OIDC_URL = 'OIDC_URL';
    public const ENV_OIDC_CLIENT_ID = 'OIDC_CLIENT_ID';
    public const ENV_OIDC_CLIENT_SECRET = 'OIDC_CLIENT_SECRET';
    public const ENV_GITHUB_TOKEN = 'GITHUB_TOKEN';
    public const ENV_DEV_BYPASS = 'DEV_BYPASS';
    public const ENV_SEED_EXAMPLE_DATA = 'SEED_EXAMPLE_DATA';

    /**
     * All production environments.
     */
    public static function productionEnvironments(): array
    {
        return [
            self::ENV_PRODBLAUW,
            self::ENV_PRODROOD,
        ];
    }

    /**
     * All non-production environments in order.
     */
    public static function nonProductionEnvironments(): array
    {
        return [
            self::ENV_TEST2,
            self::ENV_TEST,
            self::ENV_ACCROOD,
            self::ENV_ACCBLAUW,
            self::ENV_4ESET,
        ];
    }

    /**
     * All environments in deployment order.
     */
    public static function allEnvironments(): array
    {
        return array_merge(
            self::nonProductionEnvironments(),
            self::productionEnvironments(),
            [self::ENV_LB_50_50]
        );
    }
}
