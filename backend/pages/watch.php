<?php

$vId = fParam('id', '');
$vListId = fParam('list', '');
$vCommentSort = fParam('comment_sort', 'newest');
$vUserId = fUserId();
$dVideo = $vId !== '' ? fOne('SELECT * FROM videos WHERE id = ?', [$vId]) : null;

if (!$dVideo) {
  echo fEmptyState(
    fIconPlay(['size' => 52, 'stroke' => 'var(--text-3)']),
    fT('watch.not_found_title'),
    fT('watch.not_found_subtitle'),
    ['href' => fUrl('home'), 'label' => fT('common.back_home')]
  );
  return;
}

if ((int) ($dVideo['is_available'] ?? 1) !== 1 || !fVideoHasSidecar($dVideo)) {
  echo fEmptyState(
    fIconPlay(['size' => 52, 'stroke' => 'var(--text-3)']),
    fT('watch.unavailable_title'),
    fT('watch.unavailable_subtitle'),
    ['href' => fUrl('home'), 'label' => fT('common.back_home')]
  );
  return;
}

$vSavedProgress = (int) fScalar(
  'SELECT progress FROM history WHERE user_id = ? AND video_id = ?',
  [$vUserId, $dVideo['id']]
);

$dChannel = fChannelById($dVideo['channel_id']);
$vCommentOrder = $vCommentSort === 'oldest' ? 'ASC' : 'DESC';

$dCommentPagination = fPaginate(
  'SELECT COUNT(*) FROM comments WHERE video_id = ?',
  'SELECT * FROM comments WHERE video_id = ? ORDER BY created_at ' . $vCommentOrder . ', id ' . $vCommentOrder,
  [$dVideo['id']],
  'comments_page'
);
$ldComments = $dCommentPagination['rows'];

$ldUserPlaylists = fAll('SELECT id, name FROM playlists WHERE user_id = ? ORDER BY created_at DESC', [$vUserId]);

$dLikes      = fIdSet('likes');
$dWatchLater = fIdSet('watch_later');
$vIsLiked = isset($dLikes[$dVideo['id']]);
$vIsWatchLater = isset($dWatchLater[$dVideo['id']]);

$ldRelatedVideos = fAll(
  'SELECT * FROM videos WHERE is_available = 1 AND id != ? AND (category = ? OR channel_id = ?) ORDER BY views DESC, published_at DESC LIMIT 12',
  [$dVideo['id'], $dVideo['category'], $dVideo['channel_id']]
);
if (count($ldRelatedVideos) < 6) {
  $ldMoreVideos = fAll('SELECT * FROM videos WHERE is_available = 1 AND id != ? ORDER BY views DESC, published_at DESC LIMIT 12', [$dVideo['id']]);
  $dAlreadySeen = [];
  foreach ($ldRelatedVideos as $dRelatedVideo) $dAlreadySeen[$dRelatedVideo['id']] = true;
  foreach ($ldMoreVideos as $dRelatedVideo) {
    if (!isset($dAlreadySeen[$dRelatedVideo['id']])) $ldRelatedVideos[] = $dRelatedVideo;
    if (count($ldRelatedVideos) >= 12) break;
  }
}

$dPlaylist = null;
$ldPlaylistVideos = [];
$vNextPlaylistUrl = '';
if ($vListId !== '') {
  $dPlaylist = fOne('SELECT * FROM playlists WHERE id = ? AND user_id = ?', [$vListId, $vUserId]);
  if ($dPlaylist) {
    $ldPlaylistVideos = fAll(
      'SELECT v.*, pi.position FROM playlist_items pi
      INNER JOIN videos v ON v.id = pi.video_id
      WHERE pi.playlist_id = ? AND v.is_available = 1
      ORDER BY pi.position',
      [$dPlaylist['id']]
    );
    foreach ($ldPlaylistVideos as $vPlaylistIndex => $dPlaylistVideo) {
      if ($dPlaylistVideo['id'] === $dVideo['id'] && isset($ldPlaylistVideos[$vPlaylistIndex + 1])) {
        $vNextPlaylistUrl = fUrl('watch', [
          'id' => $ldPlaylistVideos[$vPlaylistIndex + 1]['id'],
          'list' => $dPlaylist['id'],
        ]);
        break;
      }
    }
  }
}

$vChannelSubs = $dChannel ? fFormatLikes($dChannel['subs']) : '0';

$vFile = '';
$vMimeVideo = 'video/mp4';
$vVideoPath = fVideoPathFromFilePath($dVideo['file_path'] ?? '', $dVideo['id']);
$vJsonPath = $vVideoPath !== '' ? fVideoJsonPath($vVideoPath) : '';
$vThumbnail = fVideoThumbnailUrl($dVideo);
if ($vVideoPath !== '' && is_file($vVideoPath) && is_file($vJsonPath)) {
  $vFile = 'media.php?id=' . rawurlencode($dVideo['id']);
  $vMimeVideo = fVideoMimeFromPath($vVideoPath);
}

require cFrontendPath . '/views/pages/watch.php';
