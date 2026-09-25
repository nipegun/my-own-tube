<?php

if (PHP_SAPI !== 'cli') {
  header_remove('X-Powered-By');
}

if (session_status() === PHP_SESSION_NONE) {
  if (PHP_SAPI === 'cli') {
    $_SESSION = [];
  } else {
    $vSessionSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
      || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    session_name('MYTUBESESSID');
    session_set_cookie_params([
      'lifetime' => 0,
      'path' => '/',
      'domain' => '',
      'secure' => $vSessionSecure,
      'httponly' => true,
      'samesite' => 'Strict',
    ]);

    if (!session_start()) {
      http_response_code(500);
      echo 'Could not start the session.';
      exit(1);
    }
  }
}

if (!isset($_SESSION['aToasts'])) {
  $_SESSION['aToasts'] = [];
}

function fToastSave($pMessage) {
  $_SESSION['aToasts'][] = $pMessage;
}

function fToastConsume() {
  $aMessages = $_SESSION['aToasts'] ?? [];
  $_SESSION['aToasts'] = [];
  return $aMessages;
}

function fCsrfToken() {
  $vToken = $_SESSION['vCsrfToken'] ?? '';
  if (!is_string($vToken) || strlen($vToken) !== 64) {
    $vToken = bin2hex(random_bytes(32));
    $_SESSION['vCsrfToken'] = $vToken;
  }
  return $vToken;
}

function fCsrfField() {
  return '<input type="hidden" name="csrf_token" value="'
    . htmlspecialchars(fCsrfToken(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . '">';
}

function fCsrfValid() {
  $vExpectedToken = fCsrfToken();
  $vSubmittedToken = $_POST['csrf_token'] ?? '';
  return is_string($vSubmittedToken) && hash_equals($vExpectedToken, $vSubmittedToken);
}

function fCsrfRequire() {
  if (!fCsrfValid()) {
    http_response_code(403);
    echo htmlspecialchars(
      function_exists('fT') ? fT('error.invalid_request') : 'The request could not be validated.',
      ENT_QUOTES | ENT_SUBSTITUTE,
      'UTF-8'
    );
    exit(1);
  }
}

function fRedirect($pDestination) {
  $vDestination = str_replace(["\r", "\n"], '', (string) $pDestination);
  $vStatus = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' ? 303 : 302;
  header('Location: ' . $vDestination, true, $vStatus);
  exit;
}

function fBack($pDefaultDestination = 'index.php') {
  $vReferer = $_SERVER['HTTP_REFERER'] ?? $pDefaultDestination;
  $dReferer = is_string($vReferer) ? parse_url($vReferer) : false;
  $vRequestHost = parse_url('http://' . (string) ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST);
  $vRequestHost = is_string($vRequestHost) ? mb_strtolower($vRequestHost) : '';

  if (!is_array($dReferer)) {
    fRedirect($pDefaultDestination);
  }

  $vRefererHost = mb_strtolower((string) ($dReferer['host'] ?? ''));
  if ($vRefererHost !== '' && $vRefererHost !== $vRequestHost) {
    fRedirect($pDefaultDestination);
  }

  $vPath = (string) ($dReferer['path'] ?? '');
  if ($vPath === '' || substr($vPath, 0, 1) !== '/') {
    fRedirect($pDefaultDestination);
  }

  $vDestination = $vPath;
  if (!empty($dReferer['query'])) {
    $vDestination .= '?' . $dReferer['query'];
  }
  fRedirect($vDestination);
}

function fAuthEnsureSchema() {
  static $vReady = false;

  if ($vReady) {
    return;
  }

  $vDb = fDb();
  $vDb->exec(
    "CREATE TABLE IF NOT EXISTS users (
      id              INTEGER PRIMARY KEY AUTOINCREMENT,
      email           TEXT NOT NULL UNIQUE,
      password_hash   TEXT NOT NULL,
      name            TEXT NOT NULL,
      handle          TEXT NOT NULL,
      initial         TEXT NOT NULL,
      role            TEXT NOT NULL DEFAULT 'user',
      color           TEXT NOT NULL DEFAULT '#e85d3d',
      locale          TEXT NOT NULL DEFAULT 'en-us',
      session_version INTEGER NOT NULL DEFAULT 1,
      created_at      INTEGER NOT NULL
    )"
  );
  fAuthEnsureMigrations();
  fAuthEnsureSettings();
  fAuthEnsureUserLocales();
  fAuthEnsureUserSessionVersions();
  fAuthEnsureVideoAvailability();

  $vAdminId = fAuthEnsureAdmin();
  if ($vAdminId > 0) {
    fAuthMigrateUserTable('likes', $vAdminId);
    fAuthMigrateUserTable('watch_later', $vAdminId);
    fAuthMigrateUserTable('history', $vAdminId);
    fAuthEnsureComments($vAdminId);
    fAuthEnsurePlaylists($vAdminId);
  }
  fAuthEnsureSearchHistory();
  fAuthEnsureLoginAttempts();
  fAuthEnsureIndexes();
  fAuthEnsureInitialPrivateDataEmpty($vAdminId);
  fAuthEnsureInitialMetricsEmpty($vAdminId);

  $vReady = true;
}

function fAuthEnsureMigrations() {
  fDb()->exec(
    "CREATE TABLE IF NOT EXISTS schema_migrations (
      name            TEXT PRIMARY KEY,
      applied_at      INTEGER NOT NULL
    )"
  );
}

