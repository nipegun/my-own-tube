<?php

function fPlaceholderThumb($pVariant = 1, $pLabel = '', $pStyle = '') {
  $vStyle = $pStyle !== '' ? ' style="' . fE($pStyle) . '"' : '';
  return '<div class="ph-thumb v' . (int) $pVariant . '"' . $vStyle . '>'
    . '<span style="opacity:0.9">' . fE($pLabel) . '</span>'
    . '</div>';
}

function fVideoThumbnailRender($pVideo, $pLabel = '', $pStyle = '') {
  $vUrl = fVideoThumbnailUrl($pVideo);
  if ($vUrl === '') {
    return fPlaceholderThumb((int) ($pVideo['thumb_variant'] ?? 1), $pLabel, $pStyle);
  }

  $vStyle = $pStyle !== '' ? ' style="' . fE($pStyle) . '"' : '';
  return '<img class="thumb-img" src="' . fE($vUrl) . '" alt="" loading="lazy" decoding="async"' . $vStyle . '>';
}

function fChannelAvatar($pChannel, $pSize = 36) {
  if (!$pChannel) return '';
  $aWords = preg_split('/\s+/', trim($pChannel['name']));
  $vInitiales = '';
  foreach (array_slice($aWords, 0, 2) as $vWord) {
    if ($vWord !== '') $vInitiales .= mb_strtoupper(mb_substr($vWord, 0, 1));
  }
  $vFont = (int) ($pSize * 0.38);
  $vColor = $pChannel['color'];
  return '<div class="ph-avatar" style="'
    . 'width:' . (int) $pSize . 'px;height:' . (int) $pSize . 'px;border-radius:999px;'
    . 'background:linear-gradient(135deg, ' . fE($vColor) . ', ' . fE($vColor) . 'cc);'
    . 'font-size:' . $vFont . 'px">'
    . fE($vInitiales)
    . '</div>';
}

function fChannelName($pChannel, $pShowVerified = true) {
  if (!$pChannel) return '';
  $vHtml = '<span style="display:inline-flex;align-items:center;gap:4px">' . fE($pChannel['name']);
  if ($pShowVerified && !empty($pChannel['verified'])) {
    $vHtml .= fIconVerified(12);
  }
  $vHtml .= '</span>';
  return $vHtml;
}

function fFormatLikes($pNum) {
  $vN = (int) $pNum;
  if ($vN >= 1000000) return rtrim(rtrim(number_format($vN / 1000000, 1, '.', ''), '0'), '.') . 'M';
  if ($vN >= 1000)    return rtrim(rtrim(number_format($vN / 1000,    1, '.', ''), '0'), '.') . 'K';
  return (string) $vN;
}

function fVideoCard($pVideo, $pOptions = []) {
  if (!fVideoHasSidecar($pVideo)) {
    return '';
  }

  $dChannel     = fChannelById($pVideo['channel_id']);
  $vBadge       = $pOptions['badge']    ?? null;
  $vProgress    = $pOptions['progress'] ?? null;
  $vHref        = fUrl('watch', ['id' => $pVideo['id']]);

  $vDuration    = fFormatDuration($pVideo['duration_sec']);
  $vWhen        = fFormatWhen($pVideo['published_at']);
  $vViews      = fFormatViews($pVideo['views']);
  $vCatMay      = mb_strtoupper(fCategoryLabel($pVideo['category']));

  ob_start(); ?>
  <a class="vcard" href="<?= fE($vHref) ?>">
    <div class="thumb-wrap">
      <?= fVideoThumbnailRender($pVideo, fT('video.thumbnail_label') . ' · ' . $vCatMay) ?>
      <?php if ($vBadge): ?><div class="thumb-badge"><?= fE($vBadge) ?></div><?php endif; ?>
      <div class="thumb-duration"><?= fE($vDuration) ?></div>
      <?php if ($vProgress !== null): ?>
        <div class="thumb-progress"><div class="thumb-progress-fill" style="width:<?= (int) $vProgress ?>%"></div></div>
      <?php endif; ?>
    </div>
    <div class="vcard-body">
      <div class="vcard-avatar"><?= fChannelAvatar($dChannel, 36) ?></div>
      <div class="vcard-meta">
        <h3 class="vcard-title"><?= fE($pVideo['title']) ?></h3>
        <p class="vcard-channel"><?= fChannelName($dChannel) ?></p>
        <p class="vcard-stats"><?= fE(fT('video.views_count', ['count' => $vViews])) ?> · <?= fE($vWhen) ?></p>
      </div>
    </div>
  </a>
  <?php
  return ob_get_clean();
}

