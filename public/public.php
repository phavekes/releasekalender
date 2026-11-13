<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

$config = new Config();
$releases = new Releases(Database::pdo(), $config);

// Get all releases
$allReleases = $releases->all();

// Separate into past and future releases based on planned production date
$today = (new DateTimeImmutable('today'))->format('Y-m-d');
$pastReleases = [];
$futureReleases = [];

foreach ($allReleases as $release) {
    // Get the planned production date from environments
    $prodPlannedDate = null;
    foreach ($release['environments'] as $env) {
        if ($config->isProduction($env['environment']) && !empty($env['planned_date'])) {
            if ($prodPlannedDate === null || $env['planned_date'] < $prodPlannedDate) {
                $prodPlannedDate = $env['planned_date'];
            }
        }
    }
    
    if ($prodPlannedDate === null) {
        // No planned date, put in future
        $futureReleases[] = $release;
    } elseif ($prodPlannedDate < $today) {
        $pastReleases[] = $release;
    } else {
        $futureReleases[] = $release;
    }
}

// Sort by planned production date
usort($pastReleases, function($a, $b) use ($config) {
    $dateA = null;
    foreach ($a['environments'] as $env) {
        if ($config->isProduction($env['environment']) && !empty($env['planned_date'])) {
            if ($dateA === null || $env['planned_date'] > $dateA) {
                $dateA = $env['planned_date'];
            }
        }
    }
    $dateB = null;
    foreach ($b['environments'] as $env) {
        if ($config->isProduction($env['environment']) && !empty($env['planned_date'])) {
            if ($dateB === null || $env['planned_date'] > $dateB) {
                $dateB = $env['planned_date'];
            }
        }
    }
    return $dateB <=> $dateA;
});

usort($futureReleases, function($a, $b) use ($config) {
    $dateA = null;
    foreach ($a['environments'] as $env) {
        if ($config->isProduction($env['environment']) && !empty($env['planned_date'])) {
            if ($dateA === null || $env['planned_date'] < $dateA) {
                $dateA = $env['planned_date'];
            }
        }
    }
    $dateB = null;
    foreach ($b['environments'] as $env) {
        if ($config->isProduction($env['environment']) && !empty($env['planned_date'])) {
            if ($dateB === null || $env['planned_date'] < $dateB) {
                $dateB = $env['planned_date'];
            }
        }
    }
    return $dateA <=> $dateB;
});

function getProdDate(array $release, Config $config): ?string {
    $date = null;
    foreach ($release['environments'] as $env) {
        if ($config->isProduction($env['environment']) && !empty($env['planned_date'])) {
            if ($date === null || $env['planned_date'] < $date) {
                $date = $env['planned_date'];
            }
        }
    }
    return $date;
}

View::header('Public Releases', 'public', true);
?>

<?php if (empty($pastReleases) && empty($futureReleases)): ?>
  <p class="muted">No releases found.</p>
<?php else: ?>
  <?php if (!empty($futureReleases)): ?>
    <section class="release-section" style="margin-bottom: 2rem;">
      <h2>Upcoming Releases</h2>
      <div class="releases-list">
        <?php foreach ($futureReleases as $release):
            $prodDate = getProdDate($release, $config);
            $appLabel = $config->applicationLabel($release['application']);
        ?>
        <article class="release-card">
          <header class="release-card-header">
            <span class="release-app"><?= e($appLabel) ?></span>
            <span class="release-version"><?= e($release['version'] ?? '') ?></span>
            <?php if ($prodDate): ?>
              <time class="release-date" datetime="<?= e($prodDate) ?>">
                <?= e((new DateTimeImmutable($prodDate))->format('d M Y')) ?>
              </time>
            <?php endif; ?>
          </header>
          <?php if (!empty($release['changelog'])): ?>
            <div class="release-changes">
              <details>
                <summary>Changes</summary>
                <div class="mono"><?= nl2br(e($release['changelog'])) ?></div>
              </details>
            </div>
          <?php endif; ?>
        </article>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <?php if (!empty($pastReleases)): ?>
    <section class="release-section">
      <h2>Past Releases</h2>
      <div class="releases-list">
        <?php foreach ($pastReleases as $release):
            $prodDate = getProdDate($release, $config);
            $appLabel = $config->applicationLabel($release['application']);
        ?>
        <article class="release-card">
          <header class="release-card-header">
            <span class="release-app"><?= e($appLabel) ?></span>
            <span class="release-version"><?= e($release['version'] ?? '') ?></span>
            <?php if ($prodDate): ?>
              <time class="release-date" datetime="<?= e($prodDate) ?>">
                <?= e((new DateTimeImmutable($prodDate))->format('d M Y')) ?>
              </time>
            <?php endif; ?>
          </header>
          <?php if (!empty($release['changelog'])): ?>
            <div class="release-changes">
              <details>
                <summary>Changes</summary>
                <div class="mono"><?= nl2br(e($release['changelog'])) ?></div>
              </details>
            </div>
          <?php endif; ?>
        </article>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>
<?php endif; ?>

<?php
View::footer();