function fAuthEnsureSettings() {
  fDb()->exec(
    "CREATE TABLE IF NOT EXISTS app_settings (
      name            TEXT PRIMARY KEY,
      value           TEXT NOT NULL,
      updated_at      INTEGER NOT NULL
    )"
  );

  $dDefaultLocale = fOne('SELECT name FROM app_settings WHERE name = ?', ['default_locale']);
  if (!$dDefaultLocale) {
    fQuery(
      'INSERT INTO app_settings (name, value, updated_at) VALUES (?, ?, ?)',
      ['default_locale', cDefaultLocale, time()]
    );
  }

  $dAllowRegistration = fOne('SELECT name FROM app_settings WHERE name = ?', ['allow_registration']);
  if (!$dAllowRegistration) {
    fQuery(
      'INSERT INTO app_settings (name, value, updated_at) VALUES (?, ?, ?)',
      ['allow_registration', '0', time()]
    );
  }
}

function fAuthEnsureUserLocales() {
  if (!fAuthColumnExists('users', 'locale')) {
    fDb()->exec("ALTER TABLE users ADD COLUMN locale TEXT NOT NULL DEFAULT 'en-us'");
  }

  $vMigration = 'normalize_user_locales_20260721';
  if (!fAuthMigrationApplied($vMigration)) {
    fQuery(
      "UPDATE users SET locale = ? WHERE locale IS NULL OR locale = ''",
      [cDefaultLocale]
    );

    $aSupportedLocales = function_exists('fSupportedLocales') ? fSupportedLocales() : cSupportedLocales;
    $vPlaceholders = implode(',', array_fill(0, count($aSupportedLocales), '?'));
    fQuery(
      'UPDATE users SET locale = ? WHERE locale NOT IN (' . $vPlaceholders . ')',
      array_merge([cDefaultLocale], $aSupportedLocales)
    );
    fAuthRegisterMigration($vMigration);
  }
}

function fAuthEnsureUserSessionVersions() {
  if (!fAuthColumnExists('users', 'session_version')) {
    fDb()->exec('ALTER TABLE users ADD COLUMN session_version INTEGER NOT NULL DEFAULT 1');
  }
}

function fAuthEnsureVideoAvailability() {
  if (fAuthTableExists('videos') && !fAuthColumnExists('videos', 'is_available')) {
    fDb()->exec('ALTER TABLE videos ADD COLUMN is_available INTEGER NOT NULL DEFAULT 1');
  }
  $vMigration = 'hide_unbacked_videos_20260925';
  if (!fAuthMigrationApplied($vMigration) && fAuthTableExists('videos') && fAuthColumnExists('videos', 'file_path')) {
    fQuery("UPDATE videos SET is_available = 0 WHERE file_path IS NULL OR trim(file_path) = ''");
    fAuthRegisterMigration($vMigration);
  }
}

function fAuthEnsureAdmin() {
  $dAdmin = fOne(
    'SELECT id FROM users WHERE role = ? ORDER BY CASE WHEN email = ? THEN 0 ELSE 1 END, created_at LIMIT 1',
    ['admin', cAdminEmail]
  );
  return $dAdmin ? (int) $dAdmin['id'] : 0;
}

