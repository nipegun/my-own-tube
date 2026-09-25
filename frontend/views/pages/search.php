<div class="tools-row">
  <div class="spacer"></div>
  <span style="color:var(--text-2);font-size:13px">
    <?= fE(fTChoice('search.result_count_singular', 'search.result_count_plural', $dPagination['total'])) ?><?php if ($vQ !== ''): ?> <?= fE(fT('search.for_query', ['query' => '"' . $vQ . '"'])) ?><?php endif; ?>
  </span>
</div>
<?= fPaginationRender($dPagination, 'search', ['q' => $vQ]) ?>

<div class="vlist">
  <?php if (empty($ldVideos)): ?>
    <?= fEmptyState(
      fIconSearch(['size' => 52, 'stroke' => 'var(--text-3)']),
      fT('search.empty_title'),
      fT('search.empty_subtitle', ['query' => $vQ])
    ) ?>
  <?php else: ?>
    <?php foreach ($ldVideos as $dVideo): ?>
      <?= fVideoRow($dVideo) ?>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
