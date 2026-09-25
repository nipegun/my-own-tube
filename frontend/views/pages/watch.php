<div class="watch">
  <div class="watch-main">
    <div class="player">
      <?php if ($vFile !== ''): ?>
        <video class="player-video" controls autoplay preload="metadata" poster="<?= fE($vThumbnail) ?>">
          <source src="<?= fE($vFile) ?>" type="<?= fE($vMimeVideo) ?>">
          <?= fE(fT('watch.video_not_supported')) ?>
        </video>
      <?php else: ?>
        <div class="player-placeholder">
          <?= fVideoThumbnailRender($dVideo, mb_strtoupper(fCategoryLabel($dVideo['category'])), 'width:100%;height:100%;border-radius:0') ?>
          <div class="player-overlay">
            <div class="player-center">
              <?= fIconPlay(['size' => 64, 'stroke' => '#fff']) ?>
              <p><?= fE(fT('watch.file_missing_title')) ?></p>
              <p class="player-sub"><?= fE(fT('watch.file_missing_subtitle')) ?></p>
            </div>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <h1 class="watch-title"><?= fE($dVideo['title']) ?></h1>

    <div class="watch-info">
      <div class="watch-channel">
        <div class="watch-channel-avatar"><?= fChannelAvatar($dChannel, 44) ?></div>
        <div class="watch-channel-meta">
          <p class="watch-channel-name"><?= fChannelName($dChannel) ?></p>
          <p class="watch-channel-subs"><?= fE(fT('watch.subscribers_count', ['count' => $vChannelSubs])) ?></p>
        </div>
      </div>

      <div class="watch-actions">
        <form method="post" action="actions/toggle-like.php" style="display:inline">
          <?= fCsrfField() ?>
          <input type="hidden" name="id" value="<?= fE($dVideo['id']) ?>">
          <button type="submit" class="btn btn-outline<?= $vIsLiked ? ' active' : '' ?>">
            <?= $vIsLiked ? fIconHeartFilled(['size' => 18]) : fIconHeart(['size' => 18]) ?>
            <?= fE($vIsLiked ? fT('watch.liked') : fT('watch.like')) ?>
          </button>
        </form>
        <button type="button" class="btn btn-outline" onclick="if(navigator.share){navigator.share({title:document.title,url:window.location.href});}else{navigator.clipboard.writeText(window.location.href);}"><?= fIconShare(['size' => 18]) ?> <?= fE(fT('common.share')) ?></button>
        <?php if ($vFile !== ''): ?>
          <a class="btn btn-outline" href="<?= fE($vFile . '&download=1') ?>"><?= fIconDownload(['size' => 18]) ?> <?= fE(fT('common.download')) ?></a>
        <?php endif; ?>
        <form method="post" action="actions/toggle-watchlater.php" style="display:inline">
          <?= fCsrfField() ?>
          <input type="hidden" name="id" value="<?= fE($dVideo['id']) ?>">
          <button type="submit" class="btn btn-outline<?= $vIsWatchLater ? ' active' : '' ?>">
            <?= fIconClock(['size' => 18]) ?>
            <?= fE($vIsWatchLater ? fT('watch.in_watch_later') : fT('nav.watch_later')) ?>
          </button>
        </form>
        <?php if (!empty($ldUserPlaylists)): ?>
          <form method="post" action="actions/update-playlist-item.php" style="display:inline-flex;gap:8px">
            <?= fCsrfField() ?>
            <input type="hidden" name="video_id" value="<?= fE($dVideo['id']) ?>">
            <input type="hidden" name="operation" value="add">
            <select name="playlist_id" aria-label="<?= fE(fT('playlists.title')) ?>">
              <?php foreach ($ldUserPlaylists as $dUserPlaylist): ?>
                <option value="<?= fE($dUserPlaylist['id']) ?>"><?= fE($dUserPlaylist['name']) ?></option>
              <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-outline"><?= fIconPlaylist(['size' => 18]) ?> <?= fE(fT('playlists.add_video')) ?></button>
          </form>
        <?php else: ?>
          <a class="btn btn-outline" href="<?= fE(fUrl('playlists')) ?>"><?= fIconPlaylist(['size' => 18]) ?> <?= fE(fT('playlists.create_title')) ?></a>
        <?php endif; ?>
      </div>
    </div>

    <div class="watch-description">
      <p class="watch-desc-stats">
        <?= fE(fT('video.views_count', ['count' => fFormatViews($dVideo['views'])])) ?> ·
        <?= fE(fFormatWhen($dVideo['published_at'])) ?> ·
        <?= fE(fCategoryLabel($dVideo['category'])) ?>
      </p>
      <p class="watch-desc-text"><?= nl2br(fE($dVideo['description'])) ?></p>
    </div>

    <?php if ($dPlaylist && !empty($ldPlaylistVideos)): ?>
      <div class="watch-list">
        <div class="watch-list-head">
          <h3><?= fE($dPlaylist['name']) ?></h3>
          <p><?= fE(fUserName()) ?> · <?= fE(fTChoice('common.video_count_singular', 'common.video_count_plural', count($ldPlaylistVideos))) ?></p>
        </div>
        <div class="vlist">
          <?php foreach ($ldPlaylistVideos as $dPlaylistVideo): ?>
            <?= fVideoRow($dPlaylistVideo, [
              'idx'     => (int) $dPlaylistVideo['position'],
              'href'    => fUrl('watch', ['id' => $dPlaylistVideo['id'], 'list' => $dPlaylist['id']]),
              'playing' => $dPlaylistVideo['id'] === $dVideo['id'],
            ]) ?>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <div class="comments">
      <div class="comments-head">
        <h3><?= fE(fTChoice('watch.comment_count_singular', 'watch.comment_count_plural', $dCommentPagination['total'])) ?></h3>
        <a class="btn btn-ghost" href="<?= fE(fUrl('watch', ['id' => $dVideo['id'], 'list' => $vListId, 'comment_sort' => $vCommentSort === 'oldest' ? 'newest' : 'oldest'])) ?>"><?= fIconSort(['size' => 18]) ?> <?= fE($vCommentSort === 'oldest' ? fT('watch.sort_newest') : fT('watch.sort_oldest')) ?></a>
      </div>

      <form class="comment-form" method="post" action="actions/post-comment.php">
        <?= fCsrfField() ?>
        <input type="hidden" name="video_id" value="<?= fE($dVideo['id']) ?>">
        <div class="comment-form-avatar" style="background:<?= fE(fUserColor()) ?>"><?= fE(fUserInitial()) ?></div>
        <div class="comment-form-body">
          <input type="text" name="text" class="comment-input" placeholder="<?= fE(fT('watch.add_comment')) ?>" required maxlength="2000">
          <div class="comment-form-actions">
            <button type="submit" class="btn btn-primary"><?= fE(fT('watch.comment')) ?></button>
          </div>
        </div>
      </form>

      <?php if (empty($ldComments)): ?>
        <p class="comments-empty"><?= fE(fT('watch.no_comments')) ?></p>
      <?php else: ?>
        <?php foreach ($ldComments as $dComment): ?>
          <div class="comment">
            <div class="comment-avatar" style="background:<?= fE($dComment['user_color']) ?>"><?= fE(mb_substr($dComment['user_name'], 0, 1)) ?></div>
            <div class="comment-body">
              <p class="comment-head">
                <b><?= fE($dComment['user_name']) ?></b>
                <span class="comment-when"><?= fE(fFormatWhen($dComment['created_at'])) ?></span>
              </p>
              <p class="comment-text"><?= nl2br(fE($dComment['text'])) ?></p>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
      <?= fPaginationRender($dCommentPagination, 'watch', ['id' => $vId, 'list' => $vListId, 'comment_sort' => $vCommentSort]) ?>
    </div>
  </div>

  <aside class="watch-side">
    <div class="watch-side-head">
      <h3><?= fE(fT('watch.related_videos')) ?></h3>
    </div>
    <div class="vlist watch-side-list">
      <?php foreach ($ldRelatedVideos as $dRelatedVideo): ?>
        <?= fVideoRow($dRelatedVideo) ?>
      <?php endforeach; ?>
    </div>
  </aside>