function fAuthMigrationApplied($pName) {
  $dMigration = fOne('SELECT name FROM schema_migrations WHERE name = ?', [$pName]);
  return (bool) $dMigration;
}

function fAuthRegisterMigration($pName) {
  fQuery(
    'INSERT OR IGNORE INTO schema_migrations (name, applied_at) VALUES (?, ?)',
    [$pName, time()]
  );
}

function fAuthTableExists($pTable) {
  $dTable = fOne(
    "SELECT name FROM sqlite_master WHERE type = 'table' AND name = ?",
    [$pTable]
  );
  return (bool) $dTable;
}

function fAuthColumnExists($pTable, $pColumn) {
  $vTable = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $pTable);
  if ($vTable === '') {
    return false;
  }

  $ldColumns = fAll('PRAGMA table_info(' . $vTable . ')');
  foreach ($ldColumns as $dColumn) {
    if (($dColumn['name'] ?? '') === $pColumn) {
      return true;
    }
  }
  return false;
}

function fAuthMigrateUserTable($pTable, $pAdminId) {
  if (!fAuthTableExists($pTable) || fAuthColumnExists($pTable, 'user_id')) {
    return;
  }

  $vLegacy = $pTable . '_legacy_auth_' . time();
  $vCreateSql = '';
  $vCopySql = '';

  if ($pTable === 'likes') {
    $vCreateSql = "CREATE TABLE likes (
      user_id         INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
      video_id        TEXT NOT NULL REFERENCES videos(id),
      liked_at        INTEGER NOT NULL,
      PRIMARY KEY (user_id, video_id)
    )";
    $vCopySql = 'INSERT OR IGNORE INTO likes (user_id, video_id, liked_at) SELECT ?, video_id, liked_at FROM ' . $vLegacy;
  }

  if ($pTable === 'watch_later') {
    $vCreateSql = "CREATE TABLE watch_later (
      user_id         INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
      video_id        TEXT NOT NULL REFERENCES videos(id),
      added_at        INTEGER NOT NULL,
      PRIMARY KEY (user_id, video_id)
    )";
    $vCopySql = 'INSERT OR IGNORE INTO watch_later (user_id, video_id, added_at) SELECT ?, video_id, added_at FROM ' . $vLegacy;
  }

  if ($pTable === 'history') {
    $vCreateSql = "CREATE TABLE history (
      user_id         INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
      video_id        TEXT NOT NULL REFERENCES videos(id),
      progress        INTEGER NOT NULL DEFAULT 0,
      watched_at      INTEGER NOT NULL,
      PRIMARY KEY (user_id, video_id)
    )";
    $vCopySql = 'INSERT OR IGNORE INTO history (user_id, video_id, progress, watched_at) SELECT ?, video_id, progress, watched_at FROM ' . $vLegacy;
  }

  if ($vCreateSql === '' || $vCopySql === '') {
    return;
  }

  $vDb = fDb();
  $vDb->exec('PRAGMA foreign_keys = OFF');
  try {
    $vDb->beginTransaction();
    $vDb->exec('ALTER TABLE ' . $pTable . ' RENAME TO ' . $vLegacy);
    $vDb->exec($vCreateSql);
    $vStmt = $vDb->prepare($vCopySql);
    $vStmt->execute([(int) $pAdminId]);
    $vDb->exec('DROP TABLE ' . $vLegacy);
    $vDb->commit();
  } catch (Throwable $vE) {
    if ($vDb->inTransaction()) {
      $vDb->rollBack();
    }
    throw $vE;
  } finally {
    $vDb->exec('PRAGMA foreign_keys = ON');
  }
}

function fAuthEnsureComments($pAdminId) {
  if (!fAuthTableExists('comments')) {
    return;
  }

  if (!fAuthColumnExists('comments', 'user_id')) {
    $vDb = fDb();
    $vDb->beginTransaction();
    try {
      $vDb->exec('ALTER TABLE comments ADD COLUMN user_id INTEGER REFERENCES users(id) ON DELETE SET NULL');
      fQuery('UPDATE comments SET user_id = ? WHERE user_id IS NULL', [(int) $pAdminId]);
      $vDb->commit();
    } finally {
      if ($vDb->inTransaction()) {
        $vDb->rollBack();
      }
    }
  }
}

