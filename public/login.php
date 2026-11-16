<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

$config = new Config();
$oidc = $config->oidc();

if (isset($_POST['dev'])) {
    Auth::checkRateLimit();
    Auth::devLogin();
}
if (isset($_POST['oidc'])) {
    Auth::checkRateLimit();
    Auth::startLoginStatic($config);
}

View::header('Log in', '', false, $config);
?>
<div class="login-card">
  <h1>Release Calendar</h1>
  <p class="muted">SURF Access release planning.</p>

  <?php if ($oidc['url'] !== '' && $oidc['client_id'] !== ''): ?>
    <form method="post">
      <?= Csrf::field() ?>
      <button type="submit" name="oidc" value="1" class="btn btn-primary btn-block">Log in with OIDC</button>
    </form>
  <?php endif; ?>

  <?php if ($oidc['dev_bypass']): ?>
    <?php if ($oidc['url'] !== '' && $oidc['client_id'] !== ''): ?>
      <div class="divider"><span>local development</span></div>
    <?php endif; ?>
    <form method="post">
      <?= Csrf::field() ?>
      <button type="submit" name="dev" value="1" class="btn btn-block">Continue as local developer</button>
    </form>
  <?php endif; ?>

  <?php if (!$oidc['dev_bypass'] && ($oidc['url'] === '' || $oidc['client_id'] === '')): ?>
    <p class="error">No OIDC provider configured. Set url, client_id and client_secret in config/config.php.</p>
  <?php endif; ?>
</div>
<?php
View::footer();
