<!DOCTYPE html>
<html lang="<?= Lang::get() ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= t('login_title') ?> — <?= APP_NAME ?></title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body class="body--login">
<div class="login-wrap">
  <div class="login-card">
    <div class="login-logo">
      <img src="assets/img/logo-woodashboard.png" alt="WooDashboard" class="login-logo-img">
    </div>
    <p class="login-sub"><?= t('login_subtitle') ?></p>
    <?php if (!empty($loginLocked)): ?>
    <div class="alert alert--error">
      <?= t('login_locked', (int)ceil($loginLocked / 60)) ?>
    </div>
    <?php elseif (!empty($loginError)): ?>
    <div class="alert alert--error">
      <?= t('login_error') ?>
      <?php if (!empty($loginAttempts) && $loginAttempts < 5): ?>
        <?= t('login_attempts_left', (int)$loginAttempts) ?>
      <?php endif; ?>
    </div>
    <?php endif; ?>
    <form method="post" class="login-form">
      <input type="hidden" name="_csrf" value="<?= Auth::csrfToken() ?>">
      <div class="form-group">
        <label><?= t('login_password') ?></label>
        <input type="password" name="password" autofocus placeholder="••••••••" class="input--lg">
      </div>
      <button type="submit" class="btn btn--primary btn--full"><?= t('login_submit') ?></button>
    </form>
    <div class="login-lang">
      <div class="lang-dropdown lang-dropdown--center">
        <button class="lang-current" onclick="this.closest('.lang-dropdown').classList.toggle('lang-dropdown--open')">
          <?= Lang::flag(Lang::get()) ?>
          <span><?= Lang::fullLabel(Lang::get()) ?></span>
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
    </div>
  </div>
</div>
</body>
</html>
