<div class="section-head-art profile">
  <div style="width:72px;height:72px;border-radius:16px;background:rgba(0,0,0,0.3);display:grid;place-items:center">
    <?= fIconLock(['size' => 40]) ?>
  </div>
  <div class="art-meta">
    <h1><?= fE(fT('users.title')) ?></h1>
    <p class="art-sub"><?= fE(fTChoice('users.count_singular', 'users.count_plural', $dPagination['total'])) ?></p>
  </div>
</div>

<div class="admin-layout">
  <section class="admin-panel">
    <h2><?= fE(fT('users.create_user')) ?></h2>
    <form class="admin-form" method="post" action="actions/create-user.php">
      <?= fCsrfField() ?>
      <label>
        <span><?= fE(fT('auth.name')) ?></span>
        <input type="text" name="name" required maxlength="120">
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
        <span><?= fE(fT('users.role')) ?></span>
        <select name="role">
          <option value="user"><?= fE(fT('users.role_user')) ?></option>
          <option value="admin"><?= fE(fT('users.role_admin')) ?></option>
        </select>
      </label>
      <button class="btn btn-primary" type="submit"><?= fIconPlus(['size' => 18]) ?> <?= fE(fT('common.create')) ?></button>
    </form>
  </section>

  <section class="admin-panel">
    <h2><?= fE(fT('users.accounts')) ?></h2>
    <div class="user-list">
      <?php foreach ($ldUsers as $dUser): ?>
        <div class="user-row">
          <div class="user-row-avatar" style="background:<?= fE($dUser['color']) ?>"><?= fE($dUser['initial']) ?></div>
          <div class="user-row-main">
            <h3><?= fE($dUser['name']) ?></h3>
            <p><?= fE($dUser['email']) ?> · <?= fE($dUser['handle']) ?></p>
          </div>
          <form method="post" action="actions/update-user-role.php" style="display:flex;align-items:center;gap:8px">
            <?= fCsrfField() ?>
            <input type="hidden" name="user_id" value="<?= (int) $dUser['id'] ?>">
            <select name="role" aria-label="<?= fE(fT('users.role')) ?>">
              <option value="user"<?= $dUser['role'] === 'user' ? ' selected' : '' ?>><?= fE(fT('users.role_user')) ?></option>
              <option value="admin"<?= $dUser['role'] === 'admin' ? ' selected' : '' ?>><?= fE(fT('users.role_admin')) ?></option>
            </select>
            <button class="btn btn-outline" type="submit"><?= fE(fT('common.save')) ?></button>
          </form>
          <?php if ((int) $dUser['id'] !== fUserId()): ?>
            <form method="post" action="actions/delete-user.php" onsubmit="return confirm(<?= fE(json_encode(fT('users.delete_confirm'))) ?>);">
              <?= fCsrfField() ?>
              <input type="hidden" name="user_id" value="<?= (int) $dUser['id'] ?>">
              <button class="btn btn-outline" type="submit"><?= fIconTrash(['size' => 18]) ?> <?= fE(fT('common.remove')) ?></button>
            </form>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
    <?= fPaginationRender($dPagination, 'users') ?>
  </section>
</div>