function fAuthEnsurePlaylists($pAdminId) {
  if (!fAuthTableExists('playlists')) {
    return;
  }

  if (!fAuthColumnExists('playlists', 'user_id')) {
    $vDb = fDb();
    $vDb->beginTransaction();
    try {
      $vDb->exec('ALTER TABLE playlists ADD COLUMN user_id INTEGER REFERENCES users(id) ON DELETE CASCADE');
      fQuery('UPDATE playlists SET user_id = ? WHERE user_id IS NULL', [(int) $pAdminId]);
      $vDb->commit();
    } finally {
      if ($vDb->inTransaction()) {
        $vDb->rollBack();
      }
    }
  }
}

function fAuthEnsureSearchHistory() {
  fDb()->exec(
    "CREATE TABLE IF NOT EXISTS search_history (
      user_id         INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
      text            TEXT NOT NULL,
      searched_at     INTEGER NOT NULL,
      PRIMARY KEY (user_id, text)
    )"
  );
}

function fAuthEnsureLoginAttempts() {
  fDb()->exec(
    "CREATE TABLE IF NOT EXISTS login_attempts (
      attempt_key      TEXT PRIMARY KEY,
      attempts         INTEGER NOT NULL,
      first_attempt_at INTEGER NOT NULL,
      locked_until     INTEGER NOT NULL DEFAULT 0
    )"
  );
}

function fAuthEnsureIndexes() {
  if (fAuthTableExists('likes') && fAuthColumnExists('likes', 'user_id')) {
    fDb()->exec('CREATE INDEX IF NOT EXISTS idx_likes_user_liked ON likes(user_id, liked_at DESC)');
  }
  if (fAuthTableExists('watch_later') && fAuthColumnExists('watch_later', 'user_id')) {
    fDb()->exec('CREATE INDEX IF NOT EXISTS idx_watch_later_user_added ON watch_later(user_id, added_at DESC)');
  }
  if (fAuthTableExists('history') && fAuthColumnExists('history', 'user_id')) {
    fDb()->exec('CREATE INDEX IF NOT EXISTS idx_history_user_watched ON history(user_id, watched_at DESC)');
  }
  if (fAuthTableExists('comments') && fAuthColumnExists('comments', 'user_id')) {
    fDb()->exec('CREATE INDEX IF NOT EXISTS idx_comments_user ON comments(user_id, created_at DESC)');
  }
  if (fAuthTableExists('playlists') && fAuthColumnExists('playlists', 'user_id')) {
    fDb()->exec('CREATE INDEX IF NOT EXISTS idx_playlists_user_created ON playlists(user_id, created_at DESC)');
  }
  if (fAuthTableExists('search_history')) {
    fDb()->exec('CREATE INDEX IF NOT EXISTS idx_search_history_user_searched ON search_history(user_id, searched_at DESC)');
  }
  if (fAuthTableExists('login_attempts')) {
    fDb()->exec('CREATE INDEX IF NOT EXISTS idx_login_attempts_first ON login_attempts(first_attempt_at)');
  }
  if (fAuthTableExists('videos') && fAuthColumnExists('videos', 'is_available')) {
    fDb()->exec('CREATE INDEX IF NOT EXISTS idx_videos_available ON videos(is_available)');
  }
}

function fAuthEnsureInitialPrivateDataEmpty($pAdminId) {
  $vMigration = 'admin_private_defaults_empty_20260424';
  if (fAuthMigrationApplied($vMigration)) {
    return;
  }
  fAuthRegisterMigration($vMigration);
}

function fAuthEnsureInitialMetricsEmpty($pAdminId) {
  $vMigration = 'initial_usage_metrics_empty_20260424';
  if (fAuthMigrationApplied($vMigration)) {
    return;
  }
  fAuthRegisterMigration($vMigration);
}

function fRegistrationEnabled() {
  $dSetting = fOne('SELECT value FROM app_settings WHERE name = ?', ['allow_registration']);
  return $dSetting && (string) $dSetting['value'] === '1';
}

