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

$vName = $_POST['name'] ?? '';
$vDescription = $_POST['description'] ?? '';
$vName = is_string($vName) ? trim($vName) : '';
$vDescription = is_string($vDescription) ? trim($vDescription) : '';

try {
  if ($vName === '') {
    throw new RuntimeException(fT('toast.playlist_name_required'));
  }
  $vName = mb_substr($vName, 0, 120);
  $vDescription = mb_substr($vDescription, 0, 500);

  do {
    $vPlaylistId = 'pl_' . bin2hex(random_bytes(12));
    $dExisting = fOne('SELECT id FROM playlists WHERE id = ?', [$vPlaylistId]);
  } while ($dExisting);

  fQuery(
    'INSERT INTO playlists (id, user_id, name, description, cover_variant, created_at) VALUES (?, ?, ?, ?, ?, ?)',
    [$vPlaylistId, fUserId(), $vName, $vDescription, 1, time()]
  );
  fToastSave(fT('toast.playlist_created'));
  fRedirect(fUrl('playlist', ['id' => $vPlaylistId]));
} catch (Throwable $vE) {
  fToastSave($vE->getMessage());
}

fRedirect(fUrl('playlists'));
