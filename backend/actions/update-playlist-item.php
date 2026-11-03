<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/session.php';
require_once __DIR__ . '/../inc/helpers.php';
require_once __DIR__ . '/../inc/i18n.php';

fAuthEnsureSchema();
fRequireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  fRedirect(fUrl('playlists'));
}
fCsrfRequire();

$vPlaylistId = $_POST['playlist_id'] ?? '';
$vVideoId = $_POST['video_id'] ?? '';
$vOperation = $_POST['operation'] ?? 'add';
$vPlaylistId = is_string($vPlaylistId) ? trim($vPlaylistId) : '';
$vVideoId = is_string($vVideoId) ? trim($vVideoId) : '';
$vOperation = $vOperation === 'remove' ? 'remove' : 'add';

try {
  $dPlaylist = fOne('SELECT id FROM playlists WHERE id = ? AND user_id = ?', [$vPlaylistId, fUserId()]);
  $dVideo = fOne('SELECT id FROM videos WHERE id = ? AND is_available = 1', [$vVideoId]);
  if (!$dPlaylist) {
    throw new RuntimeException(fT('toast.playlist_not_found'));
  }
  if (!$dVideo) {
    throw new RuntimeException(fT('toast.video_not_found'));
  }

  $vDb = fDb();
  fQuery('BEGIN IMMEDIATE');
  try {
    if ($vOperation === 'remove') {
      fQuery('DELETE FROM playlist_items WHERE playlist_id = ? AND video_id = ?', [$vPlaylistId, $vVideoId]);
      $ldItems = fAll('SELECT video_id FROM playlist_items WHERE playlist_id = ? ORDER BY position', [$vPlaylistId]);
      foreach ($ldItems as $vIndex => $dItem) {
        fQuery(
          'UPDATE playlist_items SET position = ? WHERE playlist_id = ? AND video_id = ?',
          [$vIndex + 1, $vPlaylistId, $dItem['video_id']]
        );
      }
    } else {
      $vPosition = (int) fScalar('SELECT COALESCE(MAX(position), 0) + 1 FROM playlist_items WHERE playlist_id = ?', [$vPlaylistId]);
      fQuery(
        'INSERT OR IGNORE INTO playlist_items (playlist_id, video_id, position) VALUES (?, ?, ?)',
        [$vPlaylistId, $vVideoId, $vPosition]
      );
    }
    $vDb->commit();
  } finally {
    if ($vDb->inTransaction()) {
      $vDb->rollBack();
    }
  }

  if ($vOperation === 'remove') {
    fToastSave(fT('toast.playlist_item_removed'));
  } else {
    fToastSave(fT('toast.playlist_item_added'));
  }
} catch (Throwable $vE) {
  fToastSave($vE->getMessage());
}

$vBack = $vOperation === 'remove'
  ? fUrl('playlist', ['id' => $vPlaylistId])
  : fUrl('watch', ['id' => $vVideoId]);
fRedirect($vBack);
