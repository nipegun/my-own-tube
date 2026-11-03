<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/session.php';
require_once __DIR__ . '/../inc/helpers.php';
require_once __DIR__ . '/../inc/i18n.php';

fAuthEnsureSchema();
fRequireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  fRedirect(fUrl('home'));
}
fCsrfRequire();

$vId = $_POST['id'] ?? '';
$vId = is_string($vId) ? trim($vId) : '';

try {
  $vUserId = fUserId();

  if ($vId === '') {
    fToastSave(fT('toast.missing_video_id'));
    fBack(fUrl('home'));
  }

  $dExisting = fOne('SELECT video_id FROM likes WHERE user_id = ? AND video_id = ?', [$vUserId, $vId]);

  if ($dExisting) {
    fQuery('DELETE FROM likes WHERE user_id = ? AND video_id = ?', [$vUserId, $vId]);
    fToastSave(fT('toast.removed_from_liked'));
  } else {
    $dVideo = fOne('SELECT id FROM videos WHERE id = ? AND is_available = 1', [$vId]);
    if (!$dVideo) {
      fToastSave(fT('toast.video_not_found'));
      fBack(fUrl('home'));
    }
    fQuery('INSERT INTO likes (user_id, video_id, liked_at) VALUES (?, ?, ?)', [$vUserId, $vId, time()]);
    fToastSave(fT('toast.marked_as_liked'));
  }
} catch (Throwable $vE) {
  fToastSave(fT('toast.error', ['message' => $vE->getMessage()]));
}

fBack(fUrl('home'));
