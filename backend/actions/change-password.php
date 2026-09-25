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

$vCurrentPassword = $_POST['current_password'] ?? '';
$vNewPassword = $_POST['new_password'] ?? '';
$vNewPasswordConfirm = $_POST['new_password_confirm'] ?? '';
$vCurrentPassword = is_string($vCurrentPassword) ? $vCurrentPassword : '';
$vNewPassword = is_string($vNewPassword) ? $vNewPassword : '';
$vNewPasswordConfirm = is_string($vNewPasswordConfirm) ? $vNewPasswordConfirm : '';

try {
  if ($vNewPassword !== $vNewPasswordConfirm) {
    throw new RuntimeException(fT('toast.passwords_do_not_match'));
  }
  fAuthChangePassword($vCurrentPassword, $vNewPassword);
  fToastSave(fT('toast.password_updated'));
} catch (Throwable $vE) {
  fToastSave($vE->getMessage());
}

fRedirect(fUrl('settings'));
