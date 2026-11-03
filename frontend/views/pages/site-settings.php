<?= fDashboardTabs('admin', 'site-settings') ?>

<div class="section-head-art profile">
  <div style="width:72px;height:72px;border-radius:16px;background:rgba(0,0,0,0.3);display:grid;place-items:center">
    <?= fIconSettings(['size' => 40]) ?>
  </div>
  <div class="art-meta">
    <h1><?= fE(fT('dashboard.site_settings_title')) ?></h1>
    <p class="art-sub"><?= fE(fT('dashboard.site_settings_subtitle')) ?></p>
  </div>
</div>

<div class="admin-layout">
  <section class="admin-panel">
    <h2><?= fE(fT('settings.site_language_title')) ?></h2>
    <form class="admin-form" method="post" action="/api/admin/settings">
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
</div>
