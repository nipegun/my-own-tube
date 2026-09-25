<?php

$vUserId = fUserId();

$dPagination = fPaginate(
  'SELECT COUNT(*) FROM likes l INNER JOIN videos v ON v.id = l.video_id WHERE l.user_id = ? AND v.is_available = 1',
  'SELECT v.* FROM likes l
  INNER JOIN videos v ON v.id = l.video_id
  WHERE l.user_id = ? AND v.is_available = 1
  ORDER BY l.liked_at DESC, v.id',
  [$vUserId]
);
$ldVideos = $dPagination['rows'];

require cFrontendPath . '/views/pages/liked.php';
