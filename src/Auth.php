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
    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function requireLogin(): void
    {
        if (self::user() === null) {
            header('Location: login.php');
            exit;
        }
    }

    public static function loggedIn(): bool
    {
        return self::user() !== null;
    }

    public static function devLogin(): void
    {
        $_SESSION['user'] = [
            'sub' => 'dev-user',
            'nickname' => 'Local developer',
            'name' => 'Local developer',
            'email' => 'dev@localhost',
        ];
        self::registerLogin();
        header('Location: index.php');
        exit;
    }

    /** Records the logged-in user (nickname is used as display name). */
    public static function registerLogin(): void
    {
        $user = self::user();
        if ($user === null || !isset($user['sub'])) {
            return;
        }
        try {
            (new Users(Database::pdo()))->upsert($user);
        } catch (PDOException $e) {
            // A failed bookkeeping write should not block the login.
        }
    }

    public static function startLogin(Config $config): void
    {
        $oidc = $config->oidc();
        $discovery = self::discover($oidc['url']);

        $_SESSION['oidc_state'] = bin2hex(random_bytes(16));
        $_SESSION['oidc_nonce'] = bin2hex(random_bytes(16));
        $_SESSION['oidc_verifier'] = self::base64Url(random_bytes(32));

        $params = [
            'response_type' => 'code',
            'client_id' => $oidc['client_id'],
            'redirect_uri' => self::redirectUri($config, $oidc),
            'scope' => 'openid profile email',
            'state' => $_SESSION['oidc_state'],
            'nonce' => $_SESSION['oidc_nonce'],
            'code_challenge' => self::base64Url(hash('sha256', $_SESSION['oidc_verifier'], true)),
            'code_challenge_method' => 'S256',
        ];
        header('Location: ' . $discovery['authorization_endpoint'] . '?' . http_build_query($params));
        exit;
    }

    public static function handleCallback(Config $config): void
    {
        $oidc = $config->oidc();
        $code = $_GET['code'] ?? '';
        $state = $_GET['state'] ?? '';

        if ($code === '' || $state === '' || $state !== ($_SESSION['oidc_state'] ?? '')) {
            http_response_code(400);
            exit('Invalid OIDC callback (state mismatch).');
        }

        $discovery = self::discover($oidc['url']);
        $tokens = self::exchangeCode($oidc, $discovery, $code, self::redirectUri($config, $oidc));

        $jwt = (string)($tokens['id_token'] ?? '');
        $token = self::decodeJwt($jwt);
        self::verifyClaims($token, $oidc);
        self::verifySignature($jwt, $discovery);

        $_SESSION['user'] = [
            'sub' => $token['sub'],
            'nickname' => $token['nickname']
                ?? ($token['preferred_username'] ?? ($token['name'] ?? $token['sub'])),
            'name' => $token['name'] ?? null,
            'email' => $token['email'] ?? '',
        ];
        self::registerLogin();
        unset($_SESSION['oidc_state'], $_SESSION['oidc_nonce'],
              $_SESSION['oidc_verifier'], $_SESSION['oidc_discovery']);
        header('Location: index.php');
        exit;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        session_destroy();
        header('Location: login.php');
        exit;
    }

    private static function redirectUri(Config $config, array $oidc): string
    {
        if (($oidc['redirect_uri'] ?? '') !== '') {
            return $oidc['redirect_uri'];
        }
        return $config->baseUrl() . '/callback.php';
    }

    private static function discover(string $issuer): array
    {
        if (isset($_SESSION['oidc_discovery'])) {
            return $_SESSION['oidc_discovery'];
        }
        $url = rtrim($issuer, '/') . '/.well-known/openid-configuration';
        $response = self::httpGet($url);
        if ($response === null) {
            http_response_code(502);
            exit('Could not fetch OIDC discovery document from ' . $url);
        }
        $document = json_decode($response, true);
        if (!is_array($document) || !isset($document['authorization_endpoint'])) {
            http_response_code(502);
            exit('Invalid OIDC discovery document from ' . $url);
        }
        $_SESSION['oidc_discovery'] = $document;
        return $document;
    }

    private static function exchangeCode(array $oidc, array $discovery, string $code, string $redirectUri): array
    {
        $ch = curl_init($discovery['token_endpoint']);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'grant_type' => 'authorization_code',
                'code' => $code,
                'client_id' => $oidc['client_id'],
                'client_secret' => $oidc['client_secret'],
                'redirect_uri' => $redirectUri,
                'code_verifier' => $_SESSION['oidc_verifier'] ?? '',
            ]),
            CURLOPT_TIMEOUT => 10,
        ]);
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

        $tokens = is_string($response) ? json_decode($response, true) : null;
        if ($status !== 200 || !is_array($tokens) || !isset($tokens['id_token'])) {
            http_response_code(502);
            exit('OIDC token exchange failed.');
        }
        return $tokens;
    }

    private static function decodeJwt(string $jwt): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            http_response_code(400);
            exit('Malformed id_token.');
        }
        $payload = json_decode(self::base64UrlDecode($parts[1]), true);
        if (!is_array($payload) || !isset($payload['sub'])) {
            http_response_code(400);
            exit('Malformed id_token payload.');
        }
        return $payload;
    }

    private static function verifyClaims(array $token, array $oidc): void
    {
        if (($token['iss'] ?? '') !== rtrim($oidc['url'], '/')) {
            http_response_code(400);
            exit('id_token issuer mismatch.');
        }
        $audience = (array)($token['aud'] ?? []);
        if (!in_array($oidc['client_id'], $audience, true)) {
            http_response_code(400);
            exit('id_token audience mismatch.');
        }
        if (($token['exp'] ?? 0) < time()) {
            http_response_code(400);
            exit('id_token expired.');
        }
        if (($token['nonce'] ?? '') !== ($_SESSION['oidc_nonce'] ?? '')) {
            http_response_code(400);
            exit('id_token nonce mismatch.');
        }
    }

    private static function verifySignature(string $jwt, array $discovery): void
    {
        [$headerB64, $payloadB64, $signatureB64] = explode('.', $jwt);
        $header = json_decode(self::base64UrlDecode($headerB64), true);

        if (($header['alg'] ?? '') !== 'RS256') {
            http_response_code(400);
            exit('Unsupported id_token algorithm ' . ($header['alg'] ?? '?') . ', expected RS256.');
        }

        $jwks = json_decode((string)self::httpGet($discovery['jwks_uri'] ?? ''), true);
        if (!is_array($jwks)) {
            http_response_code(502);
            exit('Could not fetch JWKS.');
        }

        foreach ($jwks['keys'] as $key) {
            if (($key['kty'] ?? '') !== 'RSA' || ($key['kid'] ?? null) !== ($header['kid'] ?? null)) {
                continue;
            }
            $pem = self::jwkToPem($key['n'], $key['e']);
            if (openssl_verify($headerB64 . '.' . $payloadB64,
                    self::base64UrlDecode($signatureB64), $pem, OPENSSL_ALGO_SHA256) === 1) {
                return;
            }
            http_response_code(400);
            exit('id_token signature verification failed.');
        }
        http_response_code(400);
        exit('No matching key found in JWKS for id_token verification.');
    }

    /** Build a PEM public key from JWKS modulus and exponent. */
    private static function jwkToPem(string $modulusB64, string $exponentB64): string
    {
        $modulus = self::base64UrlDecode($modulusB64);
        $exponent = self::base64UrlDecode($exponentB64);
        $rsaPublicKey = self::derSequence(self::derInteger($modulus) . self::derInteger($exponent));
        $algorithm = pack('H*', '300d06092a86486f70d0101010500'); // SEQ { OID rsaEncryption, NULL }
        $body = $algorithm . "\x03" . chr(strlen($rsaPublicKey) + 1) . "\x00" . $rsaPublicKey;
        $der = self::derSequence($body);
        return "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split(base64_encode($der), 64)
            . "-----END PUBLIC KEY-----";
    }

    private static function derSequence(string $content): string
    {
        return "\x30" . self::derLength(strlen($content)) . $content;
    }

    private static function derInteger(string $value): string
    {
        if ($value !== '' && (ord($value[0]) & 0x80)) {
            $value = "\x00" . $value;
        }
        return "\x02" . self::derLength(strlen($value)) . $value;
    }

    private static function derLength(int $length): string
    {
        if ($length < 0x80) {
            return chr($length);
        }
        $bytes = ltrim(pack('N', $length), "\x00");
        return chr(0x80 | strlen($bytes)) . $bytes;
    }

    private static function httpGet(string $url): ?string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        return $status === 200 && is_string($response) ? $response : null;
    }

    public static function base64Url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
