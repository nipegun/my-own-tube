<?php

function fVideoMp4Extensions() {
  return fVideoAllowedExtensions();
}

function fVideoAllowedExtensions() {
  return ['mp4', 'm4v', 'avi', 'wmv', '3gp', 'mpg', 'divx', 'mp5', 'mpeg', 'asf'];
}

function fVideoMimeByExtension($pExtension) {
  $vExtension = mb_strtolower(trim((string) $pExtension));
  $dMimes = [
    'mp4' => 'video/mp4',
    'm4v' => 'video/x-m4v',
    'avi' => 'video/x-msvideo',
    'wmv' => 'video/x-ms-wmv',
    '3gp' => 'video/3gpp',
    'mpg' => 'video/mpeg',
    'mpeg' => 'video/mpeg',
    'divx' => 'video/divx',
    'mp5' => 'video/mp4',
    'asf' => 'video/x-ms-asf',
  ];

  return $dMimes[$vExtension] ?? 'application/octet-stream';
}

function fVideoMimeFromPath($pVideoPath) {
  return fVideoMimeByExtension(pathinfo($pVideoPath, PATHINFO_EXTENSION));
}

function fVideoMetadataText($pValue, $pDefault = '') {
  return is_scalar($pValue) ? (string) $pValue : (string) $pDefault;
}

function fVideoRealBasePath() {
  $vPath = realpath(cVideosPath);
  if ($vPath === false) {
    if (!mkdir(cVideosPath, 0755, true) && !is_dir(cVideosPath)) {
      throw new RuntimeException('Could not create the videos directory.');
    }
    $vPath = realpath(cVideosPath);
  }
  if ($vPath === false) {
    throw new RuntimeException('Could not resolve the videos directory.');
  }
  return $vPath;
}

function fVideoSafeFilename($pName) {
  $vName = basename(str_replace('\\', '/', (string) $pName));
  $vName = preg_replace('/[\x00-\x1F\x7F]/', '', $vName);
  $vName = trim($vName);

  if ($vName === '' || $vName === '.' || $vName === '..') {
    $vName = 'video-' . time() . '.mp4';
  }

  $vExtension = mb_strtolower(pathinfo($vName, PATHINFO_EXTENSION));
  if (!in_array($vExtension, fVideoAllowedExtensions(), true)) {
    throw new RuntimeException(function_exists('fT') ? fT('toast.unsupported_video_file') : 'The video file type is not supported.');
  }

  $vBase = mb_strcut(pathinfo($vName, PATHINFO_FILENAME), 0, 180, 'UTF-8');
  if ($vBase === '') {
    $vBase = 'video-' . time();
  }

  return $vBase . '.' . $vExtension;
}

function fVideoAvailableFilename($pName) {
  $vName = fVideoSafeFilename($pName);
  $vBase = pathinfo($vName, PATHINFO_FILENAME);
  $vExtension = pathinfo($vName, PATHINFO_EXTENSION);
  $vCandidate = $vName;
  $vCounter = 2;

  while (
    file_exists(cVideosPath . '/' . $vCandidate)
    || file_exists(cVideosPath . '/' . pathinfo($vCandidate, PATHINFO_FILENAME) . '.json')
    || (function_exists('fOne') && fOne('SELECT id FROM videos WHERE id = ?', [pathinfo($vCandidate, PATHINFO_FILENAME)]))
  ) {
    $vCandidate = $vBase . '-' . $vCounter . '.' . $vExtension;
    $vCounter++;
  }

  return $vCandidate;
}

function fVideoJsonPath($pVideoPath) {
  $vDir = dirname($pVideoPath);
  $vBase = pathinfo($pVideoPath, PATHINFO_FILENAME);
  return $vDir . '/' . $vBase . '.json';
}

function fVideoThumbnailPath($pVideoPath) {
  $vDir = dirname($pVideoPath);
  $vBase = pathinfo($pVideoPath, PATHINFO_FILENAME);
  return $vDir . '/' . $vBase . '.thumb.jpg';
}

