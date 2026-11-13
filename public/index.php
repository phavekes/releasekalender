<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

$config = new Config();
if (!Auth::user()) {
    header('Location: public.php');
    exit;
}
$releases = new Releases(Database::pdo(), $config);
$users = (new Users(Database::pdo()))->all();

$all = $releases->all();
$overlaps = $releases->prodDateOverlaps($all);

$filter = $_GET['filter'] ?? 'all';
$responsibleFilter = $_GET['responsible'] ?? '';
$appFilter = $_GET['app'] ?? '';
$currentSub = Auth::user()['sub'] ?? '';

$rows = array_values(array_filter($all, function ($r) use ($filter, $responsibleFilter, $appFilter, $currentSub) {
    if ($filter === 'behind' && !$r['behind']) {
        return false;
    }
    if ($filter === 'overlap' && !isset($overlaps[$r['id']])) {
        return false;
    }
    if ($filter === 'open' && $r['done']) {
        return false;
    }
    if ($responsibleFilter !== '') {
        $wanted = $responsibleFilter === 'mine' ? $currentSub : $responsibleFilter;
        if (($r['responsible'] ?? '') !== $wanted) {
            return false;
        }
    }
    if ($appFilter !== '' && $r['application'] !== $appFilter) {
        return false;
    }
    return true;
}));

function filterLink(string $label, string $value, string $current, array $params): string
{
    $query = array_merge($params, ['filter' => $value]);
    $class = 'chip' . ($current === $value ? ' chip-active' : '');
    return '<a class="' . $class . '" href="index.php?' . e(http_build_query($query)) . '">' . e($label) . '</a>';
}

View::header('Overview', 'overview');
?>
<div class="page-head">
  <h1>Releases</h1>
  <a class="btn btn-primary" href="new.php">New release</a>
</div>

<form method="get" class="filters-form" action="index.php">
  <input type="hidden" name="filter" value="<?= e($filter) ?>">
  <div class="filters">
    <?= filterLink('All (' . count($all) . ')', 'all', $filter,
        array_filter(['responsible' => $responsibleFilter, 'app' => $appFilter])) ?>
    <?= filterLink('Behind schedule', 'behind', $filter,
        array_filter(['responsible' => $responsibleFilter, 'app' => $appFilter])) ?>
    <?= filterLink('PROD overlap', 'overlap', $filter,
        array_filter(['responsible' => $responsibleFilter, 'app' => $appFilter])) ?>
    <?= filterLink('Open', 'open', $filter,
        array_filter(['responsible' => $responsibleFilter, 'app' => $appFilter])) ?>
  </div>

  <div class="filters">
    <select name="responsible" onchange="this.form.submit()" aria-label="Filter by responsible">
      <option value="">All responsibles</option>
      <option value="mine" <?= $responsibleFilter === 'mine' ? 'selected' : '' ?>>Mine</option>
      <?php foreach ($users as $sub => $user): ?>
        <option value="<?= e($sub) ?>" <?= $responsibleFilter === $sub ? 'selected' : '' ?>>
          <?= e($user['nickname']) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <select name="app" onchange="this.form.submit()" aria-label="Filter by application">
      <option value="">All applications</option>
      <?php foreach ($config->applications() as $key => $app): ?>
        <option value="<?= e($key) ?>" <?= $appFilter === $key ? 'selected' : '' ?>>
          <?= e($app['label']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
</form>

<table class="releases-table">
  <thead>
    <tr>
      <th>Release</th>
      <th>Application</th>
      <th>Description</th>
      <th>Planned prodrood</th>
      <th>Status</th>
      <th>Progress</th>
      <th>Responsible</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($rows as $r):
      $rowClass = '';
      if ($r['behind']) {
          $rowClass = 'row-behind';
      }
      if (isset($overlaps[$r['id']])) {
          $rowClass .= ' row-overlap';
      }
      
      // Get planned prodrood date
      $prodroodDate = null;
      foreach ($r['environments'] as $env) {
          if ($env['environment'] === 'prodrood' && !empty($env['planned_date'])) {
              $prodroodDate = $env['planned_date'];
              break;
          }
      }
  ?>
    <tr class="<?= trim($rowClass) ?>">
      <td class="nowrap">
        <a class="release-nr" href="release.php?id=<?= (int)$r['id'] ?>">Release <?= (int)$r['release_nr'] ?></a>
        <?php if (isset($overlaps[$r['id']]) || ($r['behind'] && !$r['done'])): ?>
          <div class="badge-row">
            <?php if ($r['behind'] && !$r['done']): ?>
              <span class="badge badge-behind" title="Planned PROD date has passed without a PROD deployment">behind</span>
            <?php endif; ?>
            <?php if (isset($overlaps[$r['id']])): ?>
              <span class="badge badge-overlap" title="Planned PROD date is the same as another release">overlap</span>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </td>
      <td>
        <span class="app-name"><?= e($config->applicationLabel($r['application'])) ?></span>
        <span class="app-version"><?= e($r['version']) ?></span>
      </td>
      <td class="desc"><?= e($r['description']) ?></td>
      <td class="nowrap">
        <?= e(View::formatDate($prodroodDate)) ?>
      </td>
      <td>
        <?php 
          $status = $r['highest_status'] ?? ($r['current_environment'] ? $config->environmentLabel($r['current_environment']) : 'Not started');
          if ($r['done']) {
              $status = 'Complete';
          }
        ?>
        <span class="badge badge-env badge-current"><?= e($status) ?></span>
        <?php if (($r['current_item']['deployed_at'] ?? null) || ($r['current_item']['planned_date'] ?? null)): ?>
          <span class="muted small"><?= e(View::formatDate($r['current_item']['deployed_at'] ?? $r['current_item']['planned_date'] ?? null)) ?></span>
        <?php endif; ?>
      </td>
      <td>
        <?php if ($r['done']): ?>
          <span class="badge badge-done">done</span>
        <?php else: ?>
          <div class="progress" title="<?= (int)$r['progress'] ?>%">
            <div class="progress-bar<?= $r['behind'] ? ' progress-behind' : '' ?>" style="width: <?= (int)$r['progress'] ?>%"></div>
          </div>
          <span class="small muted"><?= (int)$r['progress'] ?>%</span>
        <?php endif; ?>
      </td>
      <td class="nowrap">
        <?php $responsibleSub = $r['responsible'] ?? ''; ?>
        <?php if ($responsibleSub === ''): ?>
          <span class="muted">-</span>
        <?php elseif ($responsibleSub === $currentSub): ?>
          <span class="responsible-mine"><?= e($users[$responsibleSub]['nickname'] ?? $responsibleSub) ?></span>
        <?php else: ?>
          <?= e($users[$responsibleSub]['nickname'] ?? $responsibleSub) ?>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?>
    <tr><td colspan="6" class="muted">No releases.</td></tr>
  <?php endif; ?>
  </tbody>
</table>

<p class="legend muted small">
  <span class="badge badge-behind">behind</span> planned PROD date has passed, application not installed on PROD &nbsp;
  <span class="badge badge-overlap">overlap</span> planned PROD date is the same as another release
</p>
<?php
View::footer();
