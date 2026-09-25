<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/session.php';
require_once __DIR__ . '/../inc/helpers.php';
require_once __DIR__ . '/../inc/i18n.php';

fAuthEnsureSchema();
fRequireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  fRedirect(fUrl('settings'));
}
fCsrfRequire();

try {
  fQuery('DELETE FROM search_history WHERE user_id = ?', [fUserId()]);
  fToastSave(fT('toast.search_history_cleared'));
} catch (Throwable $vE) {
  fToastSave(fT('toast.error', ['message' => $vE->getMessage()]));
}

fRedirect(fUrl('settings'));
