<?php

declare(strict_types=1);

/**
 * OIDC login (authorization code flow + PKCE). Every user that can
 * authenticate with the configured provider may view and edit all releases.
 *
 * For local development the config can enable dev_bypass, which offers a
 * login without a provider.
 */
class Auth
{
    /** @phpstan-ignore-next-line - Used indirectly via OIDCDiscovery */
    private HttpClient $httpClient;
    private OIDCDiscovery $oidcDiscovery;

    public function __construct(HttpClient $httpClient, OIDCDiscovery $oidcDiscovery)
    {
        $this->httpClient = $httpClient;
        $this->oidcDiscovery = $oidcDiscovery;
    }

    /**
     * Static factory for backward compatibility.
     */
    public static function create(): self
    {
        $httpClient = new HttpClient();
        $oidcDiscovery = new OIDCDiscovery($httpClient);
        return new self($httpClient, $oidcDiscovery);
    }

    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function requireLogin(): void
    {
        if (self::user() === null) {
            header('Location: /login');
            exit;
        }
    }

    public static function loggedIn(): bool
    {
        return self::user() !== null;
    }

    /**
     * Verify CSRF token for POST requests.
     */
    public static function verifyCsrf(): void
    {
        if (!Csrf::validatePost()) {
            http_response_code(403);
            exit('Invalid CSRF token.');
        }
    }

    /**
     * Apply rate limiting to login attempts.
     */
    public static function checkRateLimit(): void
    {
        if (!rateLimit('login', 5, 60)) {
            http_response_code(429);
            exit('Too many login attempts. Please try again later.');
        }
    }

    public static function devLogin(): void
    {
        if (!Csrf::validatePost()) {
            http_response_code(403);
            exit('Invalid CSRF token.');
        }

        $_SESSION['user'] = [
            'sub' => 'dev-user',
            'nickname' => 'Local developer',
            'name' => 'Local developer',
            'email' => 'dev@localhost',
        ];
        self::registerLogin();
        Csrf::regenerate(); // Prevent session fixation
        session_write_close();
        header('Location: /');
        exit;
    }

    /**
     * Records the logged-in user (nickname is used as display name).
     */
    public static function registerLogin(): void
    {
        $user = self::user();
        if ($user === null || !isset($user['sub'])) {
            return;
        }
        try {
            (new Users(Database::pdoStatic()))->upsert($user);
        } catch (PDOException $e) {
            // A failed bookkeeping write should not block the login.
            error_log("Failed to record login: " . $e->getMessage());
        }
    }

    public function startLogin(Config $config): void
    {
        $oidc = $config->oidc();
        $discovery = $this->oidcDiscovery->discover($oidc['url']);

        $_SESSION['oidc_state'] = bin2hex(random_bytes(16));
        $_SESSION['oidc_nonce'] = bin2hex(random_bytes(16));
        $_SESSION['oidc_verifier'] = JwtValidator::base64UrlEncode(random_bytes(32));

        $params = [
            'response_type' => 'code',
            'client_id' => $oidc['client_id'],
            'redirect_uri' => self::redirectUri($config, $oidc),
            'scope' => 'openid profile email',
            'state' => $_SESSION['oidc_state'],
            'nonce' => $_SESSION['oidc_nonce'],
            'code_challenge' => JwtValidator::base64UrlEncode(
                hash('sha256', $_SESSION['oidc_verifier'], true)
            ),
            'code_challenge_method' => 'S256',
        ];
        header('Location: ' . $discovery['authorization_endpoint'] . '?' . http_build_query($params));
        exit;
    }

    /**
     * Static wrapper for backward compatibility.
     */
    public static function startLoginStatic(Config $config): void
    {
        self::create()->startLogin($config);
    }

