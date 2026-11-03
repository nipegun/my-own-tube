<div class="section-head-art trending">
  <div style="width:72px;height:72px;border-radius:16px;background:rgba(0,0,0,0.3);display:grid;place-items:center">
    <?= fIconTrending(['size' => 40]) ?>
  </div>
  <div class="art-meta">
    <h1><?= fE(fT('trending.title')) ?></h1>
    <p class="art-sub"><?= fE(fT('trending.subtitle', ['app' => cAppName])) ?></p>
  </div>
</div>

<div class="tab-row">
  <?php foreach ($aTabs as $vT): ?>
    <?php $vActive = $vT === $vTab ? ' active' : ''; ?>
    <a class="tab-chip<?= $vActive ?>" href="<?= fE(fUrl('trending', ['tab' => $vT])) ?>"><?= fE(fT('trending.tab_' . fLocaleKey($vT))) ?></a>
  <?php endforeach; ?>
</div>

<div class="vlist">
  <?php if (empty($ldVideos)): ?>
    <?= fEmptyState(
      fIconTrending(['size' => 52, 'stroke' => 'var(--text-3)']),
      fT('trending.empty_title'),
      fT('trending.empty_subtitle')
    ) ?>
  <?php else: ?>
    <?php foreach ($ldVideos as $vIndex => $dVideo): ?>
      <?= fVideoRow($dVideo, ['idx' => $vIndex + 1]) ?>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
