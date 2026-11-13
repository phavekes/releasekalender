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

    public static function header(string $title, string $active = ''): void
    {
        $user = Auth::user();
        ?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> - Release Calendar</title>
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
    <a class="brand" href="index.php">Release<span>Kalender</span></a>
    <nav class="nav">
      <a href="index.php" class="<?= $active === 'overview' ? 'active' : '' ?>">Overview</a>
      <a href="new.php" class="<?= $active === 'new' ? 'active' : '' ?>">New release</a>
    </nav>
    <div class="user">
      <button type="button" id="theme-toggle" class="btn btn-ghost theme-toggle"
              aria-label="Switch color theme" title="Color theme: click to cycle Auto / Light / Dark">Auto</button>
      <?php if ($user): ?>
        <span class="user-name"><?= e(View::displayName($user)) ?></span>
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
  <div class="container">Release Calendar - SURF Access</div>
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
}
