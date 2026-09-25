<?php

$vDefaultCategory = (string) (fScalar("SELECT name FROM categories WHERE kind = 'virtual' ORDER BY display_order LIMIT 1") ?: 'All');
$vRecentCategory = (string) (fScalar("SELECT name FROM categories WHERE lower(name) IN ('recent', 'recientes') LIMIT 1") ?: 'Recent');
$vCategory = fParam('cat', $vDefaultCategory);
$ldCategories = fAll('SELECT * FROM categories ORDER BY display_order');

if ($vCategory === $vRecentCategory) {
  $dPagination = fPaginate('SELECT COUNT(*) FROM videos WHERE is_available = 1', 'SELECT * FROM videos WHERE is_available = 1 ORDER BY published_at DESC, id');
} elseif ($vCategory === $vDefaultCategory || $vCategory === '') {
  $vCategory = $vDefaultCategory;
  $dPagination = fPaginate('SELECT COUNT(*) FROM videos WHERE is_available = 1', 'SELECT * FROM videos WHERE is_available = 1 ORDER BY id');
} else {
  $dPagination = fPaginate('SELECT COUNT(*) FROM videos WHERE is_available = 1 AND category = ?', 'SELECT * FROM videos WHERE is_available = 1 AND category = ? ORDER BY published_at DESC, id', [$vCategory]);
}
$ldVideos = $dPagination['rows'];

require cFrontendPath . '/views/pages/home.php';
