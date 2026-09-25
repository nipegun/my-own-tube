<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/session.php';
require_once __DIR__ . '/../inc/helpers.php';
require_once __DIR__ . '/../inc/i18n.php';

fAuthEnsureSchema();
fRequireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  fRedirect(fUrl('history'));
}
fCsrfRequire();

$vId = $_POST['id'] ?? '';
$vId = is_string($vId) ? trim($vId) : '';

try {
  $vUserId = fUserId();

  if ($vId === '') {
    fToastSave(fT('toast.missing_video_id'));
    fBack(fUrl('history'));
  }

  fQuery('DELETE FROM history WHERE user_id = ? AND video_id = ?', [$vUserId, $vId]);
  fToastSave(fT('toast.removed_from_history'));
} catch (Throwable $vE) {
  fToastSave(fT('toast.error', ['message' => $vE->getMessage()]));
}

fBack(fUrl('history'));
