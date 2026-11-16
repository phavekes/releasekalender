<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

$config = new Config();
Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();
    $releases = new Releases(Database::pdoStatic(), $config);
    
    $id = (int)($_POST['id'] ?? 0);
    if (isset($_POST['delete'])) {
        $releases->delete($id);
        header('Location: index.php');
        exit;
    }
    $releases->save($_POST, $_POST['items'] ?? [], $id);
    header('Location: /release?id=' . $id . '&saved=1');
    exit;
}

$releases = new Releases(Database::pdoStatic(), $config);
// For POST requests, use the ID from the form; otherwise use GET parameter
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
} else {
    $id = (int)($_GET['id'] ?? 0);
}
$release = $id ? $releases->find($id) : null;

if (!$release) {
    http_response_code(404);
    View::header('Not found');
    echo '<p class="error">Release not found.</p>';
    View::footer();
    exit;
}

$githubUrl = $config->githubReleasesUrl($release['application']);

View::header('Release ' . $release['release_nr'], 'overview');
?>
<div class="page-head">
  <div>
    <h1>Release <?= (int)$release['release_nr'] ?></h1>
    <p class="muted">
      <?= e($config->applicationLabel($release['application'])) ?>
      <?= e($release['version']) ?>
      <?php if ($githubUrl): ?> -
        <a href="<?= e($githubUrl) ?>" target="_blank" rel="noopener">GitHub releases</a>
      <?php endif; ?>
    </p>
    <?php if ($release['behind']): ?>
      <span class="badge badge-behind">behind schedule</span>
    <?php endif; ?>
    <?php if ($release['done']): ?>
      <span class="badge badge-done">fully deployed</span>
    <?php else: ?>
      <span class="badge badge-env"><?= (int)$release['progress'] ?>% complete</span>
    <?php endif; ?>
  </div>
  <div class="page-actions">
    <?php if ($release['current_environment']): ?>
      <span class="muted">Now on <strong><?= e($release['current_environment']) ?></strong></span>
    <?php endif; ?>
  </div>
</div>

<?php if (isset($_GET['saved']) || isset($_POST['saved'])): ?>
  <p class="flash">Release saved.</p>
<?php endif; ?>

<form method="post" class="release-form">
  <input type="hidden" name="id" value="<?= (int)$release['id'] ?>">
  <?= Csrf::field() ?>
  <?php $mode = 'edit'; include __DIR__ . '/_release-form.php'; ?>
  <div class="form-actions">
    <button type="submit" class="btn btn-primary">Save changes</button>
    <a class="btn btn-ghost" href="/">Back to overview</a>
    <button type="submit" name="delete" value="1" class="btn btn-danger"
            onclick="return confirm('Delete release <?= (int)$release['release_nr'] ?>?')">Delete</button>
  </div>
</form>
<?php
View::footer();
