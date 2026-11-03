<?php

if (PHP_SAPI !== 'cli') {
  http_response_code(404);
  exit;
}

if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
  fwrite(STDERR, 'Run this script as the Apache service user: www-data on Debian, or apache on Alpine.' . PHP_EOL);
  exit(1);
}

if (function_exists('set_time_limit')) {
  set_time_limit(0);
}

$vSessionPath = sys_get_temp_dir();
if (is_dir($vSessionPath) && is_writable($vSessionPath)) {
  ini_set('session.save_path', $vSessionPath);
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/session.php';
require_once __DIR__ . '/inc/helpers.php';
require_once __DIR__ . '/inc/i18n.php';
require_once __DIR__ . '/inc/videos.php';

$vStart = microtime(true);

try {
  fAuthEnsureSchema();
  $dResult = fVideosSyncFolder();
  $vSeconds = max(0, microtime(true) - $vStart);

  echo 'Sync completed: '
    . (int) $dResult['imported'] . ' imported, '
    . (int) $dResult['generated'] . ' JSON files generated, '
    . (int) $dResult['errors'] . ' errors, '
    . number_format($vSeconds, 2, '.', '') . ' seconds.'
    . PHP_EOL;
  exit(0);
} catch (Throwable $vE) {
  fwrite(STDERR, 'Error: ' . $vE->getMessage() . PHP_EOL);
  exit(1);
}
