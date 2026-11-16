<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

$config = new Config();
$releases = new Releases(Database::pdoStatic(), $config);

// Get all releases
$allReleases = $releases->all();

// Separate into past and future releases based on planned production date
$today = (new DateTimeImmutable('today'))->format('Y-m-d');
$pastReleases = [];
$futureReleases = [];

foreach ($allReleases as $release) {
    // Get the planned prodblauw date
    $prodblauwDate = null;
    foreach ($release['environments'] as $env) {
        if ($env['environment'] === 'prodblauw' && !empty($env['planned_date'])) {
            $prodblauwDate = $env['planned_date'];
            break;
        }
    }
    
    // Only show releases with a planned prodblauw date
    if ($prodblauwDate === null) {
        continue;
    }
    
    if ($prodblauwDate < $today) {
        $pastReleases[] = $release;
    } else {
        $futureReleases[] = $release;
    }
}

// Sort by prodblauw planned date
usort($pastReleases, function($a, $b) {
    $dateA = getProdblauwDate($a);
    $dateB = getProdblauwDate($b);
    return $dateB <=> $dateA;
});

usort($futureReleases, function($a, $b) {
    $dateA = getProdblauwDate($a);
    $dateB = getProdblauwDate($b);
    return $dateA <=> $dateB;
});

function getProdblauwDate(array $release): ?string {
    foreach ($release['environments'] as $env) {
        if ($env['environment'] === 'prodblauw' && !empty($env['planned_date'])) {
            return $env['planned_date'];
        }
    }
    return null;
}

View::header('Public Releases', 'public', true, $config);
?>

<?php if (empty($pastReleases) && empty($futureReleases)): ?>
  <p class="muted">No releases found.</p>
<?php else: ?>
  <?php if (!empty($futureReleases)): ?>
    <section class="release-section" style="margin-bottom: 2rem;">
      <h2>Upcoming Releases</h2>
      <div class="releases-list">
        <?php foreach ($futureReleases as $release):
            $prodDate = getProdblauwDate($release);
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
            $prodDate = getProdblauwDate($release);
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
