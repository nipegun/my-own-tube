<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/session.php';
require_once __DIR__ . '/../inc/helpers.php';
require_once __DIR__ . '/../inc/i18n.php';

fAuthEnsureSchema();
fRequireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  fRedirect(fUrl('profile'));
}
fCsrfRequire();

$vName = $_POST['name'] ?? '';
$vName = is_string($vName) ? $vName : '';

try {
  fAuthUpdateProfile($vName);
  fToastSave(fT('toast.profile_updated'));
} catch (Throwable $vE) {
  fToastSave($vE->getMessage());
}

fRedirect(fUrl('profile'));