function fVideoRow($pVideo, $pOptions = []) {
  if (!fVideoHasSidecar($pVideo)) {
    return '';
  }

  $dChannel     = fChannelById($pVideo['channel_id']);
  $vIdx         = $pOptions['idx']         ?? null;
  $vPlaying     = $pOptions['playing']     ?? false;
  $vHref        = $pOptions['href']        ?? fUrl('watch', ['id' => $pVideo['id']]);
  $dRemoveForm  = $pOptions['removeForm']  ?? null;
  $vRemoveLabel = $pOptions['removeLabel'] ?? fT('common.remove');

  $vDuration = fFormatDuration($pVideo['duration_sec']);
  $vWhen     = fFormatWhen($pVideo['published_at']);
  $vViews   = fFormatViews($pVideo['views']);

  ob_start(); ?>
  <div class="vlist-item" data-href="<?= fE($vHref) ?>" onclick="window.location.href=this.dataset.href">
    <?php if ($vIdx !== null): ?>
      <div style="display:flex;align-items:center;width:28px;font-size:13px;color:var(--text-2);flex-shrink:0"><?= (int) $vIdx ?></div>
    <?php endif; ?>
    <div class="vlist-thumb-wrap">
      <div class="vlist-thumb"><?= fVideoThumbnailRender($pVideo, mb_strtoupper(fCategoryLabel($pVideo['category']))) ?></div>
      <div class="thumb-duration"><?= fE($vDuration) ?></div>
      <?php if ($vPlaying): ?><div class="thumb-badge" style="background:var(--accent)"><?= fE(fT('video.playing')) ?></div><?php endif; ?>
    </div>
    <div class="vlist-meta">
      <h3><?= fE($pVideo['title']) ?></h3>
      <p class="sub"><?= fChannelName($dChannel) ?> · <?= fE(fT('video.views_count', ['count' => $vViews])) ?> · <?= fE($vWhen) ?></p>
      <p class="desc"><?= fE($pVideo['description']) ?></p>
    </div>
    <?php if ($dRemoveForm): ?>
      <form method="post" action="<?= fE($dRemoveForm['action']) ?>" onclick="event.stopPropagation();" style="align-self:flex-start">
        <?= fCsrfField() ?>
        <?php foreach ($dRemoveForm['fields'] as $vK => $vV): ?>
          <input type="hidden" name="<?= fE($vK) ?>" value="<?= fE($vV) ?>">
        <?php endforeach; ?>
        <button type="submit" class="vlist-menu" aria-label="<?= fE($vRemoveLabel) ?>"><?= fIconClose(['size' => 18]) ?></button>
      </form>
    <?php endif; ?>
  </div>
  <?php
  return ob_get_clean();
}

function fEmptyState($pIconHtml, $pTitle, $pSubtitle, $pCta = null) {
  ob_start(); ?>
  <div class="empty">
    <div class="empty-icon"><?= $pIconHtml ?></div>
    <h2><?= fE($pTitle) ?></h2>
    <p><?= fE($pSubtitle) ?></p>
    <?php if ($pCta): ?>
      <a class="btn btn-primary" href="<?= fE($pCta['href']) ?>"><?= fE($pCta['label']) ?></a>
    <?php endif; ?>
  </div>
  <?php
  return ob_get_clean();
}

function fPaginationRender($pPagination, $pPage, $pParams = []) {
  if ($pPagination['pages'] <= 1) {
    return '';
  }
  $vHtml = '<nav class="pagination" aria-label="' . fE(fT('pagination.label')) . '">';
  if ($pPagination['page'] > 1) {
    $vHref = fUrl($pPage, array_merge($pParams, [$pPagination['parameter'] => $pPagination['page'] - 1]));
    $vHtml .= '<a class="btn btn-outline" rel="prev" href="' . fE($vHref) . '">' . fE(fT('pagination.previous')) . '</a>';
  }
  $vHtml .= '<span>' . fE(fT('pagination.page', ['page' => $pPagination['page'], 'pages' => $pPagination['pages']])) . '</span>';
  if ($pPagination['page'] < $pPagination['pages']) {
    $vHref = fUrl($pPage, array_merge($pParams, [$pPagination['parameter'] => $pPagination['page'] + 1]));
    $vHtml .= '<a class="btn btn-outline" rel="next" href="' . fE($vHref) . '">' . fE(fT('pagination.next')) . '</a>';
  }
  return $vHtml . '</nav>';
}

function fToastsRender() {
  $aMessages = fToastConsume();
  if (empty($aMessages)) return '';
  $vHtml = '';
  foreach ($aMessages as $vMsg) {
    $vHtml .= '<div class="toast">' . fE($vMsg) . '</div>';
  }
  $vHtml .= '<script>setTimeout(function(){document.querySelectorAll(".toast").forEach(function(pNode){pNode.style.transition="opacity .3s";pNode.style.opacity="0";setTimeout(function(){pNode.remove();}, 300);});}, 2500);</script>';
  return $vHtml;
}
