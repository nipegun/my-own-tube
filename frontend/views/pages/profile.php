<div class="profile-head">
  <div class="profile-avatar" style="background:<?= fE(fUserColor()) ?>"><?= fE(fUserInitial()) ?></div>
  <div class="profile-meta">
    <h1><?= fE(fUserName()) ?></h1>
    <p class="profile-handle"><?= fE(fUserHandle()) ?> · <?= fE(fUserEmail()) ?></p>
    <p class="profile-sub"><?= fE(fT('profile.private_subtitle', ['app' => cAppName])) ?></p>
    <div class="profile-actions">
      <a class="btn btn-outline" href="<?= fE(fUrl('settings')) ?>"><?= fIconSettings(['size' => 18]) ?> <?= fE(fT('nav.settings')) ?></a>
    </div>
    <form class="admin-form" method="post" action="actions/update-profile.php" style="margin-top:16px;max-width:480px">
      <?= fCsrfField() ?>
      <label>
        <span><?= fE(fT('auth.name')) ?></span>
        <input type="text" name="name" value="<?= fE(fUserName()) ?>" required maxlength="120" autocomplete="name">
      </label>
      <button class="btn btn-outline" type="submit"><?= fIconEdit(['size' => 18]) ?> <?= fE(fT('profile.edit_profile')) ?></button>
    </form>
  </div>
</div>

<div class="profile-stats">
  <a class="profile-stat" href="<?= fE(fUrl('history')) ?>">
    <span class="profile-stat-icon"><?= fIconHistory(['size' => 22]) ?></span>
    <span class="profile-stat-num"><?= $vTotalHistory ?></span>
    <span class="profile-stat-label"><?= fE(fT('nav.history')) ?></span>
  </a>
  <a class="profile-stat" href="<?= fE(fUrl('liked')) ?>">
    <span class="profile-stat-icon"><?= fIconHeart(['size' => 22]) ?></span>
    <span class="profile-stat-num"><?= $vTotalLikes ?></span>
    <span class="profile-stat-label"><?= fE(fT('nav.liked')) ?></span>
  </a>
  <a class="profile-stat" href="<?= fE(fUrl('watchlater')) ?>">
    <span class="profile-stat-icon"><?= fIconClock(['size' => 22]) ?></span>
    <span class="profile-stat-num"><?= $vTotalWatchLater ?></span>
    <span class="profile-stat-label"><?= fE(fT('nav.watch_later')) ?></span>
  </a>
  <a class="profile-stat" href="<?= fE(fUrl('playlists')) ?>">
    <span class="profile-stat-icon"><?= fIconPlaylist(['size' => 22]) ?></span>
    <span class="profile-stat-num"><?= $vTotalPlaylists ?></span>
    <span class="profile-stat-label"><?= fE(fT('nav.playlists')) ?></span>
  </a>
</div>

<?php if (!empty($ldTopCategories)): ?>
  <div class="lib-section">
    <div class="lib-section-head">
      <h2><?= fE(fT('profile.top_categories')) ?></h2>
    </div>
    <div class="chips">
      <?php foreach ($ldTopCategories as $dCategory): ?>
        <a class="chip" href="<?= fE(fUrl('home', ['cat' => $dCategory['category']])) ?>">
          <?= fE(fCategoryLabel($dCategory['category'])) ?> · <?= (int) $dCategory['n'] ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

<?php if (!empty($ldRecentlyWatched)): ?>
  <div class="lib-section">
    <div class="lib-section-head">
      <h2><?= fE(fT('profile.recently_watched')) ?></h2>
      <a class="lib-more" href="<?= fE(fUrl('history')) ?>"><?= fE(fT('profile.view_history')) ?> <?= fIconChevronRight(['size' => 16]) ?></a>
    </div>
    <div class="grid">
      <?php foreach ($ldRecentlyWatched as $dVideo): ?>
        <?= fVideoCard($dVideo, ['progress' => (int) $dVideo['progress']]) ?>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>
