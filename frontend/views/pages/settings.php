<div class="section-head-art profile">
  <div style="width:72px;height:72px;border-radius:16px;background:rgba(0,0,0,0.3);display:grid;place-items:center">
    <?= fIconSettings(['size' => 40]) ?>
  </div>
  <div class="art-meta">
    <h1><?= fE(fT('settings.title')) ?></h1>
    <p class="art-sub"><?= fE(fT('settings.subtitle')) ?></p>
  </div>
</div>

<div class="admin-layout">
  <section class="admin-panel">
    <h2><?= fE(fT('settings.user_language_title')) ?></h2>
    <form class="admin-form" method="post" action="actions/set-language.php">
      <?= fCsrfField() ?>
      <input type="hidden" name="back" value="<?= fE(fUrl('settings')) ?>">
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
    <form class="admin-form" method="post" action="actions/change-password.php">
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
    <form method="post" action="actions/clear-search-history.php">
      <?= fCsrfField() ?>
      <button class="btn btn-outline" type="submit"><?= fIconTrash(['size' => 18]) ?> <?= fE(fT('settings.clear_search_history')) ?></button>
    </form>
  </section>

  <?php if (fUserIsAdmin()): ?>
    <section class="admin-panel">
      <h2><?= fE(fT('settings.site_language_title')) ?></h2>
      <form class="admin-form" method="post" action="actions/save-settings.php">
        <?= fCsrfField() ?>
        <label>
          <span><?= fE(fT('settings.site_language_label')) ?></span>
          <select name="default_locale">
            <?php foreach ($dLocales as $vCode => $vName): ?>
              <option value="<?= fE($vCode) ?>"<?= $vCode === $vSiteLocale ? ' selected' : '' ?>><?= fE($vName) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label style="display:flex;align-items:center;gap:10px">
          <input type="checkbox" name="allow_registration" value="1"<?= $vRegistrationEnabled ? ' checked' : '' ?>>
          <span><?= fE(fT('settings.allow_registration')) ?></span>
        </label>
        <p class="settings-note"><?= fE(fT('settings.site_language_help')) ?></p>
        <button class="btn btn-primary" type="submit"><?= fIconCheck(['size' => 18]) ?> <?= fE(fT('common.save')) ?></button>
      </form>
    </section>
  <?php endif; ?>
</div>
