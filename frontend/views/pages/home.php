<div class="chips">
  <?php foreach ($ldCategories as $dCategory): ?>
    <?php $vActive = $dCategory['name'] === $vCategory ? ' active' : ''; ?>
    <a class="chip<?= $vActive ?>" href="<?= fE(fUrl('home', ['cat' => $dCategory['name']])) ?>"><?= fE(fCategoryLabel($dCategory['name'])) ?></a>
  <?php endforeach; ?>
</div>

<?php if (empty($ldVideos)): ?>
  <?= fEmptyState(
    fIconSearch(['size' => 52, 'stroke' => 'var(--text-3)']),
    fT('home.empty_title'),
    fT('home.empty_subtitle')
  ) ?>
<?php else: ?>
  <div class="grid">
    <?php foreach ($ldVideos as $dVideo): ?>
      <?= fVideoCard($dVideo) ?>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?= fPaginationRender($dPagination, 'home', ['cat' => $vCategory]) ?>
