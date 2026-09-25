<?php

$vUserId = fUserId();
$vTotalLikes = (int) fScalar(
  'SELECT COUNT(*) FROM likes l INNER JOIN videos v ON v.id = l.video_id WHERE l.user_id = ? AND v.is_available = 1',
  [$vUserId]
);
$vTotalWatchLater = (int) fScalar(
  'SELECT COUNT(*) FROM watch_later w INNER JOIN videos v ON v.id = w.video_id WHERE w.user_id = ? AND v.is_available = 1',
  [$vUserId]
);
$vTotalHistory = (int) fScalar(
  'SELECT COUNT(*) FROM history h INNER JOIN videos v ON v.id = h.video_id WHERE h.user_id = ? AND v.is_available = 1',
  [$vUserId]
);
$vTotalPlaylists  = (int) fScalar('SELECT COUNT(*) FROM playlists WHERE user_id = ?', [$vUserId]);

$ldRecentlyWatched = fAll(
  'SELECT v.*, h.progress, h.watched_at FROM history h
  INNER JOIN videos v ON v.id = h.video_id
  WHERE h.user_id = ? AND v.is_available = 1
  ORDER BY h.watched_at DESC LIMIT 6',
  [$vUserId]
);

$ldTopCategories = fAll(
  'SELECT v.category, COUNT(*) AS n FROM history h
  INNER JOIN videos v ON v.id = h.video_id
  WHERE h.user_id = ? AND v.is_available = 1
  GROUP BY v.category
  ORDER BY n DESC LIMIT 5',
  [$vUserId]
);

require cFrontendPath . '/views/pages/profile.php';
