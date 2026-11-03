<section class="auth-card">
  <a class="auth-logo" href="<?= fE(fUrl('home')) ?>">
    <span class="logo-mark" aria-hidden="true"></span>
    <span class="logo-text">MyOwn<span class="accent">Tube</span></span>
  </a>
  <div class="auth-head">
    <h1><?= fE(fT('auth.recover_title')) ?></h1>
    <p><?= fE(fT('auth.recover_subtitle', ['app' => cAppName])) ?></p>
  </div>
  <div class="auth-form">
    <p class="settings-note"><?= fE(fT('auth.recover_user_help')) ?></p>
    <p class="settings-note"><?= fE(fT('auth.recover_admin_help')) ?></p>
  </div>
  <p class="auth-switch">
    <a href="<?= fE(fUrl('login')) ?>"><?= fE(fT('auth.back_to_login')) ?></a>
  </p>
</section>
