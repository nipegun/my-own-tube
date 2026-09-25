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

if (!file_exists(cDbPath)) {
  http_response_code(503);
  require cFrontendPath . '/views/unavailable.php';
  exit;
}

$aValidPages = [
  'login',
  'register',
  'settings',
  'upload',
  'home',
  'trending',
  'search',
  'liked',
  'watchlater',
  'history',
  'playlists',
  'playlist',
  'profile',
  'users',
  'watch',
];

$aPublicPages = [
  'login',
];

$vPage = fCurrentPage();
if (!in_array($vPage, $aValidPages, true)) {
  $vPage = 'home';
}

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

if (!fUserLoggedIn() && !in_array($vPage, $aPublicPages, true)) {
  $vQuery = $_SERVER['QUERY_STRING'] ?? '';
  $vNext = $vQuery !== '' ? '/index.php?' . $vQuery : fUrl('home');
  fRedirect(fUrl('login', ['next' => $vNext]));
}

if (fUserLoggedIn() && in_array($vPage, $aPublicPages, true)) {
  fRedirect(fUrl('home'));
}

if ($vPage === 'users' && !fUserIsAdmin()) {
  fToastSave(fT('toast.admin_required'));
  fRedirect(fUrl('home'));
}

$vIsWatch = $vPage === 'watch';
$vBodyClass = $vIsWatch ? 'body-row mini' : 'body-row';

ob_start();
try {
  include __DIR__ . '/pages/' . $vPage . '.php';
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
