<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/session.php';
require_once __DIR__ . '/../inc/helpers.php';
require_once __DIR__ . '/../inc/i18n.php';
require_once __DIR__ . '/../inc/videos.php';

fAuthEnsureSchema();
fRequireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  fRedirect(fUrl('upload'));
}
fCsrfRequire();

$dFile = $_FILES['video'] ?? null;
$vTitle = $_POST['title'] ?? '';
$vCategory = $_POST['category'] ?? '';
$vDescription = $_POST['description'] ?? '';

$vTitle = is_string($vTitle) ? trim($vTitle) : '';
$vCategory = is_string($vCategory) ? trim($vCategory) : '';
$vDescription = is_string($vDescription) ? trim($vDescription) : '';
$vDestinationPath = '';
$vJsonPath = '';
$vThumbnailPath = '';
$vUploadedVideoId = '';
$vUploadSucceeded = false;
$vReservationHandle = false;

try {
  if (
    !is_array($dFile)
    || !isset($dFile['error'], $dFile['name'], $dFile['tmp_name'], $dFile['size'])
    || !is_int($dFile['error'])
    || !is_string($dFile['name'])
    || !is_string($dFile['tmp_name'])
    || !is_int($dFile['size'])
    || $dFile['error'] !== UPLOAD_ERR_OK
    || !is_uploaded_file($dFile['tmp_name'])
  ) {
    throw new RuntimeException(fT('toast.invalid_upload_file'));
  }

  $vOriginalName = $dFile['name'];
  $vOriginalExtension = mb_strtolower(pathinfo($vOriginalName, PATHINFO_EXTENSION));
  if (!in_array($vOriginalExtension, fVideoAllowedExtensions(), true)) {
    throw new RuntimeException(fT('toast.unsupported_video_file'));
  }
  $vUploadedSize = $dFile['size'];
  if ($vUploadedSize <= 0) {
    throw new RuntimeException(fT('toast.empty_video_file'));
  }
  if ($vUploadedSize > cMaximumVideoBytes) {
    throw new RuntimeException(fT('toast.video_too_large'));
  }

  if (!is_dir(cVideosPath)) {
    if (!mkdir(cVideosPath, 0755, true) && !is_dir(cVideosPath)) {
      throw new RuntimeException(fT('toast.video_save_failed'));
    }
  }

  for ($vReservationAttempt = 0; $vReservationAttempt < 5; $vReservationAttempt++) {
    $vName = fVideoAvailableFilename($vOriginalName);
    $vExtension = mb_strtolower(pathinfo($vName, PATHINFO_EXTENSION));
    if (!in_array($vExtension, fVideoAllowedExtensions(), true)) {
      throw new RuntimeException(fT('toast.unsupported_video_file'));
    }
    $vDestinationPath = cVideosPath . '/' . $vName;
    $vReservationHandle = @fopen($vDestinationPath, 'x');
    if (is_resource($vReservationHandle)) {
      break;
    }
  }
  if (!is_resource($vReservationHandle) || !fclose($vReservationHandle)) {
    $vReservationHandle = false;
    throw new RuntimeException(fT('toast.video_save_failed'));
  }
  $vReservationHandle = false;

  if (!move_uploaded_file($dFile['tmp_name'], $vDestinationPath)) {
    throw new RuntimeException(fT('toast.video_save_failed'));
  }

  $vBase = pathinfo($vName, PATHINFO_FILENAME);
  $dFileInfo = fVideoValidateFile($vDestinationPath);
  $dMeta = fVideoCreateMetadata($vDestinationPath, [
    'id' => $vBase,
    'title' => $vTitle !== '' ? $vTitle : $vBase,
    'description' => $vDescription,
    'category' => $vCategory !== '' ? $vCategory : 'Uploads',
  ], $dFileInfo);
  $vJsonPath = fVideoJsonPath($vDestinationPath);
  $vThumbnailPath = fVideoThumbnailPath($vDestinationPath);
  fVideoSaveJson($vJsonPath, $dMeta);
  if (!fVideoImportJson($vDestinationPath, $vJsonPath, $dFileInfo)) {
    throw new RuntimeException(fT('toast.video_import_failed'));
  }

  $vUploadedVideoId = (string) $dMeta['id'];
  $vUploadSucceeded = true;
  fToastSave(fT('toast.video_uploaded'));
} catch (Throwable $vE) {
  fToastSave(fT('toast.error', ['message' => $vE->getMessage()]));
} finally {
  if (is_resource($vReservationHandle)) {
    fclose($vReservationHandle);
  }
  if (!$vUploadSucceeded) {
    foreach ([$vThumbnailPath, $vJsonPath, $vDestinationPath] as $vCleanupPath) {
      if ($vCleanupPath !== '' && is_file($vCleanupPath)) {
        unlink($vCleanupPath);
      }
    }
  }
}

if ($vUploadSucceeded) {
  fRedirect(fUrl('watch', ['id' => $vUploadedVideoId]));
}
fRedirect(fUrl('upload'));
