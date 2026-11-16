<?php

/** @var Config $config */
/** @var array|null $release */
/** @var string $mode 'new' or 'edit' */

/** Shared release form, section order:
 *  Release details, Planned dates, Context, Deployment (standard workflow), Rollback. */

$release ??= null;
$users = (new Users(Database::pdoStatic()))->all();
/** Default responsible for a new release is the creator (the logged-in user). */
$responsible = $release['responsible'] ?? (Auth::user()['sub'] ?? '');
$value = fn(string $field) => e($release[$field] ?? '');
$envData = function (string $env, string $field) use ($release) {
    return e($release['environments'][$env][$field] ?? '');
};
// New releases: the repo tags default to the release number, but stay editable.
$tagDefault = fn(string $field) => $mode !== 'new'
    ? e($release[$field] ?? '')
    : (e($release[$field] ?? '') ?: $value('release_nr'));
?>
<fieldset class="form-section">
  <legend>Release details</legend>
  <div class="form-grid">
    <label>Release nr
      <input type="number" name="release_nr" id="release_nr" min="1" required value="<?= $value('release_nr') ?>">
    </label>
    <label>Application
      <select name="application" id="application" required>
        <option value="">Select application</option>
        <?php foreach ($config->applications() as $key => $app): ?>
          <option value="<?= e($key) ?>" <?= ($release['application'] ?? '') === $key ? 'selected' : '' ?>>
            <?= e($app['label']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Version (new)
      <input type="text" name="version" id="version" list="version-list" required
             value="<?= $value('version') ?>" placeholder="e.g. 8.5.1">
    </label>
    <label>Previous version (on prod)
      <input type="text" name="previous_version" value="<?= $value('previous_version') ?>" placeholder="e.g. 8.4.3">
    </label>
  </div>

  <datalist id="version-list"></datalist>
  <p class="small muted" id="github-status"></p>
  <a id="gh-link" class="small" target="_blank" rel="noopener" hidden>View this release on GitHub</a>

  <div class="form-grid">
    <label>Responsible
      <select name="responsible" required>
        <option value="">Select responsible</option>
        <?php foreach ($users as $sub => $user): ?>
          <option value="<?= e($sub) ?>" <?= $responsible === $sub ? 'selected' : '' ?>>
            <?= e($user['nickname']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Description (Toelichting)
      <input type="text" name="description" value="<?= $value('description') ?>">
    </label>
    <label>Tag OpenConext-deploy
      <input type="text" name="deploy_tag" id="deploy_tag"
             <?= $mode === 'new' ? 'data-default-from="release_nr"' : '' ?>
             value="<?= $tagDefault('deploy_tag') ?>">
    </label>
    <label>Tag surfconext-deployment-environments
      <input type="text" name="environments_tag" id="environments_tag"
             <?= $mode === 'new' ? 'data-default-from="release_nr"' : '' ?>
             value="<?= $tagDefault('environments_tag') ?>">
    </label>
  </div>
</fieldset>

<fieldset class="form-section">
  <legend>Planned dates</legend>
  <p class="muted small">The checkbox marks that the application is installed on the
    environment; the planned date can be set independently. Items can be added and
    removed per release. A release is flagged as "behind" in the overview when the
    earliest planned production date (prodblauw / prodrood) has passed while prod
    is not complete.</p>
  <?php
  $planItems = $mode === 'new'
      ? array_map(fn(string $name) => ['environment' => $name], $config->defaultPlanItems())
      : ($release['environments'] ?? []);
  ?>
  <div class="env-list" id="plan-items">
    <?php foreach ($planItems as $i => $item): ?>
      <div class="env-row env-row-edit plan-item" data-index="<?= (int)$i ?>">
        <span class="drag-handle" title="Drag to reorder" draggable="true">
          <svg viewBox="0 0 16 16" width="12" height="12" stroke="currentColor" stroke-width="1.5" fill="none" aria-hidden="true"><path d="M5 4h1M5 8h7M5 12h7"/></svg>
        </span>
        <input type="checkbox" name="items[<?= (int)$i ?>][installed]" value="1"
               <?= !empty($item['installed']) ? 'checked' : '' ?> title="Installed on this environment">
        <input type="text" name="items[<?= (int)$i ?>][name]" class="item-name"
               value="<?= e($item['environment']) ?>" placeholder="item">
        <input type="date" name="items[<?= (int)$i ?>][planned_date]"
               value="<?= e($item['planned_date'] ?? '') ?>" title="Planned date">
        <button type="button" class="btn btn-ghost item-delete" title="Remove item">
          <svg viewBox="0 0 16 16" width="12" height="12" stroke="currentColor" stroke-width="1.8" fill="none" aria-hidden="true"><path d="M4 4l8 8M12 4l-8 8"/></svg>
        </button>
      </div>
    <?php endforeach; ?>
  </div>
  <button type="button" class="btn btn-ghost" id="item-add">Add item</button>
</fieldset>

<fieldset class="form-section">
  <legend>Context</legend>
  <div class="form-grid">
    <label>Dependencies on other apps?
      <input type="text" name="dependencies" value="<?= $value('dependencies') ?>">
    </label>
    <label>Database changes?
      <input type="text" name="db_changes" value="<?= $value('db_changes') ?>">
    </label>
    <label>Where / who tests?
      <input type="text" name="testers" value="<?= $value('testers') ?>">
    </label>
  </div>

  <label>Components / changes
    <textarea name="changelog" id="changelog" rows="6"><?= $value('changelog') ?></textarea>
  </label>

  <label>Notes
    <textarea name="notes" rows="4"><?= $value('notes') ?></textarea>
  </label>
</fieldset>

<section class="card">
  <h2>Deployment (the standard workflow)</h2>
  <?php if ($mode === 'edit'): ?>
    <ol class="workflow">
      <?php foreach (Workflow::steps($config, $release) as $step):
          $done = Workflow::stepDone($release, $step['env']);
      ?>
        <li class="workflow-step<?= $done ? ' step-done' : '' ?>">
          <div class="step-title">
            <?= e($step['title']) ?>
            <?php if ($done): ?><span class="badge badge-done">done</span><?php endif; ?>
          </div>
          <?php foreach ($step['commands'] as $command): ?>
            <div class="command-row">
              <code class="command"><?= e($command) ?></code>
              <button type="button" class="btn btn-ghost btn-copy" data-command="<?= e($command) ?>"
                      title="Copy command" aria-label="Copy command"><?= View::copyIcon() ?></button>
            </div>
          <?php endforeach; ?>
        </li>
      <?php endforeach; ?>
    </ol>
  <?php else: ?>
    <p class="muted small" id="workflow-hint">Select an application to show its standard workflow.</p>
    <div id="workflow-steps"></div>
  <?php endif; ?>
</section>

<fieldset class="form-section">
  <legend>Rollback information</legend>
  <label>Actions to take for rollback
    <textarea name="rollback" rows="3"
              placeholder="Actions to take for rollback"><?= e($release['rollback'] ?? '') ?></textarea>
  </label>
</fieldset>
