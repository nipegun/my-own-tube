<div class="section-head-art history">
  <div style="width:72px;height:72px;border-radius:16px;background:rgba(0,0,0,0.3);display:grid;place-items:center">
    <?= fIconHistory(['size' => 40]) ?>
  </div>
  <div class="art-meta">
    <h1><?= fE(fT('history.title')) ?></h1>
    <p class="art-sub"><?= fE(fTChoice('history.count_singular', 'history.count_plural', $dPagination['total'])) ?></p>
  </div>
  <?php if (!empty($ldHistory)): ?>
    <form method="post" action="actions/clear-history.php" style="align-self:center" onsubmit="return confirm(<?= fE(json_encode(fT('history.clear_confirm'))) ?>);">
      <?= fCsrfField() ?>
      <button type="submit" class="btn btn-outline"><?= fIconTrash(['size' => 18]) ?> <?= fE(fT('history.clear')) ?></button>
    </form>
  <?php endif; ?>
</div>

<?php if (empty($ldHistory)): ?>
  <?= fEmptyState(
    fIconHistory(['size' => 52, 'stroke' => 'var(--text-3)']),
    fT('history.empty_title'),
    fT('history.empty_subtitle')
  ) ?>
<?php else: ?>
  <?php foreach ($aOrder as $vGroup): ?>
    <div class="history-group">
      <h3 class="history-group-title"><?= fE($vGroup) ?></h3>
      <div class="vlist">
        <?php foreach ($dGroups[$vGroup] as $dVideo): ?>
          <?= fVideoRow($dVideo, [
            'removeForm' => [
              'action' => 'actions/remove-history.php',
              'fields' => ['id' => $dVideo['id']],
            ],
            'removeLabel' => fT('history.remove'),
          ]) ?>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
<?= fPaginationRender($dPagination, 'history') ?>
