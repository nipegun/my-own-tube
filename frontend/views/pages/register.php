<section class="auth-card">
  <a class="auth-logo" href="<?= fE(fUrl('home')) ?>">
    <span class="logo-mark" aria-hidden="true"></span>
    <span class="logo-text">MyOwn<span class="accent">Tube</span></span>
  </a>
  <div class="auth-head">
    <h1><?= fE(fT('auth.register_title')) ?></h1>
    <p><?= fE(fT('auth.register_subtitle')) ?></p>
  </div>
  <form class="auth-form" method="post" action="actions/register.php">
    <?= fCsrfField() ?>
    <input type="hidden" name="next" value="<?= fE($vNext) ?>">
    <label>
      <span><?= fE(fT('auth.name')) ?></span>
      <input type="text" name="name" required maxlength="120" autocomplete="name">
    </label>
    <label>
      <span>Email</span>
      <input type="email" name="email" required maxlength="254" autocomplete="email">
    </label>
    <label>
      <span><?= fE(fT('auth.password')) ?></span>
      <input type="password" name="password" required minlength="<?= (int) cMinimumPasswordLength ?>" maxlength="<?= (int) cMaximumPasswordLength ?>" autocomplete="new-password">
    </label>
    <label>
      <span><?= fE(fT('auth.repeat_password')) ?></span>
      <input type="password" name="password_confirm" required minlength="<?= (int) cMinimumPasswordLength ?>" maxlength="<?= (int) cMaximumPasswordLength ?>" autocomplete="new-password">
    </label>
    <button class="btn btn-primary auth-submit" type="submit"><?= fE(fT('auth.create_account')) ?></button>
  </form>
  <p class="auth-switch">
    <?= fE(fT('auth.has_account')) ?>
    <a href="<?= fE(fUrl('login')) ?>"><?= fE(fT('auth.login_link')) ?></a>
  </p>
</section>
