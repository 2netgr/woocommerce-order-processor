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
    <a href="index.php" class="nav-logo">
      <span class="nav-logo-icon">🛒</span>
      <span class="nav-logo-text">Woo<strong>Dashboard</strong></span>
      <span class="nav-version">v<?= APP_VERSION ?></span>
    </a>
    <div class="nav-links">
      <a href="index.php"    class="nav-link <?= basename($_SERVER['SCRIPT_NAME']) === 'index.php'    ? 'nav-link--active' : '' ?>"><?= t('nav_dashboard') ?></a>
      <a href="settings.php" class="nav-link <?= basename($_SERVER['SCRIPT_NAME']) === 'settings.php' ? 'nav-link--active' : '' ?>"><?= t('nav_settings') ?></a>
    </div>
    <div class="nav-actions">
      <button class="btn btn--sm btn--secondary" id="btn-refresh-all"><?= t('nav_refresh') ?></button>
      <!-- Přepínač jazyka — dropdown -->
      <div class="lang-dropdown">
        <button class="lang-current" onclick="this.closest('.lang-dropdown').classList.toggle('lang-dropdown--open')">
          <?= Lang::flag(Lang::get()) ?>
          <span><?= Lang::label(Lang::get()) ?></span>
          <span class="lang-arrow">▾</span>
        </button>
        <div class="lang-menu">
          <?php foreach (Lang::available() as $lng): ?>
          <a href="?setlang=<?= $lng ?>" class="lang-option <?= Lang::get() === $lng ? 'lang-option--active' : '' ?>">
            <?= Lang::flag($lng) ?>
            <span><?= Lang::fullLabel($lng) ?></span>
          </a>
          <?php endforeach; ?>
        </div>
      </div>
      <a href="index.php?logout=1" class="nav-link muted" style="font-size:.8rem"><?= t('nav_logout') ?></a>
    </div>
  </div>
</nav>

<div class="container">
