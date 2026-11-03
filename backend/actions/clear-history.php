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

try {
  $vUserId = fUserId();
  $vCount = (int) fScalar('SELECT COUNT(*) FROM history WHERE user_id = ?', [$vUserId]);
  fQuery('DELETE FROM history WHERE user_id = ?', [$vUserId]);
  fToastSave(fT('toast.history_cleared', ['count' => $vCount]));
} catch (Throwable $vE) {
  fToastSave(fT('toast.error', ['message' => $vE->getMessage()]));
}

fRedirect(fUrl('history'));
