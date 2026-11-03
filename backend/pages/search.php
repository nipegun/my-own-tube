<?php

$vQuery = fParam('q', '');
$vQ = is_string($vQuery) ? trim($vQuery) : '';
$vQ = mb_substr($vQ, 0, 200);

if ($vQ === '') {
  $dPagination = fPaginate('SELECT COUNT(*) FROM videos WHERE is_available = 1', 'SELECT * FROM videos WHERE is_available = 1 ORDER BY id');
} else {
  $vLike = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $vQ) . '%';
  $dPagination = fPaginate(
    'SELECT COUNT(*) FROM videos v
    LEFT JOIN channels c ON c.id = v.channel_id
    WHERE v.is_available = 1
      AND (v.title LIKE ? ESCAPE \'\\\' OR v.description LIKE ? ESCAPE \'\\\' OR v.category LIKE ? ESCAPE \'\\\' OR c.name LIKE ? ESCAPE \'\\\')',
    'SELECT v.* FROM videos v
    LEFT JOIN channels c ON c.id = v.channel_id
    WHERE v.is_available = 1
      AND (v.title LIKE ? ESCAPE \'\\\' OR v.description LIKE ? ESCAPE \'\\\' OR v.category LIKE ? ESCAPE \'\\\' OR c.name LIKE ? ESCAPE \'\\\')
    ORDER BY v.published_at DESC, v.id',
    [$vLike, $vLike, $vLike, $vLike]
  );
}
$ldVideos = $dPagination['rows'];

require cFrontendPath . '/views/pages/search.php';
