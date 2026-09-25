<div class="section-head-art watchlater">
  <div style="width:72px;height:72px;border-radius:16px;background:rgba(0,0,0,0.3);display:grid;place-items:center">
    <?= fIconClock(['size' => 40]) ?>
  </div>
  <div class="art-meta">
    <h1><?= fE(fT('watch_later.title')) ?></h1>
    <p class="art-sub"><?= fE(fTChoice('watch_later.count_singular', 'watch_later.count_plural', $dPagination['total'])) ?></p>
  </div>
</div>

<?php if (empty($ldVideos)): ?>
  <?= fEmptyState(
    fIconClock(['size' => 52, 'stroke' => 'var(--text-3)']),
    fT('watch_later.empty_title'),
    fT('watch_later.empty_subtitle')
  ) ?>
<?php else: ?>
  <div class="vlist">
    <?php foreach ($ldVideos as $dVideo): ?>
      <?= fVideoRow($dVideo, [
        'removeForm' => [
          'action' => 'actions/toggle-watchlater.php',
          'fields' => ['id' => $dVideo['id']],
        ],
        'removeLabel' => fT('watch_later.remove'),
      ]) ?>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?= fPaginationRender($dPagination, 'watchlater') ?>
