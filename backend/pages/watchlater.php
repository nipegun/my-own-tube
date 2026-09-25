<?php

$vUserId = fUserId();

$dPagination = fPaginate(
  'SELECT COUNT(*) FROM watch_later w INNER JOIN videos v ON v.id = w.video_id WHERE w.user_id = ? AND v.is_available = 1',
  'SELECT v.* FROM watch_later w
  INNER JOIN videos v ON v.id = w.video_id
  WHERE w.user_id = ? AND v.is_available = 1
  ORDER BY w.added_at DESC, v.id',
  [$vUserId]
);
$ldVideos = $dPagination['rows'];

require cFrontendPath . '/views/pages/watchlater.php';
