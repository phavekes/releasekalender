<?php

declare(strict_types=1);

/**
 * OIDC Discovery document fetching and caching.
 */
class OIDCDiscovery
{
    private static ?HttpClient $httpClient = null;
    private static ?array $cache = null;
    private HttpClient $client;

    public function __construct(HttpClient $httpClient)
    {
        $this->client = $httpClient;
    }

    /**
     * Set the static HTTP client instance.
     */
    public static function setHttpClient(HttpClient $client): void
    {
        self::$httpClient = $client;
        self::$cache = null; // Reset cache when client changes
    }

    /**
     * Get the HTTP client instance.
     */
    private static function client(): HttpClient
    {
        if (self::$httpClient === null) {
            self::$httpClient = new HttpClient();
        }
        return self::$httpClient;
    }

    /**
     * Fetch and cache the OIDC discovery document.
     *
     * @param string $issuer The OIDC issuer URL
     * @return array The discovery document
     * @throws RuntimeException If the discovery document cannot be fetched or is invalid
     */
    public function discover(string $issuer): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $url = rtrim($issuer, '/') . '/.well-known/openid-configuration';
        $response = $this->client->get($url);

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
     * Static wrapper for discover.
     */
    public static function discoverStatic(string $issuer): array
    {
        return (new self(self::client()))->discover($issuer);
    }

    /**
     * Get JWKS URI from discovery document.
     */
    public function getJwksUri(string $issuer): string
    {
        $discovery = $this->discover($issuer);
        if (!isset($discovery['jwks_uri'])) {
            throw new RuntimeException('JWKS URI not found in discovery document.');
        }
        return $discovery['jwks_uri'];
    }

    /**
     * Static wrapper for getJwksUri.
     */
    public static function getJwksUriStatic(string $issuer): string
    {
        return (new self(self::client()))->getJwksUri($issuer);
    }

    /**
     * Get authorization endpoint from discovery document.
     */
    public function getAuthorizationEndpoint(string $issuer): string
    {
        $discovery = $this->discover($issuer);
        return $discovery['authorization_endpoint'];
    }

    /**
     * Static wrapper for getAuthorizationEndpoint.
     */
    public static function getAuthorizationEndpointStatic(string $issuer): string
    {
        return (new self(self::client()))->getAuthorizationEndpoint($issuer);
    }

    /**
     * Get token endpoint from discovery document.
     */
    public function getTokenEndpoint(string $issuer): string
    {
        $discovery = $this->discover($issuer);
        return $discovery['token_endpoint'];
    }

    /**
     * Static wrapper for getTokenEndpoint.
     */
    public static function getTokenEndpointStatic(string $issuer): string
    {
        return (new self(self::client()))->getTokenEndpoint($issuer);
    }

    /**
     * Clear the discovery document cache.
     */
    public static function clearCache(): void
    {
        self::$cache = null;
    }

    /**
     * Fetch JWKS from the JWKS URI.
     *
     * @param string $jwksUri The JWKS URI
     * @return array The JWKS keys
     * @throws RuntimeException If the JWKS cannot be fetched
     */
    public function fetchJwks(string $jwksUri): array
    {
        $response = $this->client->get($jwksUri);
        if ($response === null) {
            throw new RuntimeException('Could not fetch JWKS from ' . $jwksUri);
        }

        $jwks = json_decode($response, true);
        if (!is_array($jwks) || !isset($jwks['keys'])) {
            throw new RuntimeException('Invalid JWKS format from ' . $jwksUri);
        }

        return $jwks;
    }

    /**
     * Static wrapper for fetchJwks.
     */
    public static function fetchJwksStatic(string $jwksUri): array
    {
        return (new self(self::client()))->fetchJwks($jwksUri);
    }
}
