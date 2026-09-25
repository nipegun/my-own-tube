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

try {
  fDb()->exec(
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

  $ldUserColumns = fAll('PRAGMA table_info(users)');
  $dUserColumns = [];
  foreach ($ldUserColumns as $dUserColumn) {
    $dUserColumns[(string) $dUserColumn['name']] = true;
  }
  if (!isset($dUserColumns['locale'])) {
    fDb()->exec("ALTER TABLE users ADD COLUMN locale TEXT NOT NULL DEFAULT 'en-us'");
  }
  if (!isset($dUserColumns['session_version'])) {
    fDb()->exec('ALTER TABLE users ADD COLUMN session_version INTEGER NOT NULL DEFAULT 1');
  }

  $dAdmin = fOne('SELECT id, email, password_hash FROM users WHERE role = ? ORDER BY created_at LIMIT 1', ['admin']);
  if (!$dAdmin) {
    $dConfiguredUser = fOne('SELECT id, email, password_hash FROM users WHERE email = ?', [cAdminEmail]);
    if ($dConfiguredUser) {
      fQuery(
        'UPDATE users SET role = ?, session_version = session_version + 1 WHERE id = ?',
        ['admin', (int) $dConfiguredUser['id']]
      );
      $dAdmin = $dConfiguredUser;
    }
  }

  $vLegacyPassword = 'P@ssw0rd';
  $ldUsers = fAll('SELECT id, email, password_hash FROM users ORDER BY created_at');
  $ldLegacyUsers = [];
  foreach ($ldUsers as $dUser) {
    if (password_verify($vLegacyPassword, (string) $dUser['password_hash'])) {
      $ldLegacyUsers[] = $dUser;
    }
  }
  $vMustCreateAdmin = !$dAdmin;
  $vMustRotate = $vMustCreateAdmin || !empty($ldLegacyUsers);

  if ($vMustRotate) {
    $vAdminPassword = getenv(cAdminPasswordEnvironment);
    $vAdminPassword = is_string($vAdminPassword) ? $vAdminPassword : '';
    if ($vAdminPassword === '') {
      $vAdminPassword = rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
    }
    if (mb_strlen($vAdminPassword) < cMinimumPasswordLength || mb_strlen($vAdminPassword) > cMaximumPasswordLength) {
      throw new RuntimeException(
        cAdminPasswordEnvironment . ' must contain between ' . cMinimumPasswordLength . ' and ' . cMaximumPasswordLength . ' characters.'
      );
    }

    $vHash = password_hash($vAdminPassword, cPasswordAlgorithm);
    if (!is_string($vHash) || $vHash === '') {
      throw new RuntimeException('Could not hash the administrator password.');
    }

    $aSecuredEmails = [];
    $vDb = fDb();
    $vDb->beginTransaction();
    try {
      if ($vMustCreateAdmin) {
        fQuery(
          'INSERT INTO users (email, password_hash, name, handle, initial, role, color, locale, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
          [cAdminEmail, $vHash, cAdminName, cAdminHandle, cAdminInitial, 'admin', '#e85d3d', cDefaultLocale, time()]
        );
        $aSecuredEmails[] = cAdminEmail;
      }
      foreach ($ldLegacyUsers as $dLegacyUser) {
        fQuery(
          'UPDATE users SET password_hash = ?, session_version = session_version + 1 WHERE id = ?',
          [$vHash, (int) $dLegacyUser['id']]
        );
        $aSecuredEmails[] = (string) $dLegacyUser['email'];
      }
      $vDb->commit();
    } finally {
      if ($vDb->inTransaction()) {
        $vDb->rollBack();
      }
    }

    echo 'Administrator credentials were secured.' . PHP_EOL;
    echo 'Secured account email(s): ' . implode(', ', array_unique($aSecuredEmails)) . PHP_EOL;
    echo 'New password for the secured account(s): ' . $vAdminPassword . PHP_EOL;
    echo 'Store this password now; it is not written to disk in plain text. Set distinct passwords after signing in if more than one account was secured.' . PHP_EOL;
  } else {
    echo 'Administrator credentials are already using a non-default password.' . PHP_EOL;
  }

  fAuthEnsureSchema();
  if ($vMustRotate) {
    fQuery('DELETE FROM login_attempts');
  }
  echo 'Database schema is ready.' . PHP_EOL;
} catch (Throwable $vE) {
  fwrite(STDERR, 'Could not secure the administrator account: ' . $vE->getMessage() . PHP_EOL);
  exit(1);
}
