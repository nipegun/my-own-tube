<?php

if (PHP_SAPI !== 'cli') {
  http_response_code(404);
  exit(1);
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/session.php';
require_once __DIR__ . '/inc/helpers.php';
require_once __DIR__ . '/inc/i18n.php';

if (!file_exists(cDbPath)) {
  fwrite(STDERR, 'The database does not exist.' . PHP_EOL);
  exit(1);
}

$vEmail = $argv[1] ?? cAdminEmail;
$vEmail = is_string($vEmail) ? trim($vEmail) : cAdminEmail;
$vEmail = fAuthNormalizeEmail($vEmail);
$vAdminPassword = getenv(cAdminPasswordEnvironment);
$vAdminPassword = is_string($vAdminPassword) ? $vAdminPassword : '';
if ($vAdminPassword === '') {
  $vAdminPassword = rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
}
if (mb_strlen($vAdminPassword) < cMinimumPasswordLength || mb_strlen($vAdminPassword) > cMaximumPasswordLength) {
  fwrite(STDERR, cAdminPasswordEnvironment . ' must contain between ' . cMinimumPasswordLength . ' and ' . cMaximumPasswordLength . ' characters.' . PHP_EOL);
  exit(1);
}

try {
  fAuthEnsureSchema();
  $dAdmin = fOne('SELECT id, email FROM users WHERE email = ?', [$vEmail]);
} catch (Throwable $vE) {
  fwrite(STDERR, 'Could not prepare the database schema: ' . $vE->getMessage() . PHP_EOL);
  exit(1);
}

if (!$dAdmin) {
  fwrite(STDERR, 'The requested account does not exist: ' . $vEmail . PHP_EOL);
  exit(1);
}

$vHash = password_hash($vAdminPassword, cPasswordAlgorithm);
if (!is_string($vHash) || $vHash === '') {
  fwrite(STDERR, 'Could not hash the administrator password.' . PHP_EOL);
  exit(1);
}

try {
  fQuery(
    'UPDATE users SET password_hash = ?, role = ?, session_version = session_version + 1 WHERE id = ?',
    [$vHash, 'admin', (int) $dAdmin['id']]
  );
  echo 'Administrator password reset.' . PHP_EOL;
  echo 'Administrator email: ' . $vEmail . PHP_EOL;
  echo 'Administrator password: ' . $vAdminPassword . PHP_EOL;
  echo 'Store this password now; it is not written to disk in plain text.' . PHP_EOL;
  fQuery('DELETE FROM login_attempts');
  echo 'Database schema is ready and login throttles were cleared.' . PHP_EOL;
} catch (Throwable $vE) {
  fwrite(STDERR, 'Could not reset the administrator password: ' . $vE->getMessage() . PHP_EOL);
  exit(1);
}
