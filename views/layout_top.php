<!DOCTYPE html>
<html lang="<?= Lang::get() ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= Helpers::e(isset($pageTitle) ? $pageTitle : APP_NAME) ?></title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>

<nav class="nav">
  <div class="nav-inner">

    <!-- Logo -->
    <a href="index.php" class="nav-logo">
      <img src="assets/img/logo-woodashboard.png" alt="WooDashboard" class="nav-logo-img">
      <span class="nav-version">v<?= APP_VERSION ?></span>
    </a>

    <!-- Desktop: hlavní linky -->
    <div class="nav-links">
      <a href="index.php"    class="nav-link <?= basename($_SERVER['SCRIPT_NAME']) === 'index.php'    ? 'nav-link--active' : '' ?>"><?= t('nav_dashboard') ?></a>
      <a href="settings.php" class="nav-link <?= basename($_SERVER['SCRIPT_NAME']) === 'settings.php' ? 'nav-link--active' : '' ?>"><?= t('nav_settings') ?></a>
    </div>

    <!-- Desktop: akce vpravo -->
    <div class="nav-actions nav-actions--desktop">
      <button class="btn btn--sm btn--secondary" id="btn-refresh-all"><?= t('nav_refresh') ?></button>
      <div class="lang-dropdown">
        <button class="lang-current" onclick="this.closest('.lang-dropdown').classList.toggle('lang-dropdown--open')">
          <?= Lang::flag(Lang::get()) ?>
          <span><?= Lang::label(Lang::get()) ?></span>
          <span class="lang-arrow">▾</span>
        </button>
        <div class="lang-menu">
          <?php foreach (Lang::available() as $lng): ?>
          <a href="?setlang=<?= htmlspecialchars($lng, ENT_QUOTES, 'UTF-8') ?>" class="lang-option <?= Lang::get() === $lng ? 'lang-option--active' : '' ?>">
            <?= Lang::flag($lng) ?>
            <span><?= Lang::fullLabel($lng) ?></span>
          </a>
          <?php endforeach; ?>
        </div>
      </div>
      <a href="index.php?logout=1" class="nav-link muted" style="font-size:.8rem"><?= t('nav_logout') ?></a>
    </div>

    <!-- Mobile: hamburger tlačítko -->
    <button class="nav-hamburger" id="nav-hamburger" aria-label="Menu">
      <span></span><span></span><span></span>
    </button>

  </div>

  <!-- Mobile: rozbalovací menu -->
  <div class="nav-mobile-menu" id="nav-mobile-menu">
    <a href="index.php"    class="nav-mobile-link <?= basename($_SERVER['SCRIPT_NAME']) === 'index.php'    ? 'nav-mobile-link--active' : '' ?>"><?= t('nav_dashboard') ?></a>
    <a href="settings.php" class="nav-mobile-link <?= basename($_SERVER['SCRIPT_NAME']) === 'settings.php' ? 'nav-mobile-link--active' : '' ?>"><?= t('nav_settings') ?></a>
    <div class="nav-mobile-divider"></div>
    <button class="nav-mobile-link" id="btn-refresh-all-mobile"><?= t('nav_refresh') ?></button>
    <div class="nav-mobile-divider"></div>
    <!-- Jazyky v mobile menu -->
    <div class="nav-mobile-langs">
      <?php foreach (Lang::available() as $lng): ?>
      <a href="?setlang=<?= htmlspecialchars($lng, ENT_QUOTES, 'UTF-8') ?>" class="nav-mobile-lang <?= Lang::get() === $lng ? 'nav-mobile-lang--active' : '' ?>">
        <?= Lang::flag($lng) ?>
        <span><?= Lang::fullLabel($lng) ?></span>
      </a>
      <?php endforeach; ?>
    </div>
    <div class="nav-mobile-divider"></div>
    <a href="index.php?logout=1" class="nav-mobile-link nav-mobile-link--muted"><?= t('nav_logout') ?></a>
  </div>
</nav>

<div class="container">