function fVideoThumbnailExistsFromPath($pVideoPath) {
  $vThumbnailPath = fVideoThumbnailPath($pVideoPath);
  return is_file($vThumbnailPath) && is_readable($vThumbnailPath) && filesize($vThumbnailPath) > 0;
}

function fVideoThumbnailExists($pVideo) {
  $vVideoPath = fVideoPathFromFilePath($pVideo['file_path'] ?? '', $pVideo['id'] ?? '');
  if ($vVideoPath === '') {
    return false;
  }

  return fVideoThumbnailExistsFromPath($vVideoPath);
}

function fVideoThumbnailUrl($pVideo) {
  if (!fVideoThumbnailExists($pVideo)) {
    return '';
  }

  return '/thumb.php?id=' . rawurlencode((string) ($pVideo['id'] ?? ''));
}

function fVideoPathFromFilePath($pFilePath, $pId = '') {
  $vBaseReal = realpath(cVideosPath);
  $vPath = '';

  if ($pFilePath !== null && trim((string) $pFilePath) !== '') {
    $vFilePath = str_replace('\\', '/', trim((string) $pFilePath));
    if (strncmp($vFilePath, 'videos/', 7) === 0) {
      $vPath = cVideosPath . '/' . basename(substr($vFilePath, 7));
    } else {
      $vPath = cVideosPath . '/' . basename($vFilePath);
    }
  } elseif ($pId !== '') {
    $vPath = cVideosPath . '/' . fVideoSafeFilename($pId . '.mp4');
  }

  if ($vPath === '') {
    return '';
  }

  $vDirReal = realpath(dirname($vPath));
  if ($vBaseReal === false || $vDirReal === false || $vDirReal !== $vBaseReal) {
    return '';
  }

  return $vPath;
}

function fVideoHasSidecar($pVideo) {
  if (empty($pVideo['file_path'])) {
    return false;
  }

  $vVideoPath = fVideoPathFromFilePath($pVideo['file_path'], $pVideo['id'] ?? '');
  if ($vVideoPath === '' || !is_file($vVideoPath)) {
    return false;
  }

  return is_file(fVideoJsonPath($vVideoPath));
}

function fVideoReadJson($pJsonPath) {
  if (!is_file($pJsonPath) || !is_readable($pJsonPath)) {
    return null;
  }

  $vContent = file_get_contents($pJsonPath);
  if ($vContent === false) {
    return null;
  }

  $dJson = json_decode($vContent, true);
  return is_array($dJson) ? $dJson : null;
}

