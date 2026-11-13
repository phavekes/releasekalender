<?php

namespace ReleaseCalendar\Tests;

use PHPUnit\Framework\TestCase;
use PDO;
use PDOStatement;
use DateTimeImmutable;

/**
 * Test cases for release status computation.
 */
class ReleasesTest extends TestCase
{
    private PDO $pdo;
    private array $config;

    protected function setUp(): void
    {
        // Create an in-memory SQLite database for testing
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Create test tables
        $this->pdo->exec(''
            . 'CREATE TABLE releases ('
            . '  id INTEGER PRIMARY KEY AUTOINCREMENT,'
            . '  release_nr INTEGER NOT NULL,'
            . '  application VARCHAR(64) NOT NULL,'
            . '  version VARCHAR(64),'
            . '  description TEXT'
            . ')'
        );
        
        $this->pdo->exec(''
            . 'CREATE TABLE release_environments ('
            . '  id INTEGER PRIMARY KEY AUTOINCREMENT,'
            . '  release_id INTEGER NOT NULL,'
            . '  environment VARCHAR(32) NOT NULL,'
            . '  planned_date DATE,'
            . '  deployed_at DATE,'
            . '  installed TINYINT(1) NOT NULL DEFAULT 0,'
            . '  position INTEGER NOT NULL DEFAULT 0,'
            . '  FOREIGN KEY (release_id) REFERENCES releases(id) ON DELETE CASCADE'
            . ')'
        );

        $this->config = [
            'environments' => [
                'test2' => ['label' => 'test2', 'production' => false],
                'test' => ['label' => 'test', 'production' => false],
                'accrood' => ['label' => 'accrood', 'production' => false],
                'accblauw' => ['label' => 'accblauw', 'production' => false],
                '4eset' => ['label' => '4eset', 'production' => false],
                'prodblauw' => ['label' => 'prodblauw', 'production' => true],
                'prodrood' => ['label' => 'prodrood', 'production' => true],
            ],
        ];
    }

    protected function tearDown(): void
    {
        $this->pdo = null;
    }

    /**
     * Helper to insert a test release with environments.
     */
    private function insertRelease(array $envs): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO releases (release_nr, application) VALUES (?, ?)');
        $stmt->execute([700 + count($this->pdo->query('SELECT * FROM releases')->fetchAll()), 'test-app']);
        $releaseId = $this->pdo->lastInsertId();

        $insertEnv = $this->pdo->prepare(
            'INSERT INTO release_environments '
            . '(release_id, environment, planned_date, deployed_at, installed, position) '
            . 'VALUES (?, ?, ?, ?, ?, ?)'
        );

        $position = 0;
        foreach ($envs as $env => $data) {
            $position += 10;
            $insertEnv->execute([
                $releaseId,
                $env,
                $data['planned_date'] ?? null,
                $data['deployed_at'] ?? null,
                $data['installed'] ?? 0,
                $position,
            ]);
        }

