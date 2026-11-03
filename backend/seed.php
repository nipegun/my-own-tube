<?php

require_once __DIR__ . '/config.php';

if (PHP_SAPI !== 'cli') {
  http_response_code(404);
  exit(1);
}

$vLineBreak = "\n";
$vTemporaryDbPath = cDbPath . '.new.' . getmypid();
$vBackupDbPath = '';
$vSucceeded = false;
$vExitCode = 0;
$vDb = null;
$dBackupDatabasePaths = [];
$aArguments = $argv ?? [];
$vForce = in_array('--force', $aArguments, true);
$vDemo = in_array('--demo', $aArguments, true);
$vAdminPassword = getenv(cAdminPasswordEnvironment);
$vAdminPassword = is_string($vAdminPassword) ? $vAdminPassword : '';

if (file_exists(cDbPath) && !$vForce) {
  fwrite(STDERR, 'The database already exists. Use --force to replace it after creating an automatic backup.' . PHP_EOL);
  exit(1);
}

if ($vAdminPassword === '') {
  $vAdminPassword = rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
}
if (mb_strlen($vAdminPassword) < cMinimumPasswordLength || mb_strlen($vAdminPassword) > cMaximumPasswordLength) {
  fwrite(STDERR, cAdminPasswordEnvironment . ' must contain between ' . cMinimumPasswordLength . ' and ' . cMaximumPasswordLength . ' characters.' . PHP_EOL);
  exit(1);
}