function fVideoSaveJson($pJsonPath, $pMeta) {
  $vJson = json_encode($pMeta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  if ($vJson === false) {
    throw new RuntimeException(function_exists('fT') ? fT('toast.metadata_json_failed') : 'Could not generate the metadata JSON.');
  }

  $vTemporaryPath = $pJsonPath . '.new.' . getmypid() . '.' . bin2hex(random_bytes(4));
  try {
    $vWrittenBytes = file_put_contents($vTemporaryPath, $vJson . "\n", LOCK_EX);
    if ($vWrittenBytes === false || !chmod($vTemporaryPath, 0644) || !rename($vTemporaryPath, $pJsonPath)) {
      throw new RuntimeException(function_exists('fT') ? fT('toast.metadata_json_failed') : 'Could not save the metadata JSON.');
    }
  } finally {
    if (is_file($vTemporaryPath)) {
      unlink($vTemporaryPath);
    }
  }
}

function fVideoFfprobeBin() {
  if (!function_exists('shell_exec')) {
    return '';
  }

  $vBin = trim((string) shell_exec('command -v ffprobe 2>/dev/null'));
  return $vBin !== '' ? $vBin : '';
}

function fVideoFfmpegBin() {
  if (!function_exists('shell_exec')) {
    return '';
  }

  $vBin = trim((string) shell_exec('command -v ffmpeg 2>/dev/null'));
  return $vBin !== '' ? $vBin : '';
}

function fVideoTimeoutBin() {
  if (!function_exists('shell_exec')) {
    return '';
  }

  $vBin = trim((string) shell_exec('command -v timeout 2>/dev/null'));
  return $vBin !== '' ? $vBin : '';
}

function fVideoThumbnailSecond($pDuration) {
  $vDuration = max(0, (int) $pDuration);
  if ($vDuration <= 2) {
    return '0.000';
  }

  $vSecond = min(10, max(1, (int) floor($vDuration * 0.1)));
  $vSecond = min($vSecond, max(1, $vDuration - 1));
  return number_format($vSecond, 3, '.', '');
}

function fVideoGenerateThumbnail($pVideoPath, $pDuration = 0) {
  $vBin = fVideoFfmpegBin();
  if ($vBin === '' || !function_exists('shell_exec') || !is_file($pVideoPath) || !is_readable($pVideoPath)) {
    return false;
  }

  $vThumbnailPath = fVideoThumbnailPath($pVideoPath);
  $vTemporaryPath = $vThumbnailPath . '.tmp.' . getmypid() . '.jpg';
  if (is_file($vTemporaryPath) && !unlink($vTemporaryPath)) {
    return false;
  }

  $vSecond = fVideoThumbnailSecond($pDuration);
  $vTimeoutBin = fVideoTimeoutBin();
  $vCommand = ($vTimeoutBin !== '' ? escapeshellarg($vTimeoutBin) . ' --signal=KILL --kill-after=5s 60s ' : '')
    . escapeshellarg($vBin)
    . ' -y -v error -ss ' . escapeshellarg($vSecond)
    . ' -i ' . escapeshellarg($pVideoPath)
    . ' -frames:v 1 -an -vf ' . escapeshellarg('scale=640:-2')
    . ' -q:v 4 '
    . escapeshellarg($vTemporaryPath)
    . ' 2>&1';
  shell_exec($vCommand);

  if (!is_file($vTemporaryPath) || filesize($vTemporaryPath) <= 0) {
    if (is_file($vTemporaryPath)) {
      unlink($vTemporaryPath);
    }
    return false;
  }

  if (!chmod($vTemporaryPath, 0644) || !rename($vTemporaryPath, $vThumbnailPath)) {
    if (is_file($vTemporaryPath)) {
      unlink($vTemporaryPath);
    }
    return false;
  }

  return true;
}

function fVideoEnsureThumbnail($pVideoPath, $pDuration = 0) {
  $vThumbnailPath = fVideoThumbnailPath($pVideoPath);
  if (is_file($vThumbnailPath) && filesize($vThumbnailPath) > 0) {
    $vVideoMtime = is_file($pVideoPath) ? filemtime($pVideoPath) : 0;
    $vThumbnailMtime = filemtime($vThumbnailPath);
    if ($vThumbnailMtime !== false && $vVideoMtime !== false && $vThumbnailMtime >= $vVideoMtime) {
      return true;
    }
  }

  return fVideoGenerateThumbnail($pVideoPath, $pDuration);
}

function fVideoFfprobe($pVideoPath) {
  $vBin = fVideoFfprobeBin();
  if ($vBin === '' || !function_exists('shell_exec')) {
    return [];
  }

  $vCommand = escapeshellarg($vBin)
    . ' -v quiet -print_format json -show_format -show_streams '
    . escapeshellarg($pVideoPath);
  $vTimeoutBin = fVideoTimeoutBin();
  if ($vTimeoutBin !== '') {
    $vCommand = escapeshellarg($vTimeoutBin) . ' --signal=KILL --kill-after=5s 30s ' . $vCommand;
  }
  $vOutput = shell_exec($vCommand);
  if (!is_string($vOutput) || trim($vOutput) === '') {
    return [];
  }

  $dProbe = json_decode($vOutput, true);
  return is_array($dProbe) ? $dProbe : [];
}

function fVideoMetadataFromFile($pVideoPath) {
  $dProbe = fVideoFfprobe($pVideoPath);
  $dFormat = $dProbe['format'] ?? [];
  $ldStreams = $dProbe['streams'] ?? [];
  $dVideoStream = [];
  $dAudioStream = [];

  foreach ($ldStreams as $dStream) {
    if (($dStream['codec_type'] ?? '') === 'video' && empty($dVideoStream)) {
      $dVideoStream = $dStream;
    }
    if (($dStream['codec_type'] ?? '') === 'audio' && empty($dAudioStream)) {
      $dAudioStream = $dStream;
    }
  }

  $vDuration = (float) ($dFormat['duration'] ?? ($dVideoStream['duration'] ?? 0));

  return [
    'duration_sec' => max(0, (int) round($vDuration)),
    'size_bytes' => is_file($pVideoPath) ? filesize($pVideoPath) : 0,
    'is_symlink' => is_link($pVideoPath),
    'symlink_target' => is_link($pVideoPath) ? (readlink($pVideoPath) ?: '') : '',
    'mime' => fVideoMimeFromPath($pVideoPath),
    'sha256' => is_file($pVideoPath) ? hash_file('sha256', $pVideoPath) : '',
    'codec' => [
      'video' => $dVideoStream['codec_name'] ?? '',
      'audio' => $dAudioStream['codec_name'] ?? '',
    ],
    'video' => [
      'width' => (int) ($dVideoStream['width'] ?? 0),
      'height' => (int) ($dVideoStream['height'] ?? 0),
      'bit_rate' => (int) ($dVideoStream['bit_rate'] ?? 0),
      'frame_rate' => $dVideoStream['r_frame_rate'] ?? '',
    ],
    'audio' => [
      'channels' => (int) ($dAudioStream['channels'] ?? 0),
      'sample_rate' => (int) ($dAudioStream['sample_rate'] ?? 0),
      'bit_rate' => (int) ($dAudioStream['bit_rate'] ?? 0),
    ],
  ];
}

function fVideoValidateFile($pVideoPath) {
  if (!is_file($pVideoPath) || !is_readable($pVideoPath)) {
    throw new RuntimeException(function_exists('fT') ? fT('toast.invalid_upload_file') : 'The uploaded file is invalid.');
  }

  $vSize = filesize($pVideoPath);
  if ($vSize === false || $vSize <= 0) {
    throw new RuntimeException(function_exists('fT') ? fT('toast.empty_video_file') : 'The video file is empty.');
  }
  if ($vSize > cMaximumVideoBytes) {
    throw new RuntimeException(function_exists('fT') ? fT('toast.video_too_large') : 'The video file is too large.');
  }
  if (fVideoFfprobeBin() === '') {
    throw new RuntimeException(function_exists('fT') ? fT('toast.ffprobe_required') : 'ffprobe is required to validate video files.');
  }

  $dFileInfo = fVideoMetadataFromFile($pVideoPath);
  if (
    trim((string) ($dFileInfo['codec']['video'] ?? '')) === ''
    || (int) ($dFileInfo['video']['width'] ?? 0) <= 0
    || (int) ($dFileInfo['video']['height'] ?? 0) <= 0
  ) {
    throw new RuntimeException(function_exists('fT') ? fT('toast.invalid_video_content') : 'The file does not contain a valid video stream.');
  }

  return $dFileInfo;
}

function fVideoCreateMetadata($pVideoPath, $pData = [], $pFileInfo = null) {
  $vFile = basename($pVideoPath);
  $vBase = pathinfo($vFile, PATHINFO_FILENAME);
  $dFileInfo = is_array($pFileInfo) ? $pFileInfo : fVideoMetadataFromFile($pVideoPath);
  $vNow = time();

  $dMeta = [
    'id' => mb_strcut(fVideoMetadataText($pData['id'] ?? $vBase, $vBase), 0, 180, 'UTF-8'),
    'title' => mb_substr(trim(fVideoMetadataText($pData['title'] ?? $vBase, $vBase)), 0, 180),
    'description' => mb_substr(trim(fVideoMetadataText($pData['description'] ?? '')), 0, 3000),
    'category' => mb_substr(trim(fVideoMetadataText($pData['category'] ?? 'Uploads', 'Uploads')), 0, 80),
    'channel_id' => mb_substr(trim(fVideoMetadataText($pData['channel_id'] ?? 'uploads', 'uploads')), 0, 120),
    'filename' => $vFile,
    'file' => $vFile,
    'duration_sec' => (int) fVideoMetadataText($pData['duration_sec'] ?? $dFileInfo['duration_sec'], $dFileInfo['duration_sec']),
    'codec' => $dFileInfo['codec'],
    'video' => $dFileInfo['video'],
    'audio' => $dFileInfo['audio'],
    'mime' => $dFileInfo['mime'],
    'size_bytes' => $dFileInfo['size_bytes'],
    'is_symlink' => $dFileInfo['is_symlink'],
    'symlink_target' => $dFileInfo['symlink_target'],
    'sha256' => $dFileInfo['sha256'],
    'transcript' => fVideoMetadataText($pData['transcript'] ?? ''),
    'created_at' => (int) fVideoMetadataText($pData['created_at'] ?? $vNow, $vNow),
    'updated_at' => $vNow,
    'uploader_user_id' => fUserId(),
    'uploader_email' => fUserEmail(),
  ];

  if ($dMeta['title'] === '') {
    $dMeta['title'] = $vBase;
  }
  if ($dMeta['category'] === '') {
    $dMeta['category'] = 'Uploads';
  }

  return $dMeta;
}

function fVideoEnsureUploadsChannel() {
  $dChannel = fOne('SELECT id FROM channels WHERE id = ?', ['uploads']);
  if (!$dChannel) {
    fQuery(
      'INSERT OR IGNORE INTO channels (id, name, handle, subs, color, verified) VALUES (?, ?, ?, ?, ?, ?)',
      ['uploads', 'MyOwnTube Uploads', '@mytubeuploads', 0, '#e85d3d', 1]
    );
  }
}

function fVideoEnsureCategory($pCategory) {
  $vCategory = mb_substr(trim((string) $pCategory), 0, 80);
  if ($vCategory === '') {
    return;
  }

  $dCategory = fOne('SELECT name FROM categories WHERE name = ?', [$vCategory]);
  if (!$dCategory) {
    $vDisplayOrder = (int) fScalar('SELECT COALESCE(MAX(display_order), 0) + 1 FROM categories');
    fQuery(
      'INSERT OR IGNORE INTO categories (name, kind, display_order) VALUES (?, ?, ?)',
      [$vCategory, 'cat', $vDisplayOrder]
    );
  }
}

function fVideoImportJson($pVideoPath, $pJsonPath, $pFileInfo = null) {
  $dJson = fVideoReadJson($pJsonPath);
  if (!$dJson) {
    return false;
  }

  fVideoEnsureUploadsChannel();

  $vFile = basename($pVideoPath);
  $vBase = pathinfo($vFile, PATHINFO_FILENAME);
  $dFileInfo = is_array($pFileInfo) ? $pFileInfo : fVideoMetadataFromFile($pVideoPath);
  $vId = mb_strcut(trim(fVideoMetadataText($dJson['id'] ?? $vBase, $vBase)), 0, 180, 'UTF-8');
  $vId = preg_replace('/[\x00-\x1F\x7F]/', '', str_replace(['/', '\\'], '-', $vId));
  $vTitle = mb_substr(trim(fVideoMetadataText($dJson['title'] ?? $vBase, $vBase)), 0, 180);
  $vDescription = mb_substr(trim(fVideoMetadataText($dJson['description'] ?? '')), 0, 3000);
  $vCategory = mb_substr(trim(fVideoMetadataText($dJson['category'] ?? 'Uploads', 'Uploads')), 0, 80);
  $vChannelId = mb_substr(trim(fVideoMetadataText($dJson['channel_id'] ?? 'uploads', 'uploads')), 0, 120);
  $vDuration = (int) fVideoMetadataText($dJson['duration_sec'] ?? 0, 0);
  if ($vDuration <= 0) {
    $vDuration = (int) $dFileInfo['duration_sec'];
  }
  $vPublished = (int) fVideoMetadataText(
    $dJson['published_at'] ?? ($dJson['created_at'] ?? filemtime($pVideoPath)),
    filemtime($pVideoPath) ?: time()
  );
  $vVariant = max(1, (abs(crc32($vId)) % 8) + 1);
  $vFilePath = 'videos/' . $vFile;

  if ($vId === '') {
    $vId = $vBase;
  }
  if ($vTitle === '') {
    $vTitle = $vBase;
  }
  if ($vCategory === '') {
    $vCategory = 'Uploads';
  }
  if ($vChannelId === '') {
    $vChannelId = 'uploads';
  }
  fVideoEnsureCategory($vCategory);

  $dJson['id'] = $vId;
  $dJson['title'] = $vTitle;
  $dJson['description'] = $vDescription;
  $dJson['category'] = $vCategory;
  $dJson['channel_id'] = $vChannelId;
  $dJson['filename'] = $vFile;
  $dJson['file'] = $vFile;
  $dJson['duration_sec'] = $vDuration;
  $dJson['mime'] = $dFileInfo['mime'];
  $dJson['size_bytes'] = $dFileInfo['size_bytes'];
  $dJson['is_symlink'] = $dFileInfo['is_symlink'];
  $dJson['symlink_target'] = $dFileInfo['symlink_target'];
  $dJson['sha256'] = $dFileInfo['sha256'];
  $dJson['transcript'] = fVideoMetadataText($dJson['transcript'] ?? '');
  $dJson['codec'] = $dFileInfo['codec'];
  $dJson['video'] = $dFileInfo['video'];
  $dJson['audio'] = $dFileInfo['audio'];
  $vThumbnailPath = fVideoThumbnailPath($pVideoPath);
  if (fVideoEnsureThumbnail($pVideoPath, $vDuration)) {
    $dJson['thumbnail'] = basename($vThumbnailPath);
    $dJson['thumbnail_mime'] = 'image/jpeg';
    $dJson['thumbnail_updated_at'] = filemtime($vThumbnailPath) ?: time();
  } else {
    unset($dJson['thumbnail'], $dJson['thumbnail_mime'], $dJson['thumbnail_updated_at']);
  }
  $dJson['updated_at'] = time();

  $dChannel = fOne('SELECT id FROM channels WHERE id = ?', [$vChannelId]);
  if (!$dChannel) {
    $vChannelId = 'uploads';
    $dJson['channel_id'] = $vChannelId;
  }
  fVideoSaveJson($pJsonPath, $dJson);

  $dExisting = fOne('SELECT id FROM videos WHERE id = ?', [$vId]);
  if ($dExisting) {
    fQuery(
      'UPDATE videos SET title = ?, description = ?, channel_id = ?, category = ?, duration_sec = ?, file_path = ?, is_available = 1 WHERE id = ?',
      [$vTitle, $vDescription, $vChannelId, $vCategory, $vDuration, $vFilePath, $vId]
    );
  } else {
    fQuery(
      'INSERT INTO videos (id, title, description, channel_id, category, views, duration_sec, published_at, thumb_variant, is_available, file_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
      [$vId, $vTitle, $vDescription, $vChannelId, $vCategory, 0, $vDuration, $vPublished, $vVariant, 1, $vFilePath]
    );
  }

  return $vId;
}

function fVideosSyncFolder() {
  $vBase = fVideoRealBasePath();
  if (!is_readable($vBase)) {
    throw new RuntimeException(function_exists('fT') ? fT('toast.operation_failed') : 'The video directory is not readable.');
  }
  if (fVideoFfprobeBin() === '') {
    throw new RuntimeException(function_exists('fT') ? fT('toast.ffprobe_required') : 'ffprobe is required to validate video files.');
  }
  $ldTrackedVideos = fAll('SELECT id FROM videos WHERE file_path IS NOT NULL');
  $dTrackedVideoIds = [];
  foreach ($ldTrackedVideos as $dTrackedVideo) {
    $dTrackedVideoIds[(string) $dTrackedVideo['id']] = true;
  }
  $aVideos = glob($vBase . '/*') ?: [];

  $vImported = 0;
  $vGenerated = 0;
  $vErrors = 0;
  $dAvailableVideoIds = [];

  foreach ($aVideos as $vVideoPath) {
    if (!is_file($vVideoPath)) {
      continue;
    }

    $vExtension = mb_strtolower(pathinfo($vVideoPath, PATHINFO_EXTENSION));
    if (!in_array($vExtension, fVideoAllowedExtensions(), true)) {
      continue;
    }

    try {
      $dFileInfo = fVideoValidateFile($vVideoPath);
    } catch (Throwable $vE) {
      error_log('Video sync validation failed for ' . basename($vVideoPath) . ': ' . $vE->getMessage());
      $vErrors++;
      continue;
    }

    $vJsonPath = fVideoJsonPath($vVideoPath);
    if (!is_file($vJsonPath)) {
      try {
        $vVideoId = pathinfo($vVideoPath, PATHINFO_FILENAME);
        $dExistingVideo = fOne(
          'SELECT id, title, description, category, channel_id, published_at FROM videos WHERE id = ?',
          [$vVideoId]
        );
        $dMetadataData = [
          'id' => $vVideoId,
          'title' => $dExistingVideo['title'] ?? $vVideoId,
          'description' => $dExistingVideo['description'] ?? '',
          'category' => $dExistingVideo['category'] ?? 'Uploads',
          'channel_id' => $dExistingVideo['channel_id'] ?? 'uploads',
          'transcript' => '',
          'created_at' => (int) ($dExistingVideo['published_at'] ?? time()),
        ];
        $dMeta = fVideoCreateMetadata($vVideoPath, $dMetadataData, $dFileInfo);
        fVideoSaveJson($vJsonPath, $dMeta);
        $vGenerated++;
      } catch (Throwable $vE) {
        error_log('Video sync metadata generation failed for ' . basename($vVideoPath) . ': ' . $vE->getMessage());
        $vErrors++;
        continue;
      }
    }

    try {
      $vImportedVideoId = fVideoImportJson($vVideoPath, $vJsonPath, $dFileInfo);
      if ($vImportedVideoId !== false) {
        $dAvailableVideoIds[(string) $vImportedVideoId] = true;
        $vImported++;
      } else {
        error_log('Video sync metadata import failed for ' . basename($vVideoPath) . ': invalid JSON metadata.');
        $vErrors++;
      }
    } catch (Throwable $vE) {
      error_log('Video sync metadata import failed for ' . basename($vVideoPath) . ': ' . $vE->getMessage());
      $vErrors++;
    }
  }

  $vDb = fDb();
  fQuery('BEGIN IMMEDIATE');
  try {
    foreach (array_keys($dTrackedVideoIds) as $vTrackedVideoId) {
      if (!isset($dAvailableVideoIds[$vTrackedVideoId])) {
        fQuery('UPDATE videos SET is_available = 0 WHERE id = ?', [$vTrackedVideoId]);
      }
    }
    $vDb->commit();
  } finally {
    if ($vDb->inTransaction()) {
      $vDb->rollBack();
    }
  }

  return [
    'imported' => $vImported,
    'generated' => $vGenerated,
    'errors' => $vErrors,
  ];
}

function fChannelById($pChannelId) {
  static $dCache = [];
  if (isset($dCache[$pChannelId])) return $dCache[$pChannelId];
  $dCache[$pChannelId] = fOne('SELECT * FROM channels WHERE id = ?', [$pChannelId]);
  return $dCache[$pChannelId];
}

function fIdSet($pTable) {
  static $dCache = [];
  $aAllowedTables = ['likes', 'watch_later'];
  if (!is_string($pTable) || !in_array($pTable, $aAllowedTables, true)) {
    throw new InvalidArgumentException('Unsupported identifier table.');
  }
  $vUserId = fUserId();
  $vKey = $pTable . ':' . $vUserId;
  if (isset($dCache[$vKey])) return $dCache[$vKey];
  $ldRows = fAll('SELECT video_id FROM ' . $pTable . ' WHERE user_id = ?', [$vUserId]);
  $dCache[$vKey] = array_flip(array_column($ldRows, 'video_id'));
  return $dCache[$vKey];
}

