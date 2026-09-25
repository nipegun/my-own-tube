<?php

$vTab = fParam('tab', 'Now');
$aTabs = ['Now', 'Music', 'Technology', 'Gaming', 'Cooking'];
$vTab = is_string($vTab) && in_array($vTab, $aTabs, true) ? $vTab : 'Now';
$dTabCategories = [
  'Music' => 'Music',
  'Technology' => 'Technology',
  'Gaming' => 'Gaming',
  'Cooking' => 'Cooking',
];

if ($vTab === 'Now') {
  $ldVideos = fAll('SELECT * FROM videos WHERE is_available = 1 AND views > 0 ORDER BY views DESC, published_at DESC LIMIT 15');
} else {
  $ldVideos = fAll(
    'SELECT * FROM videos WHERE is_available = 1 AND category = ? ORDER BY views DESC, published_at DESC LIMIT 15',
    [$dTabCategories[$vTab]]
  );
}

require cFrontendPath . '/views/pages/trending.php';
