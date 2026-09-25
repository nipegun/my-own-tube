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

$vLocale = $_POST['locale'] ?? '';
$vBack = fAuthSafeDestination($_POST['back'] ?? fUrl('settings'));
$vLocale = is_string($vLocale) ? fNormalizeLocale($vLocale) : cDefaultLocale;

try {
  fQuery('UPDATE users SET locale = ? WHERE id = ?', [$vLocale, fUserId()]);
  fSetCurrentLocale($vLocale);
  fToastSave(fT('toast.language_updated'));
} catch (Throwable $vE) {
  fToastSave(fT('toast.error', ['message' => $vE->getMessage()]));
}

fRedirect($vBack);
