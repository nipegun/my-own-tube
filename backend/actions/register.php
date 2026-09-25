<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/session.php';
require_once __DIR__ . '/../inc/helpers.php';
require_once __DIR__ . '/../inc/i18n.php';

fAuthEnsureSchema();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  fRedirect(fUrl('register'));
}
fCsrfRequire();

if (!fRegistrationEnabled()) {
  fToastSave(fT('toast.registration_disabled'));
  fRedirect(fUrl('login'));
}

$vName = $_POST['name'] ?? '';
$vEmail = $_POST['email'] ?? '';
$vPassword = $_POST['password'] ?? '';
$vPasswordConfirm = $_POST['password_confirm'] ?? '';
$vNext = fAuthSafeDestination($_POST['next'] ?? '');

$vName = is_string($vName) ? trim($vName) : '';
$vEmail = is_string($vEmail) ? trim($vEmail) : '';
$vPassword = is_string($vPassword) ? $vPassword : '';
$vPasswordConfirm = is_string($vPasswordConfirm) ? $vPasswordConfirm : '';

try {
  if ($vPassword !== $vPasswordConfirm) {
    throw new RuntimeException(fT('toast.passwords_do_not_match'));
  }

  $vUserId = fAuthCreateUser($vEmail, $vPassword, $vName, 'user');
  fAuthStartSession($vUserId, fSiteDefaultLocale());
  fToastSave(fT('toast.account_created'));
  fRedirect($vNext);
} catch (Throwable $vE) {
  fToastSave($vE->getMessage());
}

fRedirect(fUrl('register', ['next' => $vNext]));
