<?php

declare(strict_types=1);

/**
 * Repository for releases and their per-environment status.
 */
class Releases
{
    private PDO $pdo;
    private Config $config;

    public function __construct(PDO $pdo, Config $config)
    {
        $this->pdo = $pdo;
        $this->config = $config;
    }

    public function all(): array
    {
        // Use a single query with JOIN for better performance
        $rows = $this->pdo->query(
            'SELECT r.*, re.* '
            . 'FROM releases r '
            . 'LEFT JOIN release_environments re ON re.release_id = r.id '
            . 'ORDER BY r.release_nr DESC, re.position, re.id'
        )->fetchAll();

        $releases = [];
        $currentReleaseId = null;
        $currentRelease = null;

        foreach ($rows as $row) {
            if ($row['id'] !== $currentReleaseId) {
                // New release
                if ($currentRelease !== null) {
                    $releases[] = $this->computeStatus($currentRelease);
                }
                $currentReleaseId = $row['id'];
                $currentRelease = [
                    'id' => $row['id'],
                    'release_nr' => $row['release_nr'],
                    'application' => $row['application'],
                    'version' => $row['version'],
                    'previous_version' => $row['previous_version'],
                    'description' => $row['description'],
                    'deploy_tag' => $row['deploy_tag'],
                    'environments_tag' => $row['environments_tag'],
                    'dependencies' => $row['dependencies'],
                    'db_changes' => $row['db_changes'],
                    'testers' => $row['testers'],
                    'responsible' => $row['responsible'],
                    'changelog' => $row['changelog'],
                    'notes' => $row['notes'],
                    'rollback' => $row['rollback'],
                    'created_at' => $row['created_at'],
                    'created_by' => $row['created_by'],
                    'environments' => [],
                ];
            }

            if ($row['release_id'] !== null) {
                $currentRelease['environments'][] = [
                    'id' => $row['id'],
                    'release_id' => $row['release_id'],
                    'environment' => $row['environment'],
                    'planned_date' => $row['planned_date'],
                    'deployed_at' => $row['deployed_at'],
                    'installed' => $row['installed'],
                    'position' => $row['position'],
                ];
            }
        }

        if ($currentRelease !== null) {
            $releases[] = $this->computeStatus($currentRelease);
        }

        return $releases;
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM releases WHERE id = ?'
        );
        $stmt->execute([$id]);
        $release = $stmt->fetch();
        if (!$release) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT * FROM release_environments WHERE release_id = ? ORDER BY position, id'
        );
        $stmt->execute([$id]);

        $release['environments'] = $stmt->fetchAll();
        return $this->computeStatus($release);
    }

    /**
     * Status flags used by the overview. The planned-date items are stored per
     * release (free-form names, ordered). Progress matches the ticked
     * "installed" checkboxes; prod (and the release) is complete when both
     * prodblauw and prodrood are complete.
     */
    private function computeStatus(array $release): array
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

        // Highest status is the furthest completed environment in the pipeline order
        // Build the full pipeline order: environments from config + LB 50/50
        $envOrder = array_keys($this->config->environments());
        $allEnvOrder = array_merge($envOrder, [Constants::ENV_LB_50_50]);
        $completedEnvironments = [];
        foreach ($items as $item) {
            if ($isComplete($item)) {
                $completedEnvironments[] = $item['environment'];
            }
        }

        // Find the furthest environment in the pipeline that's completed
        $highestEnv = null;
        foreach ($allEnvOrder as $envName) {
            if (in_array($envName, $completedEnvironments, true)) {
                $highestEnv = $envName;
            }
        }

        if ($doneCount === $total && $total > 0) {
            $release['highest_status'] = Constants::STATUS_COMPLETE;
        } elseif ($highestEnv !== null) {
            $release['highest_status'] = $this->config->environmentLabel($highestEnv);
        } else {
            $release['highest_status'] = Constants::STATUS_NOT_STARTED;
        }

        // Prod is complete when every production environment (prodblauw and
        // prodrood) has a complete item; the release is then complete.
        $prodDone = true;
        foreach (array_keys($this->config->environments()) as $envName) {
            if (!$this->config->isProduction($envName)) {
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

        // Alerts are based on the planned production date only. A release is
        // behind when the earliest planned production date has passed while
        // prod is not complete.
        $today = (new DateTimeImmutable('today'))->format('Y-m-d');
        $prodPlanned = null;
        foreach ($items as $item) {
            if ($this->config->isProduction($item['environment']) && $item['planned_date']) {
                $prodPlanned = $prodPlanned === null ? $item['planned_date'] : min($prodPlanned, $item['planned_date']);
            }
        }
        $release['behind'] = $prodPlanned !== null && !$prodDone && $prodPlanned < $today;
        $release['prod_planned_date'] = $prodPlanned;

        return $release;
    }

    /**
     * Release IDs of releases that share a planned PROD date with another release.
     */
    public function prodDateOverlaps(array $releases): array
    {
        $byDate = [];
        foreach ($releases as $release) {
            if ($release['prod_planned_date'] !== null) {
                $byDate[$release['prod_planned_date']][] = $release['id'];
            }
        }
        $overlaps = [];
        foreach ($byDate as $ids) {
            if (count($ids) > 1) {
                foreach ($ids as $id) {
                    $overlaps[$id] = true;
                }
            }
        }
        return $overlaps;
    }

    public function nextReleaseNr(): int
    {
        return (int)$this->pdo->query('SELECT COALESCE(MAX(release_nr), ' . Constants::DEFAULT_RELEASE_NR . ') + 1 FROM releases')->fetchColumn();
    }

    /**
     * Versions already present in the calendar for an application,
     * used to filter those out of the GitHub release list.
     */
    public function existingVersions(string $application): array
    {
        $stmt = $this->pdo->prepare('SELECT version FROM releases WHERE application = ?');
        $stmt->execute([$application]);
        $versions = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $version) {
            $versions[] = $version;
            $versions[] = ltrim($version, 'v');
        }
        return array_unique($versions);
    }

    /**
     * Save a release with its environments.
     *
     * @param array $data Release data
     * @param array $items Environment items
     * @param int|null $id Release ID (null for new release)
     * @return int The release ID
     */
    public function save(array $data, array $items, ?int $id = null): int
    {
        $fields = [
            'release_nr', 'application', 'version', 'previous_version', 'description',
            'deploy_tag', 'environments_tag', 'dependencies', 'db_changes', 'testers',
            'responsible', 'changelog', 'rollback',
        ];
        $created = Auth::user()['name'] ?? null;

        $this->pdo->beginTransaction();
        try {
            if ($id === null) {
                $columns = implode(', ', $fields);
                $placeholders = implode(', ', array_fill(0, count($fields), '?'));
                $stmt = $this->pdo->prepare(
                    "INSERT INTO releases ($columns, created_by) VALUES ($placeholders, ?)"
                );
                $values = $this->nullEmpty($fields, $data);
                $stmt->execute([...$values, $created]);
                $id = (int)$this->pdo->lastInsertId();
            } else {
                $assignments = implode(', ', array_map(fn($f) => "$f = ?", $fields));
                $stmt = $this->pdo->prepare("UPDATE releases SET $assignments WHERE id = ?");
                $stmt->execute([...$this->nullEmpty($fields, $data), $id]);
            }

            $this->saveItems($id, $items);

            $this->pdo->commit();
            return $id;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            error_log("Failed to save release: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Convert empty strings to null for the given fields.
     */
    private function nullEmpty(array $fields, array $data): array
    {
        return array_map(
            function ($f) use ($data) {
                $value = trim((string)($data[$f] ?? ''));
                return $value === '' ? null : $value;
            },
            $fields
        );
    }

    /**
     * Save environment items for a release.
     *
     * @param int $releaseId Release ID
     * @param array $items Environment items
     */
    private function saveItems(int $releaseId, array $items): void
    {
        $delete = $this->pdo->prepare('DELETE FROM release_environments WHERE release_id = ?');
        $delete->execute([$releaseId]);

        $insert = $this->pdo->prepare(
            'INSERT INTO release_environments
                (release_id, environment, planned_date, deployed_at, installed, position)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $position = 0;
        foreach ($items as $data) {
            if (!is_array($data)) {
                continue;
            }
            $name = trim((string)($data['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $position += 10;
            $planned = trim((string)($data['planned_date'] ?? '')) ?: null;
            $deployed = trim((string)($data['deployed_at'] ?? '')) ?: null;
            // Checkboxes are only submitted when ticked.
            $installed = !empty($data['installed']) ? 1 : 0;
            $insert->execute([$releaseId, $name, $planned, $deployed, $installed, $position]);
        }
    }

    public function findByReleaseNr(int $nr): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id FROM releases WHERE release_nr = ?');
        $stmt->execute([$nr]);
        $id = $stmt->fetchColumn();
        return $id === false ? null : $this->find((int)$id);
    }

    /**
     * Delete a release.
     *
     * @param int $id Release ID
     */
    public function delete(int $id): void
    {
        try {
            $stmt = $this->pdo->prepare('DELETE FROM releases WHERE id = ?');
            $stmt->execute([$id]);
        } catch (PDOException $e) {
            error_log("Failed to delete release $id: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get releases for a specific application.
     *
     * @param string $application Application key
     * @return array Releases
     */
    public function byApplication(string $application): array
    {
        return array_filter($this->all(), fn($r) => $r['application'] === $application);
    }

    /**
     * Get releases for a specific responsible person.
     *
     * @param string $sub User sub
     * @return array Releases
     */
    public function byResponsible(string $sub): array
    {
        return array_filter($this->all(), fn($r) => ($r['responsible'] ?? '') === $sub);
    }

    /**
     * Get releases that are behind schedule.
     *
     * @return array Releases
     */
    public function behindSchedule(): array
    {
        return array_filter($this->all(), fn($r) => $r['behind']);
    }

    /**
     * Get releases with overlapping production dates.
     *
     * @return array Releases
     */
    public function overlapping(): array
    {
        $all = $this->all();
        $overlaps = $this->prodDateOverlaps($all);
        return array_filter($all, fn($r) => isset($overlaps[$r['id']]));
    }

    /**
     * Get open (not complete) releases.
     *
     * @return array Releases
     */
    public function open(): array
    {
        return array_filter($this->all(), fn($r) => !$r['done']);
    }
}
