<?php

$vUserId = fUserId();

$dPagination = fPaginate('SELECT COUNT(*) FROM playlists WHERE user_id = ?', 'SELECT * FROM playlists WHERE user_id = ? ORDER BY created_at DESC, id', [$vUserId]);
$ldPlaylists = $dPagination['rows'];
$aPlaylistIds = array_column($ldPlaylists, 'id');
$vPlaylistPlaceholders = implode(',', array_fill(0, count($aPlaylistIds), '?'));

$ldCounts = empty($aPlaylistIds) ? [] : fAll(
  'SELECT pi.playlist_id, COUNT(*) AS n FROM playlist_items pi
  INNER JOIN playlists p ON p.id = pi.playlist_id
  INNER JOIN videos v ON v.id = pi.video_id
  WHERE p.user_id = ? AND v.is_available = 1 AND p.id IN (' . $vPlaylistPlaceholders . ')
  GROUP BY pi.playlist_id',
  array_merge([$vUserId], $aPlaylistIds)
);
$dCount = [];
foreach ($ldCounts as $dRow) {
  $dCount[$dRow['playlist_id']] = (int) $dRow['n'];
}

$ldFirstItems = empty($aPlaylistIds) ? [] : fAll(
  'SELECT pi.playlist_id, v.id, v.file_path, v.thumb_variant, v.category FROM playlist_items pi
  INNER JOIN videos v ON v.id = pi.video_id
  INNER JOIN playlists p ON p.id = pi.playlist_id
  WHERE p.user_id = ? AND v.is_available = 1 AND p.id IN (' . $vPlaylistPlaceholders . ')
    AND pi.position = (
      SELECT MIN(pi2.position) FROM playlist_items pi2
      INNER JOIN videos v2 ON v2.id = pi2.video_id
      WHERE pi2.playlist_id = pi.playlist_id AND v2.is_available = 1
    )',
  array_merge([$vUserId], $aPlaylistIds)
);
$dCover = [];
foreach ($ldFirstItems as $dRow) {
  $dCover[$dRow['playlist_id']] = $dRow;
}

require cFrontendPath . '/views/pages/playlists.php';
