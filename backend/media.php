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

if (!is_file($vFile) || !is_readable($vFile) || !is_file($vJson)) {
  http_response_code(404);
  exit;
}

$vSize = filesize($vFile);
if ($vSize === false || $vSize <= 0) {
  http_response_code(404);
  exit(1);
}

$vStart = 0;
$vEnd = $vSize - 1;
$vStatus = 200;
$vRange = trim((string) ($_SERVER['HTTP_RANGE'] ?? ''));

if ($vRange !== '') {
  if (!preg_match('/^bytes=(\d*)-(\d*)$/', $vRange, $aRange) || ($aRange[1] === '' && $aRange[2] === '')) {
    header('Content-Range: bytes */' . $vSize);
    http_response_code(416);
    exit(1);
  }

  if ($aRange[1] === '' && $aRange[2] !== '') {
    $vSuffixLength = (int) $aRange[2];
    if ($vSuffixLength <= 0) {
      header('Content-Range: bytes */' . $vSize);
      http_response_code(416);
      exit(1);
    }
    $vStart = max(0, $vSize - $vSuffixLength);
    $vEnd = $vSize - 1;
  } else {
    $vStart = (int) $aRange[1];
    if ($aRange[2] !== '') {
      $vEnd = min($vEnd, (int) $aRange[2]);
    }
  }

  if ($vStart > $vEnd || $vStart >= $vSize) {
    header('Content-Range: bytes */' . $vSize);
    http_response_code(416);
    exit(1);
  }

  $vStatus = 206;
}

$vLength = $vEnd - $vStart + 1;
if (session_status() === PHP_SESSION_ACTIVE) {
  session_write_close();
}

http_response_code($vStatus);
header('Content-Type: ' . fVideoMimeFromPath($vFile));
header('Accept-Ranges: bytes');
header('Content-Length: ' . $vLength);
header('Cache-Control: private, max-age=0');
header('Content-Security-Policy: sandbox');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

if (fParam('download', '') === '1') {
  header("Content-Disposition: attachment; filename*=UTF-8''" . rawurlencode(basename($vFile)));
}

if ($vStatus === 206) {
  header('Content-Range: bytes ' . $vStart . '-' . $vEnd . '/' . $vSize);
}

if ($vRequestMethod === 'HEAD') {
  exit(0);
}

$vHandle = fopen($vFile, 'rb');
if (!$vHandle) {
  http_response_code(500);
  exit(1);
}

try {
  if (fseek($vHandle, $vStart) !== 0) {
    throw new RuntimeException('Could not seek within the video file.');
  }
  $vRemaining = $vLength;
  while ($vRemaining > 0 && !feof($vHandle) && !connection_aborted()) {
    $vBlock = fread($vHandle, min(1024 * 1024, $vRemaining));
    if ($vBlock === false || $vBlock === '') {
      break;
    }
    echo $vBlock;
    $vRemaining -= strlen($vBlock);
    flush();
  }
} catch (Throwable $vE) {
  error_log('Video streaming error: ' . $vE->getMessage());
} finally {
  fclose($vHandle);
}

exit(0);
