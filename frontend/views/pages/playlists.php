<div class="section-head-art playlists">
  <div style="width:72px;height:72px;border-radius:16px;background:rgba(0,0,0,0.3);display:grid;place-items:center">
    <?= fIconPlaylist(['size' => 40]) ?>
  </div>
  <div class="art-meta">
    <h1><?= fE(fT('playlists.title')) ?></h1>
    <p class="art-sub"><?= fE(fTChoice('playlists.count_singular', 'playlists.count_plural', $dPagination['total'])) ?></p>
  </div>
</div>

<section class="admin-panel" style="margin-bottom:24px">
  <h2><?= fE(fT('playlists.create_title')) ?></h2>
  <form class="admin-form" method="post" action="actions/create-playlist.php">
    <?= fCsrfField() ?>
    <label>
      <span><?= fE(fT('playlists.name')) ?></span>
      <input type="text" name="name" required maxlength="120">
    </label>
    <label>
      <span><?= fE(fT('playlists.description')) ?></span>
      <textarea name="description" maxlength="500" rows="3"></textarea>
    </label>
    <button class="btn btn-primary" type="submit"><?= fIconPlus(['size' => 18]) ?> <?= fE(fT('common.create')) ?></button>
  </form>
</section>

<?php if (empty($ldPlaylists)): ?>
  <?= fEmptyState(
    fIconPlaylist(['size' => 52, 'stroke' => 'var(--text-3)']),
    fT('playlists.empty_title'),
    fT('playlists.empty_subtitle')
  ) ?>
<?php else: ?>
  <div class="grid">
    <?php foreach ($ldPlaylists as $dPlaylist): ?>
      <?php
        $vCount = $dCount[$dPlaylist['id']] ?? 0;
        $dCoverVideo = $dCover[$dPlaylist['id']] ?? null;
        $vVariant = $dCoverVideo ? (int) $dCoverVideo['thumb_variant'] : (int) $dPlaylist['cover_variant'];
        $vLabel = $dCoverVideo ? mb_strtoupper(fCategoryLabel($dCoverVideo['category'])) : fT('playlist.kind');
        $vHref = fUrl('playlist', ['id' => $dPlaylist['id']]);
      ?>
      <a class="vcard" href="<?= fE($vHref) ?>">
        <div class="thumb-wrap">
          <?= $dCoverVideo ? fVideoThumbnailRender($dCoverVideo, $vLabel) : fPlaceholderThumb($vVariant, $vLabel) ?>
          <div class="thumb-badge"><?= fIconPlaylist(['size' => 14]) ?> <?= fE(fTChoice('common.video_count_singular', 'common.video_count_plural', $vCount)) ?></div>
          <div class="thumb-duration"><?= fIconPlay(['size' => 12]) ?> <?= fE(fT('playlist.play_all')) ?></div>
        </div>
        <div class="vcard-body">
          <div class="vcard-meta" style="margin-left:0">
            <h3 class="vcard-title"><?= fE($dPlaylist['name']) ?></h3>
            <p class="vcard-channel"><?= fE(fUserName()) ?></p>
            <p class="vcard-stats"><?= fE($dPlaylist['description']) ?></p>
          </div>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?= fPaginationRender($dPagination, 'playlists') ?>
