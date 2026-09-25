<section class="auth-card">
  <a class="auth-logo" href="<?= fE(fUrl('home')) ?>">
    <span class="logo-mark" aria-hidden="true"></span>
    <span class="logo-text">MyOwn<span class="accent">Tube</span></span>
  </a>
  <div class="auth-head">
    <h1><?= fE(fT('auth.login_title')) ?></h1>
    <p><?= fE(fT('auth.login_subtitle')) ?></p>
  </div>
  <form class="auth-form" method="post" action="actions/login.php">
    <?= fCsrfField() ?>
    <input type="hidden" name="next" value="<?= fE($vNext) ?>">
    <label>
      <span>Email</span>
      <input type="email" name="email" required maxlength="254" autocomplete="email" autofocus>
    </label>
    <label>
      <span><?= fE(fT('auth.password')) ?></span>
      <input type="password" name="password" required maxlength="<?= (int) cMaximumPasswordLength ?>" autocomplete="current-password">
    </label>
    <button class="btn btn-primary auth-submit" type="submit"><?= fE(fT('auth.sign_in')) ?></button>
  </form>
  <?php if (fRegistrationEnabled()): ?>
    <p class="auth-switch">
      <?= fE(fT('auth.no_account')) ?>
      <a href="<?= fE(fUrl('register')) ?>"><?= fE(fT('auth.register_link')) ?></a>
    </p>
  <?php endif; ?>
</section>
