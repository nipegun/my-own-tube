<div class="playlist-head">
  <div class="playlist-cover">
    <?= $dCoverVideo ? fVideoThumbnailRender($dCoverVideo, $vCoverLabel) : fPlaceholderThumb($vCoverVariant, $vCoverLabel) ?>
    <div class="playlist-cover-count"><?= fE(fTChoice('common.video_count_singular', 'common.video_count_plural', $dPagination['total'])) ?></div>
  </div>
  <div class="playlist-meta">
    <p class="playlist-kind"><?= fE(fT('playlist.kind')) ?></p>
    <h1><?= fE($dPlaylist['name']) ?></h1>
    <p class="playlist-owner"><?= fE(fUserName()) ?> · <?= fE(fTChoice('common.video_count_singular', 'common.video_count_plural', $dPagination['total'])) ?> · <?= fE($vTotalDuration) ?></p>
    <p class="playlist-desc"><?= fE($dPlaylist['description']) ?></p>
    <div class="playlist-actions">
      <?php if ($dCoverVideo): ?>
        <a class="btn btn-primary" href="<?= fE(fUrl('watch', ['id' => $dCoverVideo['id'], 'list' => $dPlaylist['id']])) ?>">
          <?= fIconPlay(['size' => 18]) ?> <?= fE(fT('playlist.play_all')) ?>
        </a>
      <?php endif; ?>
      <button type="button" class="btn btn-outline" onclick="if(navigator.share){navigator.share({title:document.title,url:window.location.href});}else{navigator.clipboard.writeText(window.location.href);}"><?= fIconShare(['size' => 18]) ?> <?= fE(fT('common.share')) ?></button>
    </div>
    <form class="admin-form" method="post" action="actions/update-playlist.php" style="margin-top:16px">
      <?= fCsrfField() ?>
      <input type="hidden" name="playlist_id" value="<?= fE($dPlaylist['id']) ?>">
      <label>
        <span><?= fE(fT('playlists.name')) ?></span>
        <input type="text" name="name" value="<?= fE($dPlaylist['name']) ?>" required maxlength="120">
      </label>
      <label>
        <span><?= fE(fT('playlists.description')) ?></span>
        <textarea name="description" maxlength="500" rows="3"><?= fE($dPlaylist['description']) ?></textarea>
      </label>
      <button class="btn btn-outline" type="submit"><?= fIconEdit(['size' => 18]) ?> <?= fE(fT('common.save')) ?></button>
    </form>
    <form method="post" action="actions/delete-playlist.php" style="margin-top:12px" onsubmit="return confirm(<?= fE(json_encode(fT('playlists.delete_confirm'))) ?>);">
      <?= fCsrfField() ?>
      <input type="hidden" name="playlist_id" value="<?= fE($dPlaylist['id']) ?>">
      <button class="btn btn-outline" type="submit"><?= fIconTrash(['size' => 18]) ?> <?= fE(fT('playlists.delete')) ?></button>
    </form>
  </div>
</div>

<?php if (empty($ldVideos)): ?>
  <?= fEmptyState(
    fIconPlaylist(['size' => 52, 'stroke' => 'var(--text-3)']),
    fT('playlist.empty_title'),
    fT('playlist.empty_subtitle')
  ) ?>
<?php else: ?>
  <div class="vlist">
    <?php foreach ($ldVideos as $dVideo): ?>
      <?= fVideoRow($dVideo, [
        'idx'  => (int) $dVideo['position'],
        'href' => fUrl('watch', ['id' => $dVideo['id'], 'list' => $dPlaylist['id']]),
        'removeForm' => [
          'action' => 'actions/update-playlist-item.php',
          'fields' => [
            'playlist_id' => $dPlaylist['id'],
            'video_id' => $dVideo['id'],
            'operation' => 'remove',
          ],
        ],
        'removeLabel' => fT('playlists.remove_video'),
      ]) ?>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?= fPaginationRender($dPagination, 'playlist', ['id' => $vId]) ?>
