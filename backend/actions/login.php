<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/session.php';
require_once __DIR__ . '/../inc/helpers.php';
require_once __DIR__ . '/../inc/i18n.php';

fAuthEnsureSchema();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  fRedirect(fUrl('login'));
}
fCsrfRequire();

$vEmail = $_POST['email'] ?? '';
$vPassword = $_POST['password'] ?? '';
$vNext = fAuthSafeDestination($_POST['next'] ?? '');

$vEmail = is_string($vEmail) ? trim($vEmail) : '';
$vPassword = is_string($vPassword) ? $vPassword : '';

if (fAuthLogin($vEmail, $vPassword)) {
  fToastSave(fT('toast.signed_in'));
  fRedirect($vNext);
}

fToastSave(fT('toast.invalid_login'));
fRedirect(fUrl('login', ['next' => $vNext]));