</div>

<?php if ($vFile !== ''): ?>
  <script>
    (function() {
      const cPlayer = document.querySelector('.player-video');
      const cCsrfToken = <?= json_encode(fCsrfToken(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
      const cVideoId = <?= json_encode((string) $dVideo['id'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
      const cSavedProgress = <?= (int) $vSavedProgress ?>;
      const cNextPlaylistUrl = <?= json_encode($vNextPlaylistUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
      let vHasPlayed = false;
      let vLastSentAt = 0;

      function fSendProgress(pForce) {
        if (!vHasPlayed || !cPlayer || !Number.isFinite(cPlayer.duration) || cPlayer.duration <= 0) return;
        const cNow = Date.now();
        if (!pForce && cNow - vLastSentAt < 15000) return;
        vLastSentAt = cNow;
        const cProgress = Math.max(1, Math.min(100, Math.round((cPlayer.currentTime / cPlayer.duration) * 100)));
        const cData = new URLSearchParams({
          csrf_token: cCsrfToken,
          video_id: cVideoId,
          progress: String(cProgress)
        });
        fetch('actions/save-progress.php', {
          method: 'POST',
          credentials: 'same-origin',
          headers: {'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'},
          body: cData.toString(),
          keepalive: true
        }).catch(function() {});
      }

      function fRestoreProgress() {
        if (cSavedProgress > 0 && cSavedProgress < 96 && Number.isFinite(cPlayer.duration) && cPlayer.duration > 0) {
          cPlayer.currentTime = cPlayer.duration * cSavedProgress / 100;
        }
      }

      function fHandleEnded() {
        fSendProgress(true);
        if (cNextPlaylistUrl !== '') {
          window.location.href = cNextPlaylistUrl;
        }
      }

      if (cPlayer.readyState >= 1) {
        fRestoreProgress();
      } else {
        cPlayer.addEventListener('loadedmetadata', fRestoreProgress, {once: true});
      }
      cPlayer.addEventListener('playing', function() { vHasPlayed = true; fSendProgress(true); });
      cPlayer.addEventListener('timeupdate', function() { fSendProgress(false); });
      cPlayer.addEventListener('ended', fHandleEnded);
      window.addEventListener('pagehide', function() { fSendProgress(true); });
    }());
  </script>
<?php endif; ?>
