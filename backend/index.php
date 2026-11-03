<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/session.php';
require_once __DIR__ . '/inc/helpers.php';
require_once __DIR__ . '/inc/i18n.php';
require_once __DIR__ . '/inc/videos.php';
require_once __DIR__ . '/inc/navigation.php';
require_once cFrontendPath . '/components/icons.php';
require_once cFrontendPath . '/components/components.php';
require_once cFrontendPath . '/components/chrome.php';

header_remove('X-Powered-By');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; media-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; object-src 'none'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
header('Referrer-Policy: same-origin');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header('Cache-Control: no-store');

$vRequestPath = fRouteRequestPath();
$vQueryString = (string) ($_SERVER['QUERY_STRING'] ?? '');

// Former /index.php?page=... addresses keep working through a permanent redirect.
if ($vRequestPath === '/index.php' || ($vRequestPath === '/' && isset($_GET['page']))) {
  header('Location: ' . str_replace(["\r", "\n"], '', fRouteLegacyDestination($_GET)), true, 301);
  exit;
}

// Every page address ends with a slash.
if (substr($vRequestPath, -1) !== '/' && (fRouteMatchPage($vRequestPath . '/') !== null || isset(fRouteSectionDefaults()[$vRequestPath . '/']))) {
  $vCanonicalPath = $vRequestPath . '/';
  header('Location: ' . str_replace(["\r", "\n"], '', $vCanonicalPath . ($vQueryString !== '' ? '?' . $vQueryString : '')), true, 301);
  exit;
}

// A section without content of its own opens its default tab.
$dSectionDefaults = fRouteSectionDefaults();
if (isset($dSectionDefaults[$vRequestPath])) {
  fRedirect(fUrl($dSectionDefaults[$vRequestPath]));
}

if (!file_exists(cDbPath)) {
  http_response_code(503);
  require cFrontendPath . '/views/unavailable.php';
  exit;
}

$dPageMatch = fRouteMatchPage($vRequestPath);
$vPage = $dPageMatch !== null ? $dPageMatch['page'] : 'not-found';
if ($dPageMatch !== null) {
  foreach ($dPageMatch['params'] as $vName => $vValue) {
    $_GET[$vName] = $vValue;
  }
}
fRouteCurrentPage($vPage);

$aPublicPages = [
  'login',
  'recover',
];

$aAdminPages = [
  'users',
  'site-settings',
];

try {
  fAuthEnsureSchema();
  fCurrentLocale(true);
} catch (Throwable $vE) {
  error_log('Application schema initialization failed: ' . $vE->getMessage());
  http_response_code(503);
  echo 'The application database could not be initialized.';
  exit(1);
}

if (fRegistrationEnabled()) {
  $aPublicPages[] = 'register';
} elseif ($vPage === 'register') {
  fToastSave(fT('toast.registration_disabled'));
  fRedirect(fUserLoggedIn() ? fUrl('home') : fUrl('login'));
}

if (!fUserLoggedIn() && $vPage !== 'not-found' && !in_array($vPage, $aPublicPages, true)) {
  $vNext = $vRequestPath . ($vQueryString !== '' ? '?' . $vQueryString : '');
  fRedirect(fUrl('login', ['next' => fAuthSafeDestination($vNext)]));
}

if (fUserLoggedIn() && in_array($vPage, $aPublicPages, true)) {
  fRedirect(fUrl('home'));
}

if (in_array($vPage, $aAdminPages, true) && !fUserIsAdmin()) {
  fToastSave(fT('toast.admin_required'));
  fRedirect(fUrl('home'));
}

$vUsePublicLayout = in_array($vPage, $aPublicPages, true) || !fUserLoggedIn();
$vIsWatch = $vPage === 'watch';
$vBodyClass = $vIsWatch ? 'body-row mini' : 'body-row';

ob_start();
try {
  if ($vPage === 'not-found') {
    http_response_code(404);
    require cFrontendPath . '/views/page-not-found.php';
  } else {
    include __DIR__ . '/pages/' . $vPage . '.php';
  }
  $vPageContent = ob_get_clean();
} catch (Throwable $vE) {
  ob_end_clean();
  error_log('Page rendering failed: ' . $vE->getMessage());
  http_response_code(500);
  ob_start();
  require cFrontendPath . '/views/page-error.php';
  $vPageContent = ob_get_clean();
}

require cFrontendPath . '/views/layout.php';
