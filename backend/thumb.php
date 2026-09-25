<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/session.php';
require_once __DIR__ . '/inc/helpers.php';
require_once __DIR__ . '/inc/i18n.php';
require_once __DIR__ . '/inc/videos.php';

if (!file_exists(cDbPath)) {
  http_response_code(404);
  exit;
}

fAuthEnsureSchema();
fRequireLogin();

$vRequestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($vRequestMethod, ['GET', 'HEAD'], true)) {
  header('Allow: GET, HEAD');
  http_response_code(405);
  exit(1);
}

$vId = fParam('id', '');
if ($vId === '' || strpos($vId, '/') !== false || strpos($vId, '\\') !== false || preg_match('/[\x00-\x1F\x7F]/u', $vId)) {
  http_response_code(404);
  exit;
}

$dVideo = fOne('SELECT id, file_path FROM videos WHERE id = ? AND is_available = 1', [$vId]);
if (!$dVideo) {
  http_response_code(404);
  exit;
}

$vFile = fVideoPathFromFilePath($dVideo['file_path'] ?? '', $dVideo['id']);
$vJson = $vFile !== '' ? fVideoJsonPath($vFile) : '';
$vThumbnail = $vFile !== '' ? fVideoThumbnailPath($vFile) : '';

if (!is_file($vFile) || !is_readable($vFile) || !is_file($vJson) || !is_file($vThumbnail) || !is_readable($vThumbnail)) {
  http_response_code(404);
  exit;
}

$vSize = filesize($vThumbnail);
if ($vSize === false || $vSize <= 0) {
  http_response_code(404);
  exit;
}

if (session_status() === PHP_SESSION_ACTIVE) {
  session_write_close();
}

header('Content-Type: image/jpeg');
header('Content-Length: ' . $vSize);
header('Cache-Control: private, max-age=86400');
header('Content-Security-Policy: sandbox');
header('X-Content-Type-Options: nosniff');

if ($vRequestMethod === 'HEAD') {
  exit(0);
}

if (readfile($vThumbnail) === false) {
  exit(1);
}
exit(0);
