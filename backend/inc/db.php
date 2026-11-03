<?php

require_once __DIR__ . '/../config.php';

function fDbAbort($pPublicMessage, $pException) {
  $vDetailedMessage = $pPublicMessage . ': ' . $pException->getMessage();
  error_log($vDetailedMessage);

  if (PHP_SAPI === 'cli') {
    fwrite(STDERR, $vDetailedMessage . PHP_EOL);
  } else {
    http_response_code(500);
    echo htmlspecialchars($pPublicMessage, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
  }

  exit(1);
}

function fDb() {
  static $vPdo = null;

  if ($vPdo !== null) {
    return $vPdo;
  }

  if (!is_file(cDbPath)) {
    fDbAbort('Database unavailable.', new RuntimeException('The database file does not exist.'));
  }

  try {
    $vPdo = new PDO('sqlite:' . cDbPath);
    $vPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $vPdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $vPdo->exec('PRAGMA foreign_keys = ON');
    $vPdo->exec('PRAGMA busy_timeout = 5000');
    $vPdo->exec('PRAGMA journal_mode = WAL');
    $vPdo->exec('PRAGMA synchronous = NORMAL');
  } catch (PDOException $vE) {
    fDbAbort('Database connection error.', $vE);
  }

  return $vPdo;
}

function fQuery($pSql, $pParams = []) {
  static $vHandlingFailure = false;

  try {
    $vStmt = fDb()->prepare($pSql);
    $vStmt->execute($pParams);
    return $vStmt;
  } catch (PDOException $vE) {
    error_log('Database query error: ' . $vE->getMessage());
    $vPublicMessage = 'The operation could not be completed.';
    if (!$vHandlingFailure && function_exists('fT')) {
      $vHandlingFailure = true;
      try {
        $vPublicMessage = fT('toast.operation_failed');
      } catch (Throwable $vTranslationError) {
        error_log('Database error translation failed: ' . $vTranslationError->getMessage());
      } finally {
        $vHandlingFailure = false;
      }
    }
    throw new RuntimeException($vPublicMessage, 0, $vE);
  }
}

function fAll($pSql, $pParams = []) {
  return fQuery($pSql, $pParams)->fetchAll();
}

function fOne($pSql, $pParams = []) {
  $dRow = fQuery($pSql, $pParams)->fetch();
  return $dRow === false ? null : $dRow;
}

function fScalar($pSql, $pParams = []) {
  $vStmt = fQuery($pSql, $pParams);
  return $vStmt->fetchColumn();
}
