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

$vName = $_POST['name'] ?? '';
$vEmail = $_POST['email'] ?? '';
$vPassword = $_POST['password'] ?? '';
$vRole = $_POST['role'] ?? 'user';

$vName = is_string($vName) ? trim($vName) : '';
$vEmail = is_string($vEmail) ? trim($vEmail) : '';
$vPassword = is_string($vPassword) ? $vPassword : '';
$vRole = is_string($vRole) ? trim($vRole) : 'user';

try {
  fAuthCreateUser($vEmail, $vPassword, $vName, $vRole);
  fToastSave(fT('toast.user_created'));
} catch (Throwable $vE) {
  fToastSave($vE->getMessage());
}

fRedirect(fUrl('users'));
