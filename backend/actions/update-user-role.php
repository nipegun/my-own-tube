<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/session.php';
require_once __DIR__ . '/../inc/helpers.php';
require_once __DIR__ . '/../inc/i18n.php';

fAuthEnsureSchema();
fRequireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  fRedirect(fUrl('users'));
}
fCsrfRequire();

$vUserId = $_POST['user_id'] ?? '';
$vRole = $_POST['role'] ?? 'user';
$vUserId = is_string($vUserId) && ctype_digit($vUserId) ? (int) $vUserId : 0;
$vRole = is_string($vRole) ? $vRole : 'user';

try {
  fAuthAdminUpdateRole($vUserId, $vRole);
  fToastSave(fT('toast.user_updated'));
} catch (Throwable $vE) {
  fToastSave($vE->getMessage());
}

fRedirect(fUrl('users'));
