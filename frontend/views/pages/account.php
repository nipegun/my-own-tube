<?= fDashboardTabs('user', 'account') ?>

<div class="section-head-art profile">
  <div style="width:72px;height:72px;border-radius:16px;background:rgba(0,0,0,0.3);display:grid;place-items:center">
    <?= fIconSettings(['size' => 40]) ?>
  </div>
  <div class="art-meta">
    <h1><?= fE(fT('dashboard.account_title')) ?></h1>
    <p class="art-sub"><?= fE(fT('dashboard.account_subtitle')) ?></p>
  </div>
</div>

<div class="admin-layout">
  <section class="admin-panel">
    <h2><?= fE(fT('settings.user_language_title')) ?></h2>
    <form class="admin-form" method="post" action="/api/user/language">
      <?= fCsrfField() ?>
      <input type="hidden" name="back" value="<?= fE(fUrl('account')) ?>">
      <label>
        <span><?= fE(fT('settings.user_language_label')) ?></span>
        <select name="locale">
          <?php foreach ($dLocales as $vCode => $vName): ?>
            <option value="<?= fE($vCode) ?>"<?= $vCode === $vUserLocale ? ' selected' : '' ?>><?= fE($vName) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <button class="btn btn-primary" type="submit"><?= fIconCheck(['size' => 18]) ?> <?= fE(fT('common.save')) ?></button>
    </form>
  </section>

  <section class="admin-panel">
    <h2><?= fE(fT('settings.password_title')) ?></h2>
    <form class="admin-form" method="post" action="/api/user/password">
      <?= fCsrfField() ?>
      <label>
        <span><?= fE(fT('settings.current_password')) ?></span>
        <input type="password" name="current_password" required maxlength="<?= (int) cMaximumPasswordLength ?>" autocomplete="current-password">
      </label>
      <label>
        <span><?= fE(fT('settings.new_password')) ?></span>
        <input type="password" name="new_password" required minlength="<?= (int) cMinimumPasswordLength ?>" maxlength="<?= (int) cMaximumPasswordLength ?>" autocomplete="new-password">
      </label>
      <label>
        <span><?= fE(fT('settings.repeat_new_password')) ?></span>
        <input type="password" name="new_password_confirm" required minlength="<?= (int) cMinimumPasswordLength ?>" maxlength="<?= (int) cMaximumPasswordLength ?>" autocomplete="new-password">
      </label>
      <button class="btn btn-primary" type="submit"><?= fIconLock(['size' => 18]) ?> <?= fE(fT('settings.change_password')) ?></button>
    </form>
  </section>

  <section class="admin-panel">
    <h2><?= fE(fT('settings.search_history_title')) ?></h2>
    <form method="post" action="/api/user/searches/clear">
      <?= fCsrfField() ?>
      <button class="btn btn-outline" type="submit"><?= fIconTrash(['size' => 18]) ?> <?= fE(fT('settings.clear_search_history')) ?></button>
    </form>
  </section>
</div>
