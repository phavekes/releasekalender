<?php

namespace ReleaseCalendar\Tests;

use PHPUnit\Framework\TestCase;
use PDO;
use RuntimeException;

require_once __DIR__ . '/bootstrap.php';

/**
 * Tests for authentication and OIDC flow.
 */
class AuthTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $this->pdo->exec('CREATE TABLE users (sub TEXT PRIMARY KEY, nickname TEXT, name TEXT, email TEXT)');
    }

    protected function tearDown(): void
    {
        // Clear session data between tests
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    public function testCsrfTokenGeneration(): void
    {
        $_SESSION = [];
        
        $token1 = \Csrf::generate();
        $this->assertIsString($token1);
        $this->assertSame(64, strlen($token1)); // 32 bytes = 64 hex chars
        
        $token2 = \Csrf::generate();
        $this->assertSame($token1, $token2); // Same token returned
    }

    public function testCsrfTokenValidation(): void
    {
        $_SESSION = [];
        
        $token = \Csrf::generate();
        
        $this->assertTrue(\Csrf::validate($token));
        $this->assertFalse(\Csrf::validate('invalid-token'));
        $this->assertFalse(\Csrf::validate(null));
    }

    public function testCsrfTokenValidationFailsWithOldToken(): void
    {
        $_SESSION = [];
        
        $token = \Csrf::generate();
        
        // Regenerate token
        \Csrf::regenerate();
        
        $newToken = \Csrf::generate();
        
        $this->assertNotSame($token, $newToken);
        $this->assertTrue(\Csrf::validate($newToken));
        $this->assertFalse(\Csrf::validate($token));
    }

    public function testCsrfFieldGeneration(): void
    {
        $_SESSION = [];
        
        $field = \Csrf::field();
        
        $this->assertStringContainsString('csrf_token', $field);
        $this->assertStringContainsString('type="hidden"', $field);
    }

    public function testRateLimitingAllowsRequestsWithinLimit(): void
    {
        // Start session for rate limiting with a unique key
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        
        // Clear session for this test
        $_SESSION = [];
        
        // Use a unique key to avoid conflicts with other tests
        $key = 'test-limit-' . uniqid();
        
        // Should allow 5 requests within 60 seconds
        for ($i = 0; $i < 5; $i++) {
            $this->assertTrue(rateLimit($key, 5, 60), "Request $i should be allowed");
        }
    }

    public function testRateLimitingBlocksRequestsOverLimit(): void
    {
        // Start session for rate limiting with a unique key
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        
        // Clear session for this test
        $_SESSION = [];
        
        // Use a unique key to avoid conflicts with other tests
        $key = 'test-block-limit-' . uniqid();
        
        // Use up all attempts (1st call initializes with attempts=1, so we need 4 more)
        for ($i = 0; $i < 5; $i++) {
            rateLimit($key, 5, 60);
        }
        
        // 6th request should be blocked
        $this->assertFalse(rateLimit($key, 5, 60));
    }

    public function testJwtValidatorBase64UrlDecode(): void
    {
        $encoded = \JwtValidator::base64UrlEncode('test data');
        $decoded = \JwtValidator::base64UrlDecode($encoded);
        
        $this->assertSame('test data', $decoded);
    }

    public function testJwtValidatorBase64UrlDecodeHandlesInvalidInput(): void
    {
        // base64UrlDecode should return null for invalid base64
        $result = \JwtValidator::base64UrlDecode('invalid-base64!!!');
        $this->assertNull($result);
    }

    public function testJwtValidatorDecodeValidJwt(): void
    {
        // Create a minimal valid JWT (header.payload.signature)
        $header = \JwtValidator::base64UrlEncode(json_encode(['alg' => 'none', 'typ' => 'JWT']));
        $payload = \JwtValidator::base64UrlEncode(json_encode(['sub' => 'test-user', 'iss' => 'test-issuer']));
        $signature = \JwtValidator::base64UrlEncode('');
        
        $jwt = $header . '.' . $payload . '.' . $signature;
        
        $decoded = \JwtValidator::decode($jwt);
        
        $this->assertSame('test-user', $decoded['sub']);
        $this->assertSame('test-issuer', $decoded['iss']);
    }

    public function testJwtValidatorDecodeThrowsOnMalformedJwt(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        \JwtValidator::decode('not-a-valid-jwt');
    }

    public function testJwtValidatorDecodeThrowsOnInvalidPayload(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        
        $header = \JwtValidator::base64UrlEncode(json_encode(['alg' => 'none']));
        $payload = \JwtValidator::base64UrlEncode('invalid-json');
        $signature = \JwtValidator::base64UrlEncode('');
        
        \JwtValidator::decode($header . '.' . $payload . '.' . $signature);
    }

    public function testJwtValidatorDecodeThrowsOnMissingSub(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        
        $header = \JwtValidator::base64UrlEncode(json_encode(['alg' => 'none']));
        $payload = \JwtValidator::base64UrlEncode(json_encode(['other' => 'value']));
        $signature = \JwtValidator::base64UrlEncode('');
        
        \JwtValidator::decode($header . '.' . $payload . '.' . $signature);
    }

    public function testJwtValidatorVerifyClaimsValid(): void
    {
        $token = [
            'sub' => 'test-user',
            'iss' => 'https://issuer.example.com',
            'exp' => time() + 3600, // 1 hour in future
            'aud' => ['client-id'],
            'nonce' => 'test-nonce',
        ];
        
        // Should not throw
        \JwtValidator::verifyClaims($token, 'https://issuer.example.com', 'client-id', 'test-nonce');
        $this->assertTrue(true);
    }

    public function testJwtValidatorVerifyClaimsThrowsOnExpiredToken(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('expired');
        
        $token = [
            'sub' => 'test-user',
            'iss' => 'https://issuer.example.com',
            'exp' => time() - 100, // Already expired
            'aud' => ['client-id'],
            'nonce' => 'test-nonce',
        ];
        
        \JwtValidator::verifyClaims($token, 'https://issuer.example.com', 'client-id', 'test-nonce');
    }

    public function testJwtValidatorVerifyClaimsThrowsOnIssuerMismatch(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('issuer mismatch');
        
        $token = [
            'sub' => 'test-user',
            'iss' => 'https://wrong-issuer.example.com',
            'exp' => time() + 3600,
            'aud' => ['client-id'],
            'nonce' => 'test-nonce',
        ];
        
        \JwtValidator::verifyClaims($token, 'https://issuer.example.com', 'client-id', 'test-nonce');
    }

    public function testJwtValidatorVerifyClaimsThrowsOnAudienceMismatch(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('audience mismatch');
        
        $token = [
            'sub' => 'test-user',
            'iss' => 'https://issuer.example.com',
            'exp' => time() + 3600,
            'aud' => ['wrong-client-id'],
            'nonce' => 'test-nonce',
        ];
        
        \JwtValidator::verifyClaims($token, 'https://issuer.example.com', 'client-id', 'test-nonce');
    }

    public function testJwtValidatorVerifyClaimsThrowsOnNonceMismatch(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('nonce mismatch');
        
        $token = [
            'sub' => 'test-user',
            'iss' => 'https://issuer.example.com',
            'exp' => time() + 3600,
            'aud' => ['client-id'],
            'nonce' => 'wrong-nonce',
        ];
        
        \JwtValidator::verifyClaims($token, 'https://issuer.example.com', 'client-id', 'test-nonce');
    }

    public function testJwtValidatorVerifyClaimsThrowsOnMissingClaims(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        
        $token = [
            'sub' => 'test-user',
            // Missing iss, exp, aud, nonce
        ];
        
        \JwtValidator::verifyClaims($token, 'https://issuer.example.com', 'client-id', 'test-nonce');
    }

    public function testConfigValidationThrowsOnMissingApplications(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('applications');
        
        // Create a temp config file without applications
        file_put_contents(
            sys_get_temp_dir() . '/invalid-config-1.php',
            '<?php return [];'
        );
        
        new \Config(sys_get_temp_dir() . '/invalid-config-1.php');
    }

    public function testConfigValidationThrowsOnMissingEnvironments(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('environments');
        
        file_put_contents(
            sys_get_temp_dir() . '/invalid-config-2.php',
            '<?php return ["applications" => []];'
        );
        
        new \Config(sys_get_temp_dir() . '/invalid-config-2.php');
    }

    public function testConfigValidationThrowsOnMissingOidc(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('oidc');
        
        file_put_contents(
            sys_get_temp_dir() . '/invalid-config-3.php',
            '<?php return ["applications" => [], "environments" => []];'
        );
        
        new \Config(sys_get_temp_dir() . '/invalid-config-3.php');
    }

    public function testHttpClientGetSuccess(): void
    {
        // This test requires a mock HTTP server, so we'll skip it for now
        // In a real test suite, you would use a mock or test server
        $this->markTestSkipped('Requires HTTP server for integration test');
    }
}
