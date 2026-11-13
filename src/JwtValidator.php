<?php

declare(strict_types=1);

/**
 * JWT validation utilities for OIDC authentication.
 */
class JwtValidator
{
    /**
     * Decode a JWT and return its payload.
     *
     * @param string $jwt The JWT to decode
     * @return array The decoded payload
     * @throws InvalidArgumentException If the JWT is malformed
     */
    public static function decode(string $jwt): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            throw new InvalidArgumentException('Malformed id_token: does not have 3 parts.');
        }

        $payload = self::base64UrlDecode($parts[1]);
        if ($payload === null) {
            throw new InvalidArgumentException('Malformed id_token: cannot decode payload.');
        }

        $payloadArray = json_decode($payload, true);
        if (!is_array($payloadArray) || !isset($payloadArray['sub'])) {
            throw new InvalidArgumentException('Malformed id_token: invalid payload structure.');
        }

        return $payloadArray;
    }

    /**
     * Verify JWT claims against expected values.
     *
     * Note: This should be called BEFORE signature verification to fail fast
     * on obviously invalid tokens (expired, wrong issuer/audience, etc.).
     *
     * @param array $token The decoded JWT payload
     * @param string $issuer Expected issuer
     * @param string $clientId Expected audience/client ID
     * @param string $nonce Expected nonce value
     * @return void
     * @throws InvalidArgumentException If any claim is invalid
     */
    public static function verifyClaims(
        array $token,
        string $issuer,
        string $clientId,
        string $nonce
    ): void {
        // Validate required fields exist first
        if (!isset($token['sub'], $token['iss'], $token['exp'], $token['aud'], $token['nonce'])) {
            throw new InvalidArgumentException('Missing required JWT claims.');
        }

        // Verify expiration FIRST (fail fast for expired tokens)
        if ($token['exp'] < time()) {
            throw new InvalidArgumentException('id_token expired.');
        }

        // Verify issuer
        if ($token['iss'] !== rtrim($issuer, '/')) {
            throw new InvalidArgumentException('id_token issuer mismatch.');
        }

        // Verify audience
        $audience = (array)$token['aud'];
        if (!in_array($clientId, $audience, true)) {
            throw new InvalidArgumentException('id_token audience mismatch.');
        }

        // Verify nonce
        if ($token['nonce'] !== $nonce) {
            throw new InvalidArgumentException('id_token nonce mismatch.');
        }
    }

    /**
     * Verify the JWT signature using the JWKS from the OIDC provider.
     *
     * @param string $jwt The JWT to verify
     * @param array $jwks The JWKS from the OIDC provider
     * @param string $expectedAlg Expected algorithm (e.g., 'RS256')
     * @param string $expectedKid Expected key ID from JWT header
     * @return void
     * @throws InvalidArgumentException If signature verification fails
     */
    public static function verifySignature(
        string $jwt,
        array $jwks,
        string $expectedAlg = 'RS256',
        ?string $expectedKid = null
    ): void {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            throw new InvalidArgumentException('Malformed id_token for signature verification.');
        }

        // Decode header to get algorithm and key ID
        $header = self::decodeHeader($parts[0]);

        if (($header['alg'] ?? '') !== $expectedAlg) {
            throw new InvalidArgumentException(
                'Unsupported id_token algorithm ' . ($header['alg'] ?? '?') . ', expected ' . $expectedAlg
            );
        }

        // Get the key ID from header
        $kid = $header['kid'] ?? null;
        if ($expectedKid !== null && $kid !== $expectedKid) {
            throw new InvalidArgumentException('Key ID mismatch in JWT header.');
        }

        // Find the matching key in JWKS
        $signatureB64 = $parts[2];
        $signingInput = $parts[0] . '.' . $parts[1];

        foreach ($jwks['keys'] as $key) {
            if (($key['kty'] ?? '') !== 'RSA') {
                continue;
            }
            if ($kid !== null && ($key['kid'] ?? null) !== $kid) {
                continue;
            }
            if (!isset($key['n'], $key['e'])) {
                continue;
            }

            $pem = self::jwkToPem($key['n'], $key['e']);
            $signature = self::base64UrlDecode($signatureB64);

            if ($signature === null) {
                throw new InvalidArgumentException('Cannot decode JWT signature.');
            }

            $result = openssl_verify($signingInput, $signature, $pem, OPENSSL_ALGO_SHA256);
            if ($result === 1) {
                return;
            }
        }

        throw new InvalidArgumentException('id_token signature verification failed or no matching key found.');
    }

    /**
     * Decode the JWT header.
     */
    private static function decodeHeader(string $headerB64): array
    {
        $header = self::base64UrlDecode($headerB64);
        if ($header === null) {
            throw new InvalidArgumentException('Cannot decode JWT header.');
        }

        $headerArray = json_decode($header, true);
        if (!is_array($headerArray)) {
            throw new InvalidArgumentException('Invalid JWT header format.');
        }

        return $headerArray;
    }

    /**
     * Base64 URL decode.
     */
    public static function base64UrlDecode(string $data): ?string
    {
        $base64 = strtr($data, '-_', '+/');
        // Add padding if needed
        $padding = strlen($base64) % 4;
        if ($padding > 0) {
            $base64 .= str_repeat('=', 4 - $padding);
        }
        
        $result = base64_decode($base64, true);
        if ($result === false) {
            return null;
        }
        return $result;
    }

    /**
     * Convert JWK to PEM format.
     */
    private static function jwkToPem(string $modulusB64, string $exponentB64): string
    {
        $modulus = self::base64UrlDecode($modulusB64);
        if ($modulus === null) {
            throw new InvalidArgumentException('Cannot decode modulus.');
        }

        $exponent = self::base64UrlDecode($exponentB64);
        if ($exponent === null) {
            throw new InvalidArgumentException('Cannot decode exponent.');
        }

        $rsaPublicKey = self::derSequence(self::derInteger($modulus) . self::derInteger($exponent));
        $algorithm = pack('H*', '300d06092a86486f70d0101010500'); // SEQ { OID rsaEncryption, NULL }
        $body = $algorithm . "\x03" . chr(strlen($rsaPublicKey) + 1) . "\x00" . $rsaPublicKey;
        $der = self::derSequence($body);

        return "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split(base64_encode($der), 64)
            . "-----END PUBLIC KEY-----";
    }

    /**
     * DER encode an integer.
     */
    private static function derInteger(string $value): string
    {
        if ($value !== '' && (ord($value[0]) & 0x80)) {
            $value = "\x00" . $value;
        }
        return "\x02" . self::derLength(strlen($value)) . $value;
    }

    /**
     * DER encode a sequence.
     */
    private static function derSequence(string $content): string
    {
        return "\x30" . self::derLength(strlen($content)) . $content;
    }

    /**
     * DER encode a length.
     */
    private static function derLength(int $length): string
    {
        if ($length < 0x80) {
            return chr($length);
        }
        $bytes = ltrim(pack('N', $length), "\x00");
        return chr(0x80 | strlen($bytes)) . $bytes;
    }

    /**
     * Base64 URL encode.
     */
    public static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
