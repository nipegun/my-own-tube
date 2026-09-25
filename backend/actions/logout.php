<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/session.php';
require_once __DIR__ . '/../inc/helpers.php';
require_once __DIR__ . '/../inc/i18n.php';

fAuthEnsureSchema();
fRequireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  fRedirect(fUrl('home'));
}
fCsrfRequire();

$vSignedOutMessage = fT('toast.signed_out');
fAuthLogout();
fToastSave($vSignedOutMessage);
fRedirect(fUrl('login'));
