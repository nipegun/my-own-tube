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
$vPlaylistId = is_string($vPlaylistId) ? trim($vPlaylistId) : '';

try {
  $vDeleted = fQuery('DELETE FROM playlists WHERE id = ? AND user_id = ?', [$vPlaylistId, fUserId()])->rowCount();
  if ($vDeleted <= 0) {
    throw new RuntimeException(fT('toast.playlist_not_found'));
  }
  fToastSave(fT('toast.playlist_deleted'));
} catch (Throwable $vE) {
  fToastSave($vE->getMessage());
}

fRedirect(fUrl('playlists'));
