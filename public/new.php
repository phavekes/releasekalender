<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

$config = new Config();
Auth::requireLogin();
$releases = new Releases(Database::pdoStatic(), $config);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();
    $release = $releases->save($_POST, $_POST['items'] ?? []);
    header('Location: release.php?id=' . $release . '&saved=1');
    exit;
}

$nextNr = $releases->nextReleaseNr();

View::header('New release', 'new');
?>
<div class="page-head">
  <h1>New release</h1>
</div>

<form method="post" class="release-form">
  <?= Csrf::field() ?>
  <?php $mode = 'new';
        $release = ['release_nr' => $nextNr];
        include __DIR__ . '/_release-form.php'; ?>
  <div class="form-actions">
    <button type="submit" class="btn btn-primary">Add release</button>
    <a class="btn btn-ghost" href="index.php">Cancel</a>
  </div>
</form>
<?php
View::footer();
?>