function fSetRegistrationEnabled($pEnabled) {
  $vValue = $pEnabled ? '1' : '0';
  fQuery(
    'INSERT INTO app_settings (name, value, updated_at) VALUES (?, ?, ?)
    ON CONFLICT(name) DO UPDATE SET value = excluded.value, updated_at = excluded.updated_at',
    ['allow_registration', $vValue, time()]
  );
}

function fAuthNormalizeEmail($pEmail) {
  return mb_strtolower(trim((string) $pEmail));
}

function fAuthCreateHandle($pEmail) {
  $aParts = explode('@', $pEmail);
  $vBase = $aParts[0] ?? 'user';
  $vBase = preg_replace('/[^a-z0-9._-]/', '', mb_strtolower($vBase));
  if ($vBase === '') {
    $vBase = 'user' . time();
  }
  return '@' . $vBase;
}

function fAuthCreateInitial($pName, $pEmail) {
  $vBase = trim((string) $pName);
  if ($vBase === '') {
    $vBase = trim((string) $pEmail);
  }
  return mb_strtoupper(mb_substr($vBase, 0, 1));
}

function fAuthColorByEmail($pEmail) {
  $aColors = ['#e85d3d', '#3b82f6', '#10b981', '#a855f7', '#f59e0b', '#ec4899', '#14b8a6'];
  $vIndex = abs(crc32((string) $pEmail)) % count($aColors);
  return $aColors[$vIndex];
}

function fAuthValidatePassword($pPassword) {
  $vPassword = (string) $pPassword;
  $vLength = mb_strlen($vPassword);

  if (strpos($vPassword, "\0") !== false) {
    throw new RuntimeException(
      function_exists('fT') ? fT('toast.operation_failed') : 'The operation could not be completed.'
    );
  }

  if ($vLength < cMinimumPasswordLength) {
    throw new RuntimeException(
      function_exists('fT')
        ? fT('toast.password_too_short', ['count' => cMinimumPasswordLength])
        : 'The password is too short.'
    );
  }
  if ($vLength > cMaximumPasswordLength) {
    throw new RuntimeException(
      function_exists('fT') ? fT('toast.password_too_long') : 'The password is too long.'
    );
  }

  return $vPassword;
}

function fAuthLoginAttemptKey() {
  $vRemoteAddress = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
  return hash('sha256', $vRemoteAddress);
}

function fAuthLoginAllowed() {
  $vAttemptKey = fAuthLoginAttemptKey();
  $vNow = time();
  $dAttempt = fOne('SELECT attempts, first_attempt_at, locked_until FROM login_attempts WHERE attempt_key = ?', [$vAttemptKey]);
  if (!$dAttempt) {
    return true;
  }
  if ((int) $dAttempt['locked_until'] > $vNow) {
    return false;
  }
  if ((int) $dAttempt['first_attempt_at'] + cLoginWindowSeconds <= $vNow) {
    fQuery('DELETE FROM login_attempts WHERE attempt_key = ?', [$vAttemptKey]);
  }
  return true;
}

function fAuthRecordLoginFailure() {
  $vAttemptKey = fAuthLoginAttemptKey();
  $vNow = time();
  $vDb = fDb();
  fQuery('BEGIN IMMEDIATE');
  try {
    $dAttempt = fOne('SELECT attempts, first_attempt_at FROM login_attempts WHERE attempt_key = ?', [$vAttemptKey]);
    if (!$dAttempt || (int) $dAttempt['first_attempt_at'] + cLoginWindowSeconds <= $vNow) {
      $vAttempts = 1;
      $vFirstAttemptAt = $vNow;
    } else {
      $vAttempts = (int) $dAttempt['attempts'] + 1;
      $vFirstAttemptAt = (int) $dAttempt['first_attempt_at'];
    }
    $vLockedUntil = $vAttempts >= cLoginMaximumAttempts ? $vNow + cLoginWindowSeconds : 0;
    fQuery(
      'INSERT INTO login_attempts (attempt_key, attempts, first_attempt_at, locked_until) VALUES (?, ?, ?, ?)
      ON CONFLICT(attempt_key) DO UPDATE SET attempts = excluded.attempts, first_attempt_at = excluded.first_attempt_at, locked_until = excluded.locked_until',
      [$vAttemptKey, $vAttempts, $vFirstAttemptAt, $vLockedUntil]
    );
    fQuery('DELETE FROM login_attempts WHERE first_attempt_at < ?', [$vNow - 86400]);
    $vDb->commit();
  } finally {
    if ($vDb->inTransaction()) {
      $vDb->rollBack();
    }
  }
}

