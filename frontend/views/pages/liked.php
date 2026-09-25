<div class="section-head-art liked">
  <div style="width:72px;height:72px;border-radius:16px;background:rgba(0,0,0,0.3);display:grid;place-items:center">
    <?= fIconHeart(['size' => 40]) ?>
  </div>
  <div class="art-meta">
    <h1><?= fE(fT('liked.title')) ?></h1>
    <p class="art-sub"><?= fE(fTChoice('liked.count_singular', 'liked.count_plural', $dPagination['total'])) ?></p>
  </div>
</div>

<?php if (empty($ldVideos)): ?>
  <?= fEmptyState(
    fIconHeart(['size' => 52, 'stroke' => 'var(--text-3)']),
    fT('liked.empty_title'),
    fT('liked.empty_subtitle')
  ) ?>
<?php else: ?>
  <div class="vlist">
    <?php foreach ($ldVideos as $dVideo): ?>
      <?= fVideoRow($dVideo, [
        'removeForm' => [
          'action' => 'actions/toggle-like.php',
          'fields' => ['id' => $dVideo['id']],
        ],
        'removeLabel' => fT('liked.remove'),
      ]) ?>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?= fPaginationRender($dPagination, 'liked') ?>
