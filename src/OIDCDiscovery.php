<?php

declare(strict_types=1);

/**
 * OIDC Discovery document fetching and caching.
 */
class OIDCDiscovery
{
    private static ?array $cache = null;

    /**
     * Fetch and cache the OIDC discovery document.
     *
     * @param string $issuer The OIDC issuer URL
     * @return array The discovery document
     * @throws RuntimeException If the discovery document cannot be fetched or is invalid
     */
    public static function discover(string $issuer): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $url = rtrim($issuer, '/') . '/.well-known/openid-configuration';
        $response = self::httpGet($url);

        if ($response === null) {
            throw new RuntimeException('Could not fetch OIDC discovery document from ' . $url);
        }

        $document = json_decode($response, true);
        if (!is_array($document) || !isset($document['authorization_endpoint'])) {
            throw new RuntimeException('Invalid OIDC discovery document from ' . $url);
        }

        self::$cache = $document;
        return $document;
    }

    /**
     * Get JWKS URI from discovery document.
     */
    public static function getJwksUri(string $issuer): string
    {
        $discovery = self::discover($issuer);
        if (!isset($discovery['jwks_uri'])) {
            throw new RuntimeException('JWKS URI not found in discovery document.');
        }
        return $discovery['jwks_uri'];
    }

    /**
     * Get authorization endpoint from discovery document.
     */
    public static function getAuthorizationEndpoint(string $issuer): string
    {
        $discovery = self::discover($issuer);
        return $discovery['authorization_endpoint'];
    }

    /**
     * Get token endpoint from discovery document.
     */
    public static function getTokenEndpoint(string $issuer): string
    {
        $discovery = self::discover($issuer);
        return $discovery['token_endpoint'];
    }

    /**
     * Clear the discovery document cache.
     */
    public static function clearCache(): void
    {
        self::$cache = null;
    }

    /**
     * Make an HTTP GET request.
     *
     * @param string $url The URL to fetch
     * @return string|null The response body, or null on failure
     */
    private static function httpGet(string $url): ?string
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
        ]);

        $response = curl_exec($ch);
        if ($response === false) {
            return null;
        }

        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        if ($status !== 200) {
            return null;
        }

        return $response;
    }

    /**
     * Fetch JWKS from the JWKS URI.
     *
     * @param string $jwksUri The JWKS URI
     * @return array The JWKS keys
     * @throws RuntimeException If the JWKS cannot be fetched
     */
    public static function fetchJwks(string $jwksUri): array
    {
        $response = self::httpGet($jwksUri);
        if ($response === null) {
            throw new RuntimeException('Could not fetch JWKS from ' . $jwksUri);
        }

        $jwks = json_decode($response, true);
        if (!is_array($jwks) || !isset($jwks['keys'])) {
            throw new RuntimeException('Invalid JWKS format from ' . $jwksUri);
        }

        return $jwks;
    }
}
