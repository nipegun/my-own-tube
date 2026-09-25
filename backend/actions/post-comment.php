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

$vVideoId = $_POST['video_id'] ?? '';
$vText   = $_POST['text']     ?? '';
$vVideoId = is_string($vVideoId) ? trim($vVideoId) : '';
$vText   = is_string($vText)   ? trim($vText)   : '';

try {
  $vUserId = fUserId();

  if ($vVideoId === '') {
    fToastSave(fT('toast.missing_video_id'));
    fRedirect(fUrl('home'));
  }

  if ($vText === '') {
    fToastSave(fT('toast.empty_comment'));
    fRedirect(fUrl('watch', ['id' => $vVideoId]));
  }

  if (mb_strlen($vText) > 2000) {
    $vText = mb_substr($vText, 0, 2000);
  }

  $dVideo = fOne('SELECT id FROM videos WHERE id = ? AND is_available = 1', [$vVideoId]);
  if (!$dVideo) {
    fToastSave(fT('toast.video_not_found'));
    fRedirect(fUrl('home'));
  }

  fQuery(
    'INSERT INTO comments (user_id, video_id, user_name, user_color, text, likes, reply_count, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
    [$vUserId, $vVideoId, fUserName(), fUserColor(), $vText, 0, 0, time()]
  );
  fToastSave(fT('toast.comment_posted'));
} catch (Throwable $vE) {
  fToastSave(fT('toast.error', ['message' => $vE->getMessage()]));
}

fRedirect(fUrl('watch', ['id' => $vVideoId]));
