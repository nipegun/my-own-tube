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
$vName = $_POST['name'] ?? '';
$vDescription = $_POST['description'] ?? '';
$vPlaylistId = is_string($vPlaylistId) ? trim($vPlaylistId) : '';
$vName = is_string($vName) ? trim($vName) : '';
$vDescription = is_string($vDescription) ? trim($vDescription) : '';

try {
  $dPlaylist = fOne('SELECT id FROM playlists WHERE id = ? AND user_id = ?', [$vPlaylistId, fUserId()]);
  if (!$dPlaylist) {
    throw new RuntimeException(fT('toast.playlist_not_found'));
  }
  if ($vName === '') {
    throw new RuntimeException(fT('toast.playlist_name_required'));
  }

  fQuery(
    'UPDATE playlists SET name = ?, description = ? WHERE id = ? AND user_id = ?',
    [mb_substr($vName, 0, 120), mb_substr($vDescription, 0, 500), $vPlaylistId, fUserId()]
  );
  fToastSave(fT('toast.playlist_updated'));
} catch (Throwable $vE) {
  fToastSave($vE->getMessage());
}

fRedirect(fUrl('playlist', ['id' => $vPlaylistId]));
