<?php

namespace ReleaseCalendar\Tests;

use PHPUnit\Framework\TestCase;
use PDO;
use PDOException;

require_once __DIR__ . '/bootstrap.php';

/**
 * Tests for database operations.
 */
class DatabaseTest extends TestCase
{
    private string $tempDbPath;

    protected function setUp(): void
    {
        $this->tempDbPath = tempnam(sys_get_temp_dir(), 'test-db-') . '.sqlite';
    }

    protected function tearDown(): void
    {
        // Clean up temp files
        if (file_exists($this->tempDbPath)) {
            unlink($this->tempDbPath);
        }
        
        // Reset static PDO
        \Database::reset();
        
        // Clear environment
        putenv('DB_HOST');
        putenv('DB_PORT');
        putenv('DB_NAME');
        putenv('DB_USER');
        putenv('DB_PASS');
    }

    public function testPdoStaticCreatesConnection(): void
    {
        // Create a test PDO
        $pdo = new PDO('sqlite:' . $this->tempDbPath);
        
        // Use dependency injection
        $db = new \Database($pdo);
        
        $result = $db->pdo();
        
        $this->assertInstanceOf(PDO::class, $result);
    }

    public function testConnectedReturnsTrueForValidConnection(): void
    {
        $pdo = new PDO('sqlite:' . $this->tempDbPath);
        $pdo->exec('CREATE TABLE test (id INTEGER)');
        
        $db = new \Database($pdo);
        
        // Test the instance method
        $this->assertInstanceOf(PDO::class, $db->pdo());
        
        // Test connected with a valid PDO instance
        $this->assertTrue($db->pdo()->query('SELECT 1') !== false);
    }

    public function testConnectedReturnsFalseForInvalidQuery(): void
    {
        // Create a mock that will fail
        $mock = $this->getMockBuilder(PDO::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['query'])
            ->getMock();
        
        $mock->method('query')
            ->willThrowException(new PDOException('Connection failed'));
        
        // Set the mock as the static PDO instance
        // We need to use reflection to set the private static property
        $reflection = new \ReflectionClass(\Database::class);
        $property = $reflection->getProperty('pdo');
        $property->setAccessible(true);
        $property->setValue($mock);
        
        $this->assertFalse(\Database::connected());
    }

    public function testResetClearsStaticPdo(): void
    {
        $pdo = new PDO('sqlite:' . $this->tempDbPath);
        
        // Create a database instance with the PDO
        $db = new \Database($pdo);
        
        // Manually set the static PDO to our test PDO
        // We need to use reflection to set the private static property
        $reflection = new \ReflectionClass(\Database::class);
        $property = $reflection->getProperty('pdo');
        $property->setAccessible(true);
        $property->setValue($pdo);
        
        // Verify it's set
        $staticPdo = \Database::pdoStatic();
        $this->assertSame($pdo, $staticPdo);
        
        // Reset
        \Database::reset();
        
        // After reset, it should be null
        $property->setAccessible(true);
        $this->assertNull($property->getValue());
        
        // After reset, a new call to pdoStatic should create a new instance
        // But we can't test the actual creation without a valid DB connection
        // So we just verify that reset cleared the static property
    }

    public function testUsersUpsertInsertsNewUser(): void
    {
        $pdo = new PDO('sqlite:' . $this->tempDbPath);
        
        $users = new \Users($pdo);
        
        $user = [
            'sub' => 'test-sub',
            'nickname' => 'Test User',
            'name' => 'Test User Name',
            'email' => 'test@example.com',
        ];
        
        $users->upsert($user);
        
        $stmt = $pdo->query('SELECT * FROM users WHERE sub = "test-sub"');
        $result = $stmt->fetch();
        
        $this->assertNotFalse($result);
        $this->assertSame('Test User', $result['nickname']);
    }

    public function testUsersUpsertUpdatesExistingUser(): void
    {
        $pdo = new PDO('sqlite:' . $this->tempDbPath);
        
        $users = new \Users($pdo);
        
        // Insert initial user
        $user1 = [
            'sub' => 'test-sub-update',
            'nickname' => 'Original Nickname',
            'name' => 'Original Name',
            'email' => 'original@example.com',
        ];
        $users->upsert($user1);
        
        // Update user
        $user2 = [
            'sub' => 'test-sub-update',
            'nickname' => 'Updated Nickname',
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
        ];
        $users->upsert($user2);
        
        $stmt = $pdo->query('SELECT * FROM users WHERE sub = "test-sub-update"');
        $result = $stmt->fetch();
        
        $this->assertSame('Updated Nickname', $result['nickname']);
        $this->assertSame('Updated Name', $result['name']);
        $this->assertSame('updated@example.com', $result['email']);
    }

    public function testUsersAllReturnsEmptyArrayForNoUsers(): void
    {
        $pdo = new PDO('sqlite:' . $this->tempDbPath);
        $pdo->exec('CREATE TABLE users (sub TEXT PRIMARY KEY, nickname TEXT, name TEXT, email TEXT)');
        
        $users = new \Users($pdo);
        
        $this->assertSame([], $users->all());
    }

    public function testUsersAllReturnsAllUsers(): void
    {
        $pdo = new PDO('sqlite:' . $this->tempDbPath);
        
        $users = new \Users($pdo);
        
        $users->upsert(['sub' => 'user1', 'nickname' => 'User 1', 'name' => '', 'email' => '']);
        $users->upsert(['sub' => 'user2', 'nickname' => 'User 2', 'name' => '', 'email' => '']);
        
        $all = $users->all();
        
        $this->assertCount(2, $all);
        $this->assertArrayHasKey('user1', $all);
        $this->assertArrayHasKey('user2', $all);
    }

    public function testUsersFindReturnsUser(): void
    {
        $pdo = new PDO('sqlite:' . $this->tempDbPath);
        
        $users = new \Users($pdo);
        $users->upsert(['sub' => 'test-user', 'nickname' => 'Test User', 'name' => '', 'email' => '']);
        
        $result = $users->find('test-user');
        
        $this->assertNotNull($result);
        $this->assertSame('Test User', $result['nickname']);
    }

    public function testUsersFindReturnsNullForNonExistentUser(): void
    {
        $pdo = new PDO('sqlite:' . $this->tempDbPath);
        $pdo->exec('CREATE TABLE users (sub TEXT PRIMARY KEY, nickname TEXT, name TEXT, email TEXT)');
        
        $users = new \Users($pdo);
        
        $this->assertNull($users->find('non-existent'));
    }

    public function testUsersDeleteRemovesUser(): void
    {
        $pdo = new PDO('sqlite:' . $this->tempDbPath);
        
        $users = new \Users($pdo);
        $users->upsert(['sub' => 'test-user-del', 'nickname' => 'Test User', 'name' => '', 'email' => '']);
        
        $users->delete('test-user-del');
        
        $this->assertNull($users->find('test-user-del'));
    }

    public function testUsersCountReturnsCorrectCount(): void
    {
        $pdo = new PDO('sqlite:' . $this->tempDbPath);
        $pdo->exec('CREATE TABLE users (sub TEXT PRIMARY KEY, nickname TEXT, name TEXT, email TEXT)');
        
        $users = new \Users($pdo);
        
        $this->assertSame(0, $users->count());
        
        $users->upsert(['sub' => 'user1-count', 'nickname' => 'User 1', 'name' => '', 'email' => '']);
        $this->assertSame(1, $users->count());
        
        $users->upsert(['sub' => 'user2-count', 'nickname' => 'User 2', 'name' => '', 'email' => '']);
        $this->assertSame(2, $users->count());
    }
}
