<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/session.php';
require_once __DIR__ . '/../inc/helpers.php';
require_once __DIR__ . '/../inc/i18n.php';
require_once __DIR__ . '/../inc/videos.php';

fAuthEnsureSchema();
fRequireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  fRedirect(fUrl('upload'));
}
fCsrfRequire();

try {
  $dResult = fVideosSyncFolder();
  fToastSave(
    fT('toast.sync_completed', [
      'imported' => (int) $dResult['imported'],
      'generated' => (int) $dResult['generated'],
      'errors' => (int) $dResult['errors'],
    ])
  );
} catch (Throwable $vE) {
  fToastSave(fT('toast.error', ['message' => $vE->getMessage()]));
}

fRedirect(fUrl('upload'));
