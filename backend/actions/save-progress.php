<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/session.php';
require_once __DIR__ . '/../inc/helpers.php';
require_once __DIR__ . '/../inc/i18n.php';
require_once __DIR__ . '/../inc/videos.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

fAuthEnsureSchema();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Allow: POST');
  http_response_code(405);
  echo json_encode(['ok' => false]);
  exit(1);
}
if (!fUserLoggedIn()) {
  http_response_code(401);
  echo json_encode(['ok' => false]);
  exit(1);
}
if (!fCsrfValid()) {
  http_response_code(403);
  echo json_encode(['ok' => false]);
  exit(1);
}

$vVideoId = $_POST['video_id'] ?? '';
$vProgress = $_POST['progress'] ?? '';
$vVideoId = is_string($vVideoId) ? trim($vVideoId) : '';
$vProgress = is_string($vProgress) ? (int) $vProgress : 0;
$vProgress = max(1, min(100, $vProgress));
$vUserId = fUserId();

try {
  $dVideo = fOne('SELECT id, file_path FROM videos WHERE id = ? AND is_available = 1', [$vVideoId]);
  if (!$dVideo) {
    throw new RuntimeException('Video not found.');
  }
  $vVideoPath = fVideoPathFromFilePath($dVideo['file_path'] ?? '', $dVideo['id']);
  if ($vVideoPath === '' || !is_file($vVideoPath) || !is_file(fVideoJsonPath($vVideoPath))) {
    throw new RuntimeException('Video file not found.');
  }

  $vDb = fDb();
  $vDb->beginTransaction();
  try {
    $vInsertStatement = fQuery(
      'INSERT OR IGNORE INTO history (user_id, video_id, progress, watched_at) VALUES (?, ?, ?, ?)',
      [$vUserId, $vVideoId, $vProgress, time()]
    );
    if ($vInsertStatement->rowCount() > 0) {
      fQuery('UPDATE videos SET views = views + 1 WHERE id = ?', [$vVideoId]);
    } else {
      fQuery(
        'UPDATE history SET progress = ?, watched_at = ? WHERE user_id = ? AND video_id = ?',
        [$vProgress, time(), $vUserId, $vVideoId]
      );
    }
    $vDb->commit();
  } finally {
    if ($vDb->inTransaction()) {
      $vDb->rollBack();
    }
  }

  if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
  }
  echo json_encode(['ok' => true, 'progress' => $vProgress]);
  exit(0);
} catch (Throwable $vE) {
  error_log('Progress update failed: ' . $vE->getMessage());
  http_response_code(500);
  echo json_encode(['ok' => false]);
  exit(1);
}
