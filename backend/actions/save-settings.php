<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/session.php';
require_once __DIR__ . '/../inc/helpers.php';
require_once __DIR__ . '/../inc/i18n.php';

fAuthEnsureSchema();
fRequireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  fRedirect(fUrl('settings'));
}
fCsrfRequire();

$vDefaultLocale = $_POST['default_locale'] ?? cDefaultLocale;
$vDefaultLocale = is_string($vDefaultLocale) ? fNormalizeLocale($vDefaultLocale) : cDefaultLocale;
$vAllowRegistration = isset($_POST['allow_registration']) && $_POST['allow_registration'] === '1';

try {
  fSetSiteDefaultLocale($vDefaultLocale);
  fSetRegistrationEnabled($vAllowRegistration);
  fToastSave(fT('toast.site_language_updated'));
} catch (Throwable $vE) {
  fToastSave(fT('toast.error', ['message' => $vE->getMessage()]));
}

fRedirect(fUrl('settings'));
