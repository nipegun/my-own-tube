<?= fEmptyState(
  fIconSearch(['size' => 52, 'stroke' => 'var(--text-3)']),
  fT('error.page_not_found_title'),
  fT('error.page_not_found_subtitle'),
  ['href' => fUrl('home'), 'label' => fT('common.back_home')]
) ?>
