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
$vPassword = $_POST['password'] ?? '';
$vUserId = is_string($vUserId) && ctype_digit($vUserId) ? (int) $vUserId : 0;
$vPassword = is_string($vPassword) ? $vPassword : '';

try {
  fAuthAdminSetPassword($vUserId, $vPassword);
  fToastSave(fT('toast.user_password_updated'));
} catch (Throwable $vE) {
  fToastSave($vE->getMessage());
}

fRedirect(fUrl('users'));
