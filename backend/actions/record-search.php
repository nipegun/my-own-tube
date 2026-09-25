<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/session.php';
require_once __DIR__ . '/../inc/helpers.php';
require_once __DIR__ . '/../inc/i18n.php';

fAuthEnsureSchema();
fRequireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  fRedirect(fUrl('search'));
}
fCsrfRequire();

$vSubmittedQuery = $_POST['q'] ?? '';
$vQuery = is_string($vSubmittedQuery) ? mb_substr(trim($vSubmittedQuery), 0, 200) : '';

if ($vQuery !== '') {
  try {
    fQuery(
      'INSERT INTO search_history (user_id, text, searched_at) VALUES (?, ?, ?)
      ON CONFLICT(user_id, text) DO UPDATE SET searched_at = excluded.searched_at',
      [fUserId(), $vQuery, time()]
    );
  } catch (Throwable $vE) {
    error_log('Search history recording failed: ' . $vE->getMessage());
  }
}

fRedirect(fUrl('search', $vQuery !== '' ? ['q' => $vQuery] : []));