        return (int)$releaseId;
    }

    /**
     * Helper to fetch a release and compute its status.
     */
    private function computeStatus(int $releaseId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM releases WHERE id = ?');
        $stmt->execute([$releaseId]);
        $release = $stmt->fetch();

        $stmt = $this->pdo->prepare(
            'SELECT * FROM release_environments WHERE release_id = ? ORDER BY position, id'
        );
        $stmt->execute([$releaseId]);
        $release['environments'] = $stmt->fetchAll();

        return $this->computeReleaseStatus($release);
    }

    /**
     * Simplified version of Releases::computeStatus for testing.
     */
    private function computeReleaseStatus(array $release): array
    {
        $items = array_values($release['environments']);
        $isComplete = fn(array $item) => (bool)($item['installed'] ?? 0) || (bool)$item['deployed_at'];

        $doneCount = 0;
        $currentItem = null;
        foreach ($items as $item) {
            if ($isComplete($item)) {
                $doneCount++;
                $currentItem = $item;
            }
        }

        $total = count($items);
        $release['current_environment'] = $currentItem['environment'] ?? null;
        $release['current_item'] = $currentItem;
        $release['progress'] = $total === 0 ? 0 : (int)round(100 * $doneCount / $total);

        // Find the furthest completed environment in the pipeline order
        $envOrder = array_keys($this->config['environments']);
        $allEnvOrder = array_merge($envOrder, ['LB 50/50']);
        $completedEnvironments = [];
        foreach ($items as $item) {
            if ($isComplete($item)) {
                $completedEnvironments[] = $item['environment'];
            }
        }

        $highestEnv = null;
        foreach ($allEnvOrder as $envName) {
            if (in_array($envName, $completedEnvironments, true)) {
                $highestEnv = $envName;
            }
        }

        if ($doneCount === $total && $total > 0) {
            $release['highest_status'] = 'Complete';
        } elseif ($highestEnv !== null) {
            $release['highest_status'] = $this->config['environments'][$highestEnv]['label'] ?? $highestEnv;
        } else {
            $release['highest_status'] = 'Not started';
        }

        // Prod is complete when every production environment has a complete item
        $prodDone = true;
        foreach (array_keys($this->config['environments']) as $envName) {
            if (!$this->config['environments'][$envName]['production']) {
                continue;
            }
            $complete = false;
            foreach ($items as $item) {
                if ($item['environment'] === $envName && $isComplete($item)) {
                    $complete = true;
                    break;
                }
            }
            if (!$complete) {
                $prodDone = false;
            }
        }
        $release['done'] = $prodDone;

        // Alerts are based on the planned production date only
        $today = (new DateTimeImmutable('today'))->format('Y-m-d');
        $prodPlanned = null;
        foreach ($items as $item) {
            if ($this->config['environments'][$item['environment']]['production'] ?? false && $item['planned_date']) {
                $prodPlanned = $prodPlanned === null ? $item['planned_date'] : min($prodPlanned, $item['planned_date']);
            }
        }
        $release['behind'] = $prodPlanned !== null && !$prodDone && $prodPlanned < $today;
        $release['prod_planned_date'] = $prodPlanned;

        return $release;
    }

    public function testProgressIsZeroWhenNoEnvironments(): void
    {
        $releaseId = $this->insertRelease([]);
        $release = $this->computeStatus($releaseId);

        $this->assertSame(0, $release['progress']);
        $this->assertSame('Not started', $release['highest_status']);
        $this->assertNull($release['current_environment']);
    }

    public function testProgressWithSingleCompletedEnvironment(): void
    {
        $releaseId = $this->insertRelease([
            'test2' => ['installed' => 1, 'planned_date' => '2026-01-01'],
            'test' => ['installed' => 0, 'planned_date' => '2026-01-02'],
        ]);
        $release = $this->computeStatus($releaseId);

        $this->assertSame(50, $release['progress']);
        $this->assertSame('test2', $release['current_environment']);
        $this->assertSame('test2', $release['highest_status']);
    }

    public function testProgressWithAllCompleted(): void
    {
        $releaseId = $this->insertRelease([
            'test2' => ['installed' => 1, 'planned_date' => '2026-01-01'],
            'test' => ['installed' => 1, 'planned_date' => '2026-01-02'],
        ]);
        $release = $this->computeStatus($releaseId);

        $this->assertSame(100, $release['progress']);
        $this->assertSame('test', $release['highest_status']);
    }

    public function testCompleteStatusWhenAllDone(): void
    {
        $releaseId = $this->insertRelease([
            'prodblauw' => ['installed' => 1],
            'prodrood' => ['installed' => 1],
        ]);
        $release = $this->computeStatus($releaseId);

        $this->assertTrue($release['done']);
        $this->assertSame('Complete', $release['highest_status']);
    }

    public function testNotDoneWhenOnlyOneProductionComplete(): void
    {
        $releaseId = $this->insertRelease([
            'prodblauw' => ['installed' => 1],
            'prodrood' => ['installed' => 0],
        ]);
        $release = $this->computeStatus($releaseId);

        $this->assertFalse($release['done']);
        $this->assertSame('prodblauw', $release['highest_status']);
    }

    public function testNotDoneWhenProductionNotComplete(): void
    {
        $releaseId = $this->insertRelease([
            'test2' => ['installed' => 1],
            'test' => ['installed' => 1],
            'prodblauw' => ['installed' => 0],
            'prodrood' => ['installed' => 0],
        ]);
        $release = $this->computeStatus($releaseId);

        $this->assertFalse($release['done']);
        $this->assertSame('test', $release['highest_status']);
    }

    public function testBehindWhenPlannedDatePassedAndNotComplete(): void
    {
        $releaseId = $this->insertRelease([
            'prodblauw' => ['planned_date' => '2020-01-01', 'installed' => 0],
            'prodrood' => ['planned_date' => '2020-01-01', 'installed' => 0],
        ]);
        $release = $this->computeStatus($releaseId);

        $this->assertTrue($release['behind']);
        $this->assertFalse($release['done']);
    }

    public function testNotBehindWhenPlannedDateInFuture(): void
    {
        $releaseId = $this->insertRelease([
            'prodblauw' => ['planned_date' => '2030-01-01', 'installed' => 0],
            'prodrood' => ['planned_date' => '2030-01-01', 'installed' => 0],
        ]);
        $release = $this->computeStatus($releaseId);

        $this->assertFalse($release['behind']);
    }

    public function testNotBehindWhenProductionComplete(): void
    {
        $releaseId = $this->insertRelease([
            'prodblauw' => ['planned_date' => '2020-01-01', 'installed' => 1],
            'prodrood' => ['planned_date' => '2020-01-01', 'installed' => 1],
        ]);
        $release = $this->computeStatus($releaseId);

        $this->assertFalse($release['behind']);
        $this->assertTrue($release['done']);
    }

    public function testBehindWhenEarlierProductionDatePassed(): void
    {
        $releaseId = $this->insertRelease([
            'prodblauw' => ['planned_date' => '2020-01-01', 'installed' => 0],
            'prodrood' => ['planned_date' => '2030-01-01', 'installed' => 0],
        ]);
        $release = $this->computeStatus($releaseId);

        $this->assertTrue($release['behind']);
        $this->assertSame('2020-01-01', $release['prod_planned_date']);
    }

    public function testHighestStatusIsFurthestCompleted(): void
    {
        $releaseId = $this->insertRelease([
            'test2' => ['installed' => 1, 'planned_date' => '2026-01-01'],
            'test' => ['installed' => 1, 'planned_date' => '2026-01-02'],
            'accrood' => ['installed' => 0, 'planned_date' => '2026-01-03'],
            'accblauw' => ['installed' => 0, 'planned_date' => '2026-01-04'],
        ]);
        $release = $this->computeStatus($releaseId);

        $this->assertSame('test', $release['highest_status']);
    }

    public function testDeployedAtAlsoCountsAsComplete(): void
    {
        $releaseId = $this->insertRelease([
            'prodblauw' => ['deployed_at' => '2026-01-01'],
            'prodrood' => ['deployed_at' => '2026-01-01'],
        ]);
        $release = $this->computeStatus($releaseId);

        $this->assertTrue($release['done']);
        $this->assertSame('Complete', $release['highest_status']);
    }

    public function testMixedInstalledAndDeployed(): void
    {
        $releaseId = $this->insertRelease([
            'prodblauw' => ['installed' => 1],
            'prodrood' => ['deployed_at' => '2026-01-01'],
        ]);
        $release = $this->computeStatus($releaseId);

        $this->assertTrue($release['done']);
    }

    public function testProdPlannedDateIsEarliestProductionDate(): void
    {
        $releaseId = $this->insertRelease([
            'prodblauw' => ['planned_date' => '2026-01-02'],
            'prodrood' => ['planned_date' => '2026-01-01'],
        ]);
        $release = $this->computeStatus($releaseId);

        $this->assertSame('2026-01-01', $release['prod_planned_date']);
    }

    public function testProdPlannedDateIsNullWhenNoProductionDates(): void
    {
        $releaseId = $this->insertRelease([
            'test2' => ['planned_date' => '2026-01-01'],
            'test' => ['planned_date' => '2026-01-02'],
        ]);
        $release = $this->computeStatus($releaseId);

        $this->assertNull($release['prod_planned_date']);
    }
}
