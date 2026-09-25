<?php

$vId = fParam('id', '');
$vUserId = fUserId();
$dPlaylist = $vId !== '' ? fOne('SELECT * FROM playlists WHERE id = ? AND user_id = ?', [$vId, $vUserId]) : null;

if (!$dPlaylist) {
  echo fEmptyState(
    fIconPlaylist(['size' => 52, 'stroke' => 'var(--text-3)']),
    fT('playlist.not_found_title'),
    fT('playlist.not_found_subtitle'),
    ['href' => fUrl('playlists'), 'label' => fT('playlist.back_to_playlists')]
  );
  return;
}

$dPagination = fPaginate(
  'SELECT COUNT(*) FROM playlist_items pi INNER JOIN videos v ON v.id = pi.video_id WHERE pi.playlist_id = ? AND v.is_available = 1',
  'SELECT v.*, pi.position FROM playlist_items pi
  INNER JOIN videos v ON v.id = pi.video_id
  WHERE pi.playlist_id = ? AND v.is_available = 1
  ORDER BY pi.position, v.id',
  [$dPlaylist['id']]
);
$ldVideos = $dPagination['rows'];

$vTotalSeconds = (int) fScalar('SELECT COALESCE(SUM(v.duration_sec), 0) FROM playlist_items pi INNER JOIN videos v ON v.id = pi.video_id WHERE pi.playlist_id = ? AND v.is_available = 1', [$dPlaylist['id']]);
$vTotalDuration = fFormatDuration($vTotalSeconds);

$vCoverVariant = (int) $dPlaylist['cover_variant'];
$vCoverLabel = fT('playlist.kind');
$dCoverVideo = fOne('SELECT v.* FROM playlist_items pi INNER JOIN videos v ON v.id = pi.video_id WHERE pi.playlist_id = ? AND v.is_available = 1 ORDER BY pi.position, v.id LIMIT 1', [$dPlaylist['id']]);
if ($dCoverVideo) {
  $vCoverVariant = (int) $dCoverVideo['thumb_variant'];
  $vCoverLabel = mb_strtoupper(fCategoryLabel($dCoverVideo['category']));
}

require cFrontendPath . '/views/pages/playlist.php';