    public function handleCallback(Config $config): void
    {
        self::checkRateLimit();

        $oidc = $config->oidc();
        $code = $_GET['code'] ?? '';
        $state = $_GET['state'] ?? '';

        if ($code === '' || $state === '' || $state !== ($_SESSION['oidc_state'] ?? '')) {
            error_log("Invalid OIDC callback: state mismatch or missing code");
            http_response_code(400);
            exit('Invalid OIDC callback (state mismatch).');
        }

        $discovery = $this->oidcDiscovery->discover($oidc['url']);
        $tokens = $this->exchangeCode($oidc, $discovery, $code, self::redirectUri($config, $oidc));

        $jwt = (string)($tokens['id_token'] ?? '');
        if ($jwt === '') {
            error_log("Missing id_token in OIDC response");
            http_response_code(400);
            exit('Missing id_token in OIDC response.');
        }

        // Verify claims FIRST (exp check happens here, fail fast for expired tokens)
        $token = JwtValidator::decode($jwt);
        self::validateToken($token, $oidc, $_SESSION['oidc_nonce'] ?? '');

        // Then verify signature (expensive operation)
        $jwks = $this->oidcDiscovery->fetchJwks($discovery['jwks_uri']);
        self::verifySignature($jwt, $jwks, 'RS256', null);

        $_SESSION['user'] = [
            'sub' => $token['sub'],
            'nickname' => $token['nickname']
                ?? ($token['preferred_username'] ?? ($token['name'] ?? $token['sub'])),
            'name' => $token['name'] ?? null,
            'email' => $token['email'] ?? '',
        ];
        self::registerLogin();
        unset($_SESSION['oidc_state'], $_SESSION['oidc_nonce'],
              $_SESSION['oidc_verifier']);
        OIDCDiscovery::clearCache();
        Csrf::regenerate();
        session_write_close();
        header('Location: /');
        exit;
    }

    /**
     * Static wrapper for backward compatibility.
     */
    public static function handleCallbackStatic(Config $config): void
    {
        self::create()->handleCallback($config);
    }

    public static function logout(): void
    {
        $_SESSION = [];
        session_destroy();
        header('Location: /login');
        exit;
    }

    private static function redirectUri(Config $config, array $oidc): string
    {
        if (($oidc['redirect_uri'] ?? '') !== '') {
            return $oidc['redirect_uri'];
        }
        return $config->baseUrl() . '/callback.php';
    }

    /**
     * Exchange authorization code for tokens.
     *
     * @param array $oidc OIDC configuration
     * @param array $discovery OIDC discovery document
     * @param string $code Authorization code
     * @param string $redirectUri Redirect URI
     * @return array Tokens (id_token, access_token, etc.)
     * @throws RuntimeException On failure
     */
    private function exchangeCode(
        array $oidc,
        array $discovery,
        string $code,
        string $redirectUri
    ): array {
        $ch = curl_init($discovery['token_endpoint']);
        if ($ch === false) {
            throw new RuntimeException('Cannot initialize cURL for token exchange.');
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'grant_type' => 'code',
                'code' => $code,
                'client_id' => $oidc['client_id'],
                'client_secret' => $oidc['client_secret'],
                'redirect_uri' => $redirectUri,
                'code_verifier' => $_SESSION['oidc_verifier'] ?? '',
            ]),
            CURLOPT_TIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('cURL error during token exchange: ' . $error);
        }

        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($status !== 200) {
            error_log("OIDC token exchange failed with HTTP $status: $response");
            throw new RuntimeException('OIDC token exchange failed with HTTP ' . $status);
        }

        $tokens = json_decode($response, true);
        if (!is_array($tokens) || !isset($tokens['id_token'])) {
            throw new RuntimeException('Invalid token exchange response: missing id_token.');
        }

        return $tokens;
    }

    /**
     * Validate JWT token claims against expected values.
     *
     * @param array $token The decoded JWT payload
     * @param array $oidc OIDC configuration
     * @param string $nonce Expected nonce
     * @return void
     * @throws InvalidArgumentException On validation failure
     */
    private static function validateToken(array $token, array $oidc, string $nonce): void
    {
        JwtValidator::verifyClaims(
            $token,
            $oidc['url'],
            $oidc['client_id'],
            $nonce
        );
    }

    /**
     * Verify JWT signature.
     *
     * @param string $jwt JWT to verify
     * @param array $jwks JWKS keys
     * @param string $expectedAlg Expected algorithm
     * @param string $expectedKid Expected key ID
     * @return void
     * @throws InvalidArgumentException On verification failure
     */
    private static function verifySignature(string $jwt, array $jwks, string $expectedAlg, ?string $expectedKid): void
    {
        JwtValidator::verifySignature($jwt, $jwks, $expectedAlg, $expectedKid);
    }
}
