<?php

function fSupportedLocales() {
  return cSupportedLocales;
}

function fLocaleNames() {
  return cLocaleNames;
}

function fNormalizeLocale($pLocale) {
  $vLocale = str_replace('_', '-', mb_strtolower(trim((string) $pLocale)));
  return in_array($vLocale, fSupportedLocales(), true) ? $vLocale : cDefaultLocale;
}

function fLocaleName($pLocale) {
  $vLocale = fNormalizeLocale($pLocale);
  $dNames = fLocaleNames();
  return $dNames[$vLocale] ?? $vLocale;
}

function fLocaleFilePath($pLocale) {
  return cFrontendPath . '/lang/' . fNormalizeLocale($pLocale) . '.json';
}

function fLoadLocaleMessages($pLocale) {
  static $dCache = [];

  $vLocale = fNormalizeLocale($pLocale);
  if (isset($dCache[$vLocale])) {
    return $dCache[$vLocale];
  }

  $dMessages = [];
  $vPath = fLocaleFilePath($vLocale);
  if (is_file($vPath) && is_readable($vPath)) {
    $vJson = file_get_contents($vPath);
    $dDecoded = is_string($vJson) ? json_decode($vJson, true) : null;
    if (is_array($dDecoded)) {
      $dMessages = $dDecoded;
    }
  }

  $dCache[$vLocale] = $dMessages;
  return $dMessages;
}

function fI18nTableExists($pTable) {
  if (!file_exists(cDbPath)) {
    return false;
  }

  try {
    $dTable = fOne(
      "SELECT name FROM sqlite_master WHERE type = 'table' AND name = ?",
      [$pTable]
    );
    return (bool) $dTable;
  } catch (Throwable $vE) {
    return false;
  }
}

function fI18nColumnExists($pTable, $pColumn) {
  if (!file_exists(cDbPath)) {
    return false;
  }

  $vTable = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $pTable);
  if ($vTable === '') {
    return false;
  }

  try {
    $vStmt = fDb()->query('PRAGMA table_info(' . $vTable . ')');
    $ldColumns = $vStmt ? $vStmt->fetchAll() : [];
    foreach ($ldColumns as $dColumn) {
      if (($dColumn['name'] ?? '') === $pColumn) {
        return true;
      }
    }
  } catch (Throwable $vE) {
    return false;
  }

  return false;
}

function fSiteDefaultLocale($pRefresh = false) {
  static $vLocale = null;

  if ($pRefresh) {
    $vLocale = null;
  }
  if ($vLocale !== null) {
    return $vLocale;
  }

  $vLocale = cDefaultLocale;
  if (fI18nTableExists('app_settings')) {
    try {
      $dSetting = fOne('SELECT value FROM app_settings WHERE name = ?', ['default_locale']);
      if ($dSetting) {
        $vLocale = fNormalizeLocale($dSetting['value'] ?? cDefaultLocale);
      }
    } catch (Throwable $vE) {
      $vLocale = cDefaultLocale;
    }
  }

  return $vLocale;
}

function fSetSiteDefaultLocale($pLocale) {
  $vLocale = fNormalizeLocale($pLocale);
  fQuery(
    'INSERT INTO app_settings (name, value, updated_at) VALUES (?, ?, ?)
    ON CONFLICT(name) DO UPDATE SET value = excluded.value, updated_at = excluded.updated_at',
    ['default_locale', $vLocale, time()]
  );
  fSiteDefaultLocale(true);
  return $vLocale;
}

function fSetCurrentLocale($pLocale) {
  $vLocale = fNormalizeLocale($pLocale);
  $_SESSION['vLocale'] = $vLocale;
  $GLOBALS['vCurrentLocale'] = $vLocale;
  return $vLocale;
}

function fCurrentLocale($pRefresh = false) {
  if (!$pRefresh && isset($GLOBALS['vCurrentLocale'])) {
    return $GLOBALS['vCurrentLocale'];
  }

  $vLocale = '';
  $vUserId = (int) ($_SESSION['vUserId'] ?? 0);
  $vSessionLocale = $_SESSION['vLocale'] ?? '';
  if ($vUserId > 0 && is_string($vSessionLocale) && trim($vSessionLocale) !== '' && in_array(fNormalizeLocale($vSessionLocale), fSupportedLocales(), true)) {
    $vLocale = fNormalizeLocale($vSessionLocale);
  }

  if ($vUserId > 0 && fI18nTableExists('users') && fI18nColumnExists('users', 'locale')) {
    try {
      $dUser = fOne('SELECT locale FROM users WHERE id = ?', [$vUserId]);
      if ($dUser && !empty($dUser['locale'])) {
        $vLocale = fNormalizeLocale($dUser['locale']);
      }
    } catch (Throwable $vE) {
      $vLocale = '';
    }
  }

  if ($vLocale === '') {
    $vLocale = fSiteDefaultLocale();
  }

  return fSetCurrentLocale($vLocale);
}

function fT($pKey, $pParams = []) {
  $vLocale = fCurrentLocale();
  $dFallback = fLoadLocaleMessages(cDefaultLocale);
  $dMessages = $vLocale === cDefaultLocale ? $dFallback : array_merge($dFallback, fLoadLocaleMessages($vLocale));
  $vText = $dMessages[$pKey] ?? $pKey;

  foreach ($pParams as $vKey => $vValue) {
    $vText = str_replace('{{' . $vKey . '}}', (string) $vValue, $vText);
  }

  return $vText;
}

function fTChoice($pSingularKey, $pPluralKey, $pCount, $pParams = []) {
  $dParams = array_merge(['count' => (int) $pCount], $pParams);
  return fT((int) $pCount === 1 ? $pSingularKey : $pPluralKey, $dParams);
}

function fLocaleKey($pText) {
  $vText = str_replace(['á', 'é', 'í', 'ó', 'ú', 'ñ', 'ü'], ['a', 'e', 'i', 'o', 'u', 'n', 'u'], mb_strtolower(trim((string) $pText)));
  $vText = preg_replace('/[^a-z0-9]+/', '_', $vText);
  return trim($vText, '_');
}

function fCategoryLabel($pCategory) {
  $vKey = 'category.' . fLocaleKey($pCategory);
  $vTranslated = fT($vKey);
  return $vTranslated === $vKey ? (string) $pCategory : $vTranslated;
}