try {
  if (!is_dir(dirname(cDbPath)) && !mkdir(dirname(cDbPath), 0750, true) && !is_dir(dirname(cDbPath))) {
    throw new RuntimeException('Could not create the database directory.');
  }
  if (!chmod(dirname(cDbPath), 0750)) {
    throw new RuntimeException('Could not secure the database directory permissions.');
  }
  if (file_exists($vTemporaryDbPath) && !unlink($vTemporaryDbPath)) {
    throw new RuntimeException('Could not remove a stale temporary database.');
  }

  $vDb = new PDO('sqlite:' . $vTemporaryDbPath);
  $vDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $vDb->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
  $vDb->exec('PRAGMA foreign_keys = ON');
  $vDb->beginTransaction();

  $aSchema = [
    "CREATE TABLE users (
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
    )",

    "CREATE TABLE app_settings (
      name            TEXT PRIMARY KEY,
      value           TEXT NOT NULL,
      updated_at      INTEGER NOT NULL
    )",

    "CREATE TABLE channels (
      id              TEXT PRIMARY KEY,
      name            TEXT NOT NULL,
      handle          TEXT NOT NULL,
      subs            INTEGER NOT NULL DEFAULT 0,
      color           TEXT NOT NULL,
      verified        INTEGER NOT NULL DEFAULT 0
    )",
    "CREATE TABLE videos (
      id              TEXT PRIMARY KEY,
      title           TEXT NOT NULL,
      description     TEXT NOT NULL DEFAULT '',
      channel_id      TEXT NOT NULL REFERENCES channels(id),
      category        TEXT NOT NULL,
      views           INTEGER NOT NULL DEFAULT 0,
      duration_sec    INTEGER NOT NULL DEFAULT 0,
      published_at    INTEGER NOT NULL,
      thumb_variant   INTEGER NOT NULL DEFAULT 1,
      is_available    INTEGER NOT NULL DEFAULT 1,
      file_path       TEXT
    )",
    "CREATE INDEX idx_videos_channel ON videos(channel_id)",
    "CREATE INDEX idx_videos_category ON videos(category)",
    "CREATE INDEX idx_videos_published ON videos(published_at DESC)",
    "CREATE INDEX idx_videos_available ON videos(is_available)",

    "CREATE TABLE categories (
      name            TEXT PRIMARY KEY,
      kind            TEXT NOT NULL DEFAULT 'cat',
      display_order   INTEGER NOT NULL DEFAULT 0
    )",

    "CREATE TABLE comments (
      id              INTEGER PRIMARY KEY AUTOINCREMENT,
      user_id         INTEGER REFERENCES users(id) ON DELETE SET NULL,
      video_id        TEXT NOT NULL REFERENCES videos(id),
      user_name       TEXT NOT NULL,
      user_color      TEXT NOT NULL,
      text            TEXT NOT NULL,
      likes           INTEGER NOT NULL DEFAULT 0,
      reply_count     INTEGER NOT NULL DEFAULT 0,
      created_at      INTEGER NOT NULL
    )",
    "CREATE INDEX idx_comments_video ON comments(video_id, created_at DESC)",

    "CREATE TABLE playlists (
      id              TEXT PRIMARY KEY,
      user_id         INTEGER REFERENCES users(id) ON DELETE CASCADE,
      name            TEXT NOT NULL,
      description     TEXT NOT NULL DEFAULT '',
      cover_variant   INTEGER NOT NULL DEFAULT 1,
      created_at      INTEGER NOT NULL
    )",
    "CREATE TABLE playlist_items (
      playlist_id     TEXT NOT NULL REFERENCES playlists(id) ON DELETE CASCADE,
      video_id        TEXT NOT NULL REFERENCES videos(id),
      position        INTEGER NOT NULL,
      PRIMARY KEY (playlist_id, video_id)
    )",

    "CREATE TABLE likes (
      user_id         INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
      video_id        TEXT NOT NULL REFERENCES videos(id),
      liked_at        INTEGER NOT NULL,
      PRIMARY KEY (user_id, video_id)
    )",
    "CREATE TABLE watch_later (
      user_id         INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
      video_id        TEXT NOT NULL REFERENCES videos(id),
      added_at        INTEGER NOT NULL,
      PRIMARY KEY (user_id, video_id)
    )",
    "CREATE TABLE history (
      user_id         INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
      video_id        TEXT NOT NULL REFERENCES videos(id),
      progress        INTEGER NOT NULL DEFAULT 0,
      watched_at      INTEGER NOT NULL,
      PRIMARY KEY (user_id, video_id)
    )",
    "CREATE INDEX idx_history_user_watched ON history(user_id, watched_at DESC)",
    "CREATE INDEX idx_likes_user_liked ON likes(user_id, liked_at DESC)",
    "CREATE INDEX idx_watch_later_user_added ON watch_later(user_id, added_at DESC)",
    "CREATE INDEX idx_comments_user ON comments(user_id, created_at DESC)",
    "CREATE INDEX idx_playlists_user_created ON playlists(user_id, created_at DESC)",

    "CREATE TABLE notifications (
      id              INTEGER PRIMARY KEY AUTOINCREMENT,
      channel_name    TEXT NOT NULL,
      channel_color   TEXT NOT NULL,
      message         TEXT NOT NULL,
      thumb_variant   INTEGER,
      unread          INTEGER NOT NULL DEFAULT 1,
      created_at      INTEGER NOT NULL
    )",

    "CREATE TABLE search_suggestions (
      id              INTEGER PRIMARY KEY AUTOINCREMENT,
      text            TEXT NOT NULL,
      is_history      INTEGER NOT NULL DEFAULT 0,
      display_order   INTEGER NOT NULL DEFAULT 0
    )",

    "CREATE TABLE search_history (
      user_id         INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
      text            TEXT NOT NULL,
      searched_at     INTEGER NOT NULL,
      PRIMARY KEY (user_id, text)
    )",
    "CREATE INDEX idx_search_history_user_searched ON search_history(user_id, searched_at DESC)",

    "CREATE TABLE login_attempts (
      attempt_key      TEXT PRIMARY KEY,
      attempts         INTEGER NOT NULL,
      first_attempt_at INTEGER NOT NULL,
      locked_until     INTEGER NOT NULL DEFAULT 0
    )",
    "CREATE INDEX idx_login_attempts_first ON login_attempts(first_attempt_at)",
  ];

  foreach ($aSchema as $vSql) {
    $vDb->exec($vSql);
  }
  echo 'Schema created.' . $vLineBreak;

  $vNow = time();
  $vHashAdmin = password_hash($vAdminPassword, cPasswordAlgorithm);
  if (!is_string($vHashAdmin) || $vHashAdmin === '') {
    throw new RuntimeException('Could not hash the administrator password.');
  }

  $vStmt = $vDb->prepare('INSERT INTO app_settings (name, value, updated_at) VALUES (?, ?, ?)');
  $vStmt->execute(['default_locale', cDefaultLocale, $vNow]);
  $vStmt->execute(['allow_registration', '0', $vNow]);
  echo 'Default site language initialized: ' . cDefaultLocale . $vLineBreak;

  $vStmt = $vDb->prepare('INSERT INTO users (email, password_hash, name, handle, initial, role, color, locale, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
  $vStmt->execute([cAdminEmail, $vHashAdmin, cAdminName, cAdminHandle, cAdminInitial, 'admin', '#e85d3d', cDefaultLocale, $vNow]);
  $vAdminId = (int) $vDb->lastInsertId();
  echo 'Admin user created: ' . cAdminEmail . $vLineBreak;

  $aChannels = [
    ['c1',  'Lumen Studios',    '@lumenstudios',    0, '#f59e0b', 1],
    ['c2',  'Orbit Tech',       '@orbittech',       0, '#3b82f6', 1],
    ['c3',  "Marta's Kitchen",  '@martaskitchen',   0, '#ef4444', 1],
    ['c4',  'Lore and Letters', '@loreletters',     0, '#a855f7', 0],
    ['c5',  'Pixel Arcade',     '@pixelarcade',     0, '#10b981', 1],
    ['c6',  'Nova Music',       '@novamusic',       0, '#ec4899', 1],
    ['c7',  'Line and Ink',     '@lineandink',      0, '#14b8a6', 0],
    ['c8',  'North Route',      '@northroute',      0, '#f97316', 1],
    ['c9',  'Open Ledger',      '@openledger',      0, '#6366f1', 1],
    ['c10', 'Green Hour',       '@greenhour',       0, '#84cc16', 0],
  ];
  $vStmt = $vDb->prepare('INSERT INTO channels (id, name, handle, subs, color, verified) VALUES (?, ?, ?, ?, ?, ?)');
  foreach ($vDemo ? $aChannels : [] as $aRow) {
    $vStmt->execute($aRow);
  }
  echo ($vDemo ? count($aChannels) : 0) . ' channels inserted.' . $vLineBreak;

  $aCategories = [
    ['All',        'virtual', 0],
    ['Music',      'cat',     1],
    ['Technology', 'cat',     2],
    ['Cooking',    'cat',     3],
    ['Literature', 'cat',     4],
    ['Gaming',     'cat',     5],
    ['Art',        'cat',     6],
    ['Travel',     'cat',     7],
    ['Finance',    'cat',     8],
    ['Gardening',  'cat',     9],
    ['Recent',     'virtual', 10],
  ];
  $vStmt = $vDb->prepare('INSERT INTO categories (name, kind, display_order) VALUES (?, ?, ?)');
  if (!$vDemo) {
    $aCategories = [['All', 'virtual', 0], ['Uploads', 'cat', 1], ['Recent', 'virtual', 2]];
  }
  foreach ($aCategories as $aRow) {
    $vStmt->execute($aRow);
  }
  echo count($aCategories) . ' categories inserted.' . $vLineBreak;

  $aVideos = [
    ['v1',  'I built an analog modular synthesizer from scratch, and it sounded better than expected', 'After six months designing PCBs and burning resistors, I assembled my first modular synthesizer. This video covers the full process, the most important circuits, and how it sounds next to the classics.', 'c1', 'Music',      0,  863, 2 * 86400,   1],
    ['v2',  'How RISC-V chips are quietly changing the industry', 'A deep look at the rise of RISC-V and why companies like Qualcomm, NVIDIA, and entire governments are betting on this open architecture.', 'c2', 'Technology', 0, 1330, 5 * 86400,   2],
    ['v3',  "48-hour sourdough bread: my grandmother's bakery recipe", 'I found my grandmother’s recipe notebook and this was the first one I tried. Crisp crust, open crumb, and a flavor modern bakeries rarely match.', 'c3', 'Cooking',    0,  585, 7 * 86400,   3],
    ['v4',  "Why 'One Hundred Years of Solitude' is still misunderstood", 'An essay on magical realism, cyclical time, and the political readings contemporary critics often overlook.', 'c4', 'Literature', 0, 1868, 3 * 86400,   4],
    ['v5',  'I finished the Elden Ring DLC on max difficulty without dying', 'Almost 80 hours of gameplay condensed into one analysis. Builds, optimal routes, and the bosses that nearly made me quit.', 'c5', 'Gaming',     0, 2842, 4 * 86400,   5],
    ['v6',  'I recorded an entire album with kitchen instruments', 'Pots, spoons, pressure cooker noise, and everything I found in my kitchen became part of a 10-song album.', 'c6', 'Music',      0, 1110, 14 * 86400,  6],
    ['v7',  'Ink wash techniques I learned in Kyoto', 'I spent three weeks studying with a sumi-e master. Here are the five fundamental strokes and how to practice them at home.', 'c7', 'Art',        0,  775, 6 * 86400,   7],
    ['v8',  'I drove 3000 km through Patagonia in a 1987 car', 'Breakdowns, impossible landscapes, forgotten towns, and the best barbecue of my life. A trip with no GPS, only paper maps.', 'c8', 'Travel',     0, 4360, 7 * 86400,   8],
    ['v9',  'The hidden problem with self-help books, after reading 50 bestsellers', 'I read 50 of the best-selling self-help books of the last decade. The patterns I found may surprise you.', 'c4', 'Literature', 0, 1517, 14 * 86400,  4],
    ['v10', 'Repairing my old 1999 Mac took 3 months, and it was worth every minute', 'Blown capacitors, impossible solder joints, discontinued components. This is how I restored a Bondi Blue iMac G3.', 'c2', 'Technology', 0, 1684, 21 * 86400, 2],
    ['v11', 'I made fresh pasta every day for a month', '31 days, 31 different pasta shapes. Ravioli, tagliatelle, orecchiette, and a delicious experiment that was sometimes frustrating.', 'c3', 'Cooking',    0, 1010, 30 * 86400,  3],
    ['v12', 'Speedrunning a game that came out yesterday: world record attempt', 'A 14-hour streak trying to become the first person to finish this game in under 45 minutes. Spoiler: I did not make it. Or did I?', 'c5', 'Gaming', 0, 2535, 2 * 86400, 5],
    ['v13', 'A symphony orchestra plays video game music', 'A youth philharmonic performs soundtracks from Chrono Trigger, Zelda, Halo, and more. Recorded live at Teatro Colon.', 'c6', 'Music', 0, 6502, 30 * 86400, 6],
    ['v14', 'I lived a month in Lisbon with EUR 100', 'Food, lodging, transport, and entertainment. Is it possible? Yes, but only with tricks nobody tells you about.', 'c8', 'Travel', 0, 1475, 14 * 86400, 8],
    ['v15', 'Miniature watercolor: how to paint in postcard format', 'Brushes from 000 to 2, cotton paper, and a lot of patience. A tutorial for anyone starting with botanical watercolor.', 'c7', 'Art', 0, 1040, 4 * 86400, 7],
    ['v16', 'Why web design in 2005 was better than today', 'Skeuomorphism, custom fonts, Flash animations, and the lost soul of web design. A love letter to the internet that used to be.', 'c2', 'Technology', 0, 1142, 21 * 86400, 2],
    ['v17', 'Personal finance for people in their twenties', 'Investing, saving, debt, and the most common mistake we make after university. No jargon and no impossible promises.', 'c9', 'Finance', 0, 1398, 7 * 86400, 1],
    ['v18', 'I grew my own coffee on a balcony for 2 years', 'From seed to cup. Watering, roasting, grinding: the full coffee cycle at home from a 45 square meter apartment.', 'c10', 'Gardening', 0, 1304, 30 * 86400, 3],
    ['v19', 'How a modern processor works, explained with Lego', 'Pipelines, cache, branch prediction, and out-of-order execution, all explained with a computer made literally from Lego bricks.', 'c2', 'Technology', 0, 2090, 60 * 86400, 2],
    ['v20', 'I composed a song for every emotional state', 'Happiness, sadness, anger, melancholy, euphoria. 12 songs, 12 moods, recorded at the exact moment I felt them.', 'c1', 'Music', 0, 1571, 5 * 86400, 6],
    ['v21', 'I found this for EUR 3 at an Istanbul flea market', 'A walk through antiques, textiles, and merchants who do not accept easy haggling. What I brought home changed my collection.', 'c8', 'Travel', 0, 928, 6 * 86400, 8],
    ['v22', 'Painting a 10-meter mural with no sketches', 'A 40-hour timelapse in 10 minutes. No plans, no grid, just paint and real-time decisions.', 'c7', 'Art', 0, 590, 14 * 86400, 7],
    ['v23', 'The Japanese trick that keeps knives sharp', 'Sharpening stones, exact angles, and the ritual every chef should know. Simpler than it looks and more effective than you think.', 'c3', 'Cooking', 0, 675, 21 * 86400, 3],
    ['v24', 'I tried 10 indie games nobody knows', 'Hidden gems from Itch.io, Steam Next Fest, and a few projects still in development. My top 10 after 40 hours of testing.', 'c5', 'Gaming', 0, 2322, 7 * 86400, 5],
    ['v25', 'Review: the strangest book I read this year', 'A book with no plot, no characters, only descriptions of rooms. Why I could not stop reading it anyway.', 'c4', 'Literature', 0, 842, 7 * 86400, 4],
    ['v26', 'How I built an urban garden in 2 square meters', 'Tomatoes, basil, arugula, peppers. Everything you need to start on a balcony without spending a fortune.', 'c10', 'Gardening', 0, 1175, 14 * 86400, 3],
    ['v27', 'The biggest cryptocurrency scam: full investigation', 'Six months of investigation, dozens of interviews, leaked documents. This is how the scheme that vanished with 3 billion worked.', 'c9', 'Finance', 0, 3382, 30 * 86400, 1],
    ['v28', 'Acoustic concert in the forest: full intimate session', 'A dawn session recorded in the middle of the Black Forest. Just guitar, voice, and birds in the background.', 'c6', 'Music', 0, 3130, 21 * 86400, 6],
  ];
  $vStmt = $vDb->prepare('INSERT INTO videos (id, title, description, channel_id, category, views, duration_sec, published_at, thumb_variant, is_available) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0)');
  foreach ($vDemo ? $aVideos : [] as $aRow) {
    $aRow[7] = $vNow - $aRow[7];
    $vStmt->execute($aRow);
  }
  echo ($vDemo ? count($aVideos) : 0) . ' demo videos inserted (unavailable until media is imported).' . $vLineBreak;

  echo 'Comments initialized empty.' . $vLineBreak;

  echo 'Admin private data initialized empty.' . $vLineBreak;

  echo 'Notifications initialized empty.' . $vLineBreak;

  $aSuggestions = [
    ['modular synthesizer',              0, 0],
    ['modular synthesizer from scratch', 0, 1],
    ['analog synthesizer tutorial',      0, 2],
    ['homemade synthesizer step by step',0, 3],
    ['subtractive synthesis explained',  0, 4],
    ['eurorack modular synthesizer',     0, 5],
    ['cheap diy synthesizer',            0, 6],
  ];
  $vStmt = $vDb->prepare('INSERT INTO search_suggestions (text, is_history, display_order) VALUES (?, ?, ?)');
  foreach ($vDemo ? $aSuggestions : [] as $aRow) {
    $vStmt->execute($aRow);
  }
  echo ($vDemo ? count($aSuggestions) : 0) . ' suggestions inserted.' . $vLineBreak;

  $vDb->commit();
  $vDb = null;

  if (file_exists(cDbPath)) {
    $vExistingDb = new PDO('sqlite:' . cDbPath);
    $vExistingDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $vExistingDb->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $vExistingDb->exec('PRAGMA busy_timeout = 5000');
    $dCheckpoint = $vExistingDb->query('PRAGMA wal_checkpoint(TRUNCATE)')->fetch();
    if (!$dCheckpoint || (int) ($dCheckpoint['busy'] ?? 1) !== 0) {
      throw new RuntimeException('Could not checkpoint the existing database. Stop web traffic and try again.');
    }
    $vIntegrityResult = $vExistingDb->query('PRAGMA quick_check')->fetchColumn();
    if ($vIntegrityResult !== 'ok') {
      throw new RuntimeException('The existing database failed its integrity check.');
    }
    $vExistingDb = null;

    $vBackupDbPath = cDbPath . '.backup.' . date('Ymd_His') . '.' . getmypid() . '.' . bin2hex(random_bytes(4));
    $aDatabaseSuffixes = ['', '-wal', '-shm'];
    foreach ($aDatabaseSuffixes as $vSuffix) {
      $vSourcePath = cDbPath . $vSuffix;
      $vBackupPath = $vBackupDbPath . $vSuffix;
      if (file_exists($vSourcePath)) {
        if (!rename($vSourcePath, $vBackupPath)) {
          foreach ($dBackupDatabasePaths as $vOriginalPath => $vMovedPath) {
            if (file_exists($vMovedPath)) {
              rename($vMovedPath, $vOriginalPath);
            }
          }
          throw new RuntimeException('Could not back up the existing database.');
        }
        $dBackupDatabasePaths[$vSourcePath] = $vBackupPath;
        if (!chmod($vBackupPath, 0640)) {
          foreach ($dBackupDatabasePaths as $vOriginalPath => $vMovedPath) {
            if (file_exists($vMovedPath)) {
              rename($vMovedPath, $vOriginalPath);
            }
          }
          throw new RuntimeException('Could not secure the existing database backup.');
        }
      }
    }
  }

  if (!rename($vTemporaryDbPath, cDbPath)) {
    foreach ($dBackupDatabasePaths as $vOriginalPath => $vMovedPath) {
      if (file_exists($vMovedPath)) {
        rename($vMovedPath, $vOriginalPath);
      }
    }
    throw new RuntimeException('Could not activate the new database.');
  }
  if (!chmod(cDbPath, 0640)) {
    rename(cDbPath, $vTemporaryDbPath);
    foreach ($dBackupDatabasePaths as $vOriginalPath => $vMovedPath) {
      if (file_exists($vMovedPath)) {
        rename($vMovedPath, $vOriginalPath);
      }
    }
    throw new RuntimeException('Could not secure the database file permissions.');
  }

  $vSucceeded = true;
  echo $vLineBreak . 'Seed complete. DB at: ' . cDbPath . $vLineBreak;
  if ($vBackupDbPath !== '') {
    echo 'Previous database backup: ' . $vBackupDbPath . $vLineBreak;
  }
  echo 'Administrator email: ' . cAdminEmail . $vLineBreak;
  echo 'Administrator password: ' . $vAdminPassword . $vLineBreak;
  echo 'Store this password now; it is not written to disk in plain text.' . $vLineBreak;
} catch (Throwable $vE) {
  $vExitCode = 1;
  fwrite(STDERR, 'Seed error: ' . $vE->getMessage() . $vLineBreak);
} finally {
  if ($vDb instanceof PDO) {
    if ($vDb->inTransaction()) {
      $vDb->rollBack();
    }
    $vDb = null;
  }
  if (!$vSucceeded && file_exists($vTemporaryDbPath)) {
    unlink($vTemporaryDbPath);
  }
}

if ($vExitCode !== 0) {
  exit($vExitCode);
}