function fAuthClearLoginFailures() {
  fQuery('DELETE FROM login_attempts WHERE attempt_key = ?', [fAuthLoginAttemptKey()]);
}

function fAuthCreateUser($pEmail, $pPassword, $pName, $pRole = 'user', $pLocale = null) {
  $vEmail = fAuthNormalizeEmail($pEmail);
  $vPassword = fAuthValidatePassword($pPassword);
  $vName = trim((string) $pName);
  $vRole = $pRole === 'admin' ? 'admin' : 'user';
  $vLocale = $pLocale !== null ? fNormalizeLocale($pLocale) : (function_exists('fSiteDefaultLocale') ? fSiteDefaultLocale() : cDefaultLocale);

  if (!filter_var($vEmail, FILTER_VALIDATE_EMAIL)) {
    throw new RuntimeException(function_exists('fT') ? fT('toast.invalid_email') : 'The email address is invalid.');
  }

  if (mb_strlen($vEmail) > 254) {
    throw new RuntimeException(function_exists('fT') ? fT('toast.invalid_email') : 'The email address is invalid.');
  }

  if ($vName === '') {
    $vName = $vEmail;
  }
  if (mb_strlen($vName) > 120) {
    $vName = mb_substr($vName, 0, 120);
  }

  $vHash = password_hash($vPassword, cPasswordAlgorithm);
  if (!is_string($vHash) || $vHash === '') {
    throw new RuntimeException(function_exists('fT') ? fT('toast.operation_failed') : 'The operation could not be completed.');
  }
  $vHandle = fAuthCreateHandle($vEmail);
  $vInitial = fAuthCreateInitial($vName, $vEmail);
  $vColor = fAuthColorByEmail($vEmail);

  try {
    $vStmt = fDb()->prepare(
      'INSERT INTO users (email, password_hash, name, handle, initial, role, color, locale, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $vStmt->execute([$vEmail, $vHash, $vName, $vHandle, $vInitial, $vRole, $vColor, $vLocale, time()]);
  } catch (PDOException $vE) {
    if (strpos($vE->getMessage(), 'UNIQUE constraint failed: users.email') !== false) {
      throw new RuntimeException(function_exists('fT') ? fT('toast.email_exists') : 'A user with that email already exists.');
    }
    error_log('User creation failed: ' . $vE->getMessage());
    throw new RuntimeException(function_exists('fT') ? fT('toast.operation_failed') : 'The operation could not be completed.');
  }

  return (int) fDb()->lastInsertId();
}

function fAuthStartSession($pUserId, $pLocale) {
  $dUser = fOne('SELECT session_version FROM users WHERE id = ?', [(int) $pUserId]);
  if (!$dUser) {
    throw new RuntimeException(function_exists('fT') ? fT('toast.user_not_found') : 'The user was not found.');
  }

  if (!session_regenerate_id(true)) {
    throw new RuntimeException(function_exists('fT') ? fT('toast.operation_failed') : 'The operation could not be completed.');
  }
  unset($_SESSION['vCsrfToken']);
  fCsrfToken();
  $_SESSION['vUserId'] = (int) $pUserId;
  $_SESSION['vSessionVersion'] = (int) $dUser['session_version'];
  if (function_exists('fSetCurrentLocale')) {
    fSetCurrentLocale($pLocale);
  }
}

function fAuthLogin($pEmail, $pPassword) {
  $vEmail = fAuthNormalizeEmail($pEmail);
  $vPassword = (string) $pPassword;
  if (!fAuthLoginAllowed()) {
    return false;
  }
  if (
    mb_strlen($vEmail) > 254
    || mb_strlen($vPassword) > cMaximumPasswordLength
    || strpos($vPassword, "\0") !== false
  ) {
    fAuthRecordLoginFailure();
    return false;
  }
  $dUser = fOne('SELECT * FROM users WHERE email = ?', [$vEmail]);

  if (!$dUser || !password_verify($vPassword, $dUser['password_hash'])) {
    fAuthRecordLoginFailure();
    return false;
  }

  fAuthClearLoginFailures();

  if (password_needs_rehash($dUser['password_hash'], cPasswordAlgorithm)) {
    $vHash = password_hash($vPassword, cPasswordAlgorithm);
    if (is_string($vHash) && $vHash !== '') {
      fQuery('UPDATE users SET password_hash = ? WHERE id = ?', [$vHash, (int) $dUser['id']]);
    }
  }

  fAuthStartSession((int) $dUser['id'], $dUser['locale'] ?? cDefaultLocale);
  return true;
}

function fAuthLogout() {
  $_SESSION = [];

  if (ini_get('session.use_cookies')) {
    $dCookieParams = session_get_cookie_params();
    setcookie(session_name(), '', [
      'expires' => time() - 42000,
      'path' => $dCookieParams['path'],
      'domain' => $dCookieParams['domain'],
      'secure' => $dCookieParams['secure'],
      'httponly' => $dCookieParams['httponly'],
      'samesite' => $dCookieParams['samesite'] ?? 'Strict',
    ]);
  }

  if (!session_destroy() || !session_start() || !session_regenerate_id(true)) {
    throw new RuntimeException(function_exists('fT') ? fT('toast.operation_failed') : 'The operation could not be completed.');
  }
}

function fAuthChangePassword($pCurrentPassword, $pNewPassword) {
  $dUser = fOne('SELECT id, password_hash, session_version FROM users WHERE id = ?', [fUserId()]);
  if (!$dUser || !password_verify((string) $pCurrentPassword, $dUser['password_hash'])) {
    throw new RuntimeException(function_exists('fT') ? fT('toast.current_password_invalid') : 'The current password is incorrect.');
  }

  $vNewPassword = fAuthValidatePassword($pNewPassword);
  $vHash = password_hash($vNewPassword, cPasswordAlgorithm);
  if (!is_string($vHash) || $vHash === '') {
    throw new RuntimeException(function_exists('fT') ? fT('toast.operation_failed') : 'The operation could not be completed.');
  }

  if (!session_regenerate_id(true)) {
    throw new RuntimeException(function_exists('fT') ? fT('toast.operation_failed') : 'The operation could not be completed.');
  }
  unset($_SESSION['vCsrfToken']);
  fCsrfToken();

  $vDb = fDb();
  fQuery('BEGIN IMMEDIATE');
  try {
    fQuery(
      'UPDATE users SET password_hash = ?, session_version = session_version + 1 WHERE id = ?',
      [$vHash, (int) $dUser['id']]
    );
    $vSessionVersion = (int) fScalar('SELECT session_version FROM users WHERE id = ?', [(int) $dUser['id']]);
    $vDb->commit();
  } finally {
    if ($vDb->inTransaction()) {
      $vDb->rollBack();
    }
  }
  $_SESSION['vSessionVersion'] = $vSessionVersion;
}

function fAuthUpdateProfile($pName) {
  $vName = trim((string) $pName);
  if ($vName === '') {
    throw new RuntimeException(function_exists('fT') ? fT('toast.name_required') : 'The name is required.');
  }
  if (mb_strlen($vName) > 120) {
    $vName = mb_substr($vName, 0, 120);
  }

  fQuery(
    'UPDATE users SET name = ?, initial = ? WHERE id = ?',
    [$vName, fAuthCreateInitial($vName, fUserEmail()), fUserId()]
  );
}

function fAuthAdminUpdateRole($pUserId, $pRole) {
  $vUserId = (int) $pUserId;
  $vRole = $pRole === 'admin' ? 'admin' : 'user';
  $vDb = fDb();
  fQuery('BEGIN IMMEDIATE');
  try {
    $dUser = fOne('SELECT id, role FROM users WHERE id = ?', [$vUserId]);
    if (!$dUser) {
      throw new RuntimeException(function_exists('fT') ? fT('toast.user_not_found') : 'The user was not found.');
    }

    if ($dUser['role'] === 'admin' && $vRole !== 'admin') {
      $vAdminCount = (int) fScalar('SELECT COUNT(*) FROM users WHERE role = ?', ['admin']);
      if ($vAdminCount <= 1) {
        throw new RuntimeException(function_exists('fT') ? fT('toast.last_admin_required') : 'At least one administrator is required.');
      }
    }

    fQuery('UPDATE users SET role = ? WHERE id = ?', [$vRole, $vUserId]);
    $vDb->commit();
  } finally {
    if ($vDb->inTransaction()) {
      $vDb->rollBack();
    }
  }
}

function fAuthAdminDeleteUser($pUserId) {
  $vUserId = (int) $pUserId;
  if ($vUserId === fUserId()) {
    throw new RuntimeException(function_exists('fT') ? fT('toast.cannot_delete_self') : 'You cannot delete your own account.');
  }

  $vDb = fDb();
  fQuery('BEGIN IMMEDIATE');
  try {
    $dUser = fOne('SELECT id, role FROM users WHERE id = ?', [$vUserId]);
    if (!$dUser) {
      throw new RuntimeException(function_exists('fT') ? fT('toast.user_not_found') : 'The user was not found.');
    }
    if ($dUser['role'] === 'admin') {
      $vAdminCount = (int) fScalar('SELECT COUNT(*) FROM users WHERE role = ?', ['admin']);
      if ($vAdminCount <= 1) {
        throw new RuntimeException(function_exists('fT') ? fT('toast.last_admin_required') : 'At least one administrator is required.');
      }
    }

    fQuery('DELETE FROM users WHERE id = ?', [$vUserId]);
    $vDb->commit();
  } finally {
    if ($vDb->inTransaction()) {
      $vDb->rollBack();
    }
  }
}

function fAuthSafeDestination($pDestination) {
  if (!is_string($pDestination)) {
    return fUrl('home');
  }
  $vDestination = trim((string) $pDestination);
  if ($vDestination === '') {
    return fUrl('home');
  }
  if (preg_match('/^\/?index\.php(\?|$)/', $vDestination)) {
    if (substr($vDestination, 0, 1) !== '/') {
      return '/' . $vDestination;
    }
    return $vDestination;
  }
  return fUrl('home');
}

function fCurrentUser() {
  $vUserId = (int) ($_SESSION['vUserId'] ?? 0);
  $vSessionVersion = (int) ($_SESSION['vSessionVersion'] ?? 0);

  if ($vUserId <= 0) {
    return null;
  }

  $dUser = fOne(
    'SELECT id, email, name, handle, initial, role, color, locale, session_version, created_at FROM users WHERE id = ?',
    [$vUserId]
  );

  if (!$dUser || $vSessionVersion <= 0 || $vSessionVersion !== (int) $dUser['session_version']) {
    unset($_SESSION['vUserId'], $_SESSION['vSessionVersion']);
    return null;
  }

  return $dUser;
}

function fUserLoggedIn() {
  return fCurrentUser() !== null;
}

function fUserId() {
  $dUser = fCurrentUser();
  return $dUser ? (int) $dUser['id'] : 0;
}

function fUserEmail() {
  $dUser = fCurrentUser();
  return $dUser['email'] ?? '';
}

function fUserName() {
  $dUser = fCurrentUser();
  return $dUser['name'] ?? '';
}

function fUserHandle() {
  $dUser = fCurrentUser();
  return $dUser['handle'] ?? '';
}

function fUserInitial() {
  $dUser = fCurrentUser();
  return $dUser['initial'] ?? '';
}

function fUserColor() {
  $dUser = fCurrentUser();
  return $dUser['color'] ?? '#e85d3d';
}

function fUserLocale() {
  $dUser = fCurrentUser();
  return fNormalizeLocale($dUser['locale'] ?? cDefaultLocale);
}

function fUserIsAdmin() {
  $dUser = fCurrentUser();
  return $dUser && ($dUser['role'] ?? '') === 'admin';
}

function fRequireLogin() {
  if (!fUserLoggedIn()) {
    fToastSave(function_exists('fT') ? fT('toast.login_required') : 'Sign in to continue.');
    fRedirect(fUrl('login'));
  }
}

function fRequireAdmin() {
  fRequireLogin();
  if (!fUserIsAdmin()) {
    fToastSave(function_exists('fT') ? fT('toast.admin_required') : 'Administrator permissions are required.');
    fRedirect(fUrl('home'));
  }
}
