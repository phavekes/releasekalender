<?php

declare(strict_types=1);

/**
 * Shared layout: header with navigation, footer.
 */
class View
{
    public static function displayName(?array $user): string
    {
        if (!$user) {
            return '';
        }
        return $user['nickname'] ?? ($user['name'] ?? ($user['sub'] ?? ''));
    }

    public static function header(string $title, string $active = '', bool $public = false): void
    {
        $user = Auth::user();
        $homeUrl = $public ? 'public.php' : 'index.php';
        ?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<title><?= e($title) ?> - <?= e(Constants::APP_NAME) ?></title>
<link rel="icon" type="image/svg+xml" href="favicon.svg">
<link rel="icon" type="image/x-icon" href="favicon.ico">
<link rel="apple-touch-icon" href="apple-touch-icon.png">
<link rel="manifest" href="manifest.json">
<link rel="mask-icon" href="safari-pinned-tab.svg" color="#2563eb">
<script>
(function () {
  var mode = localStorage.getItem('theme') || 'auto';
  var dark = mode === 'dark' ||
    (mode === 'auto' && window.matchMedia('(prefers-color-scheme: dark)').matches);
  if (dark) { document.documentElement.classList.add('dark'); }
})();
</script>
<link rel="stylesheet" href="assets/style.css">
<script src="assets/app.js" defer></script>
</head>
<body>
<header class="topbar">
  <div class="topbar-inner">
    <a class="brand" href="<?= e($homeUrl) ?>"><?= e(Constants::APP_NAME) ?></a>
    <nav class="nav">
      <?php if ($user): ?>
        <a href="index.php" class="<?= $active === 'overview' ? 'active' : '' ?>">Overview</a>
        <a href="new.php" class="<?= $active === 'new' ? 'active' : '' ?>">New release</a>
      <?php else: ?>
        <a href="public.php" class="<?= $active === 'public' ? 'active' : '' ?>">Public Releases</a>
      <?php endif; ?>
    </nav>
    <div class="user">
      <?php if (!$user): ?>
        <a class="btn btn-primary" href="login.php">Log in</a>
      <?php endif; ?>
      <button type="button" id="theme-toggle" class="btn btn-ghost theme-toggle"
              aria-label="Switch color theme" title="Color theme: click to cycle Auto / Light / Dark">
        <?= self::themeIcon() ?>
      </button>
      <?php if ($user): ?>
        <span class="user-name"><?= e(self::displayName($user)) ?></span>
        <a class="btn btn-ghost" href="logout.php">Log out</a>
      <?php endif; ?>
    </div>
  </div>
</header>
<main class="container">
<?php
    }

    public static function footer(): void
    {
        ?>
</main>
<footer class="footer">
  <div class="container">ReleaseCalendar - SURF Access</div>
</footer>
</body>
</html>
<?php
    }

    public static function formatDate(?string $date): string
    {
        if (!$date) {
            return '';
        }
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $date);
        return $dt ? $dt->format('d M Y') : $date;
    }

    public static function copyIcon(): string
    {
        return '<svg viewBox="0 0 16 16" width="14" height="16" fill="none" stroke="currentColor"'
            . ' stroke-width="1.5" aria-hidden="true"><rect x="5.5" y="5.5" width="8" height="8"'
            . ' rx="1.5"/><path d="M10.5 5.5V3.8c0-.7-.6-1.3-1.3-1.3H3.8c-.7 0-1.3.6-1.3 1.3v5.4'
            . ' c0 .7.6 1.3 1.3 1.3h1.4"/></svg>';
    }

    public static function checkIcon(): string
    {
        return '<svg viewBox="0 0 16 16" width="14" height="16" fill="none" stroke="currentColor"'
            . ' stroke-width="2" aria-hidden="true"><path d="M3 8.5l3.2 3.2L13 5"/></svg>';
    }

    public static function themeIcon(): string
    {
        return '<svg viewBox="0 0 16 16" width="16" height="16" fill="none" stroke="currentColor"'
            . ' stroke-width="1.5" aria-hidden="true">'
            . '<circle cx="8" cy="8" r="4"/>'
            . '<path d="M8 2v2M8 12v2M2 8h2M12 8h2"/>'
            . '<path d="m5.9 5.9 1.4 1.4M9.7 9.7 11.1 11.1M5.9 10.1 7.3 8.7M9.7 5.9 11.1 7.3"/>'
            . '</svg>';
    }

    /**
     * Alert message.
     */
    public static function alert(string $type, string $message): string
    {
        $types = ['error', 'warning', 'success', 'info'];
        $type = in_array($type, $types, true) ? $type : 'info';
        return '<p class="alert alert-' . e($type) . '">' . e($message) . '</p>';
    }
}
