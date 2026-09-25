<?php

function fE($pText) {
  return htmlspecialchars((string) $pText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function fUrl($pPage, $pParams = []) {
  $dAll = array_merge(['page' => $pPage], $pParams);
  return '/index.php?' . http_build_query($dAll);
}

function fCurrentPage() {
  $vPage = $_GET['page'] ?? 'home';
  if (!is_string($vPage)) {
    return 'home';
  }
  return preg_replace('/[^a-z0-9_-]/', '', $vPage);
}

function fParam($pName, $pDefault = null) {
  $vValue = $_GET[$pName] ?? $pDefault;
  return is_string($vValue) ? trim($vValue) : $pDefault;
}

function fPaginate($pCountSql, $pRowsSql, $pParams = [], $pParameter = 'p') {
  $vTotal = max(0, (int) fScalar($pCountSql, $pParams));
  $vPages = max(1, (int) ceil($vTotal / cPageSize));
  $vRequested = fParam($pParameter, '1');
  $vPage = is_string($vRequested) && ctype_digit($vRequested) ? (int) $vRequested : 1;
  $vPage = max(1, min($vPages, $vPage));
  $vOffset = ($vPage - 1) * cPageSize;
  return [
    'total' => $vTotal,
    'page' => $vPage,
    'pages' => $vPages,
    'parameter' => $pParameter,
    'rows' => fAll($pRowsSql . ' LIMIT ' . (int) cPageSize . ' OFFSET ' . $vOffset, $pParams),
  ];
}

function fFallbackText($pKey, $pFallback, $pParams = []) {
  if (function_exists('fT')) {
    return fT($pKey, $pParams);
  }

  $vText = $pFallback;
  foreach ($pParams as $vKey => $vValue) {
    $vText = str_replace('{{' . $vKey . '}}', (string) $vValue, $vText);
  }
  return $vText;
}

function fFormatViews($pViews) {
  $vNum = (int) $pViews;
  if ($vNum >= 1000000) {
    return rtrim(rtrim(number_format($vNum / 1000000, 1, '.', ''), '0'), '.') . 'M';
  }
  if ($vNum >= 1000) {
    return rtrim(rtrim(number_format($vNum / 1000, 1, '.', ''), '0'), '.') . 'K';
  }
  return (string) $vNum;
}

function fFormatWhen($pTimestamp) {
  $vNow = time();
  $vDelta = $vNow - (int) $pTimestamp;

  if ($vDelta < 3600) {
    return fFallbackText('time.minutes_ago', '{{count}} min ago', ['count' => max(1, (int) ($vDelta / 60))]);
  }
  if ($vDelta < 86400) {
    return fFallbackText('time.hours_ago', '{{count}} h ago', ['count' => (int) ($vDelta / 3600)]);
  }
  if ($vDelta < 2 * 86400) {
    return fFallbackText('time.one_day_ago', '1 day ago');
  }
  if ($vDelta < 7 * 86400) {
    return fFallbackText('time.days_ago', '{{count}} days ago', ['count' => (int) ($vDelta / 86400)]);
  }
  if ($vDelta < 14 * 86400) {
    return fFallbackText('time.one_week_ago', '1 week ago');
  }
  if ($vDelta < 30 * 86400) {
    return fFallbackText('time.weeks_ago', '{{count}} weeks ago', ['count' => (int) ($vDelta / (7 * 86400))]);
  }
  if ($vDelta < 60 * 86400) {
    return fFallbackText('time.one_month_ago', '1 month ago');
  }
  if ($vDelta < 365 * 86400) {
    return fFallbackText('time.months_ago', '{{count}} months ago', ['count' => (int) ($vDelta / (30 * 86400))]);
  }
  return fFallbackText('time.years_ago', '{{count}} years ago', ['count' => (int) ($vDelta / (365 * 86400))]);
}

function fFormatDay($pTimestamp) {
  $vNow = time();
  $vDelta = $vNow - (int) $pTimestamp;

  if ($vDelta < 86400)       return fFallbackText('day.today', 'Today');
  if ($vDelta < 2 * 86400)   return fFallbackText('day.yesterday', 'Yesterday');
  if ($vDelta < 3 * 86400)   return fFallbackText('day.two_days_ago', '2 days ago');
  if ($vDelta < 7 * 86400)   return fFallbackText('day.this_week', 'This week');
  if ($vDelta < 14 * 86400)  return fFallbackText('day.last_week', 'Last week');
  if ($vDelta < 30 * 86400)  return fFallbackText('day.this_month', 'This month');
  return fFallbackText('day.older', 'Older');
}

function fFormatDuration($pSeconds) {
  $vS = max(0, (int) $pSeconds);
  $vH = (int) ($vS / 3600);
  $vM = (int) (($vS % 3600) / 60);
  $vSeconds = $vS % 60;
  if ($vH > 0) {
    return sprintf('%d:%02d:%02d', $vH, $vM, $vSeconds);
  }
  return sprintf('%d:%02d', $vM, $vSeconds);
}

function fDurationToSeconds($pDuration) {
  $aParts = array_map('intval', explode(':', $pDuration));
  if (count($aParts) === 3) return $aParts[0] * 3600 + $aParts[1] * 60 + $aParts[2];
  if (count($aParts) === 2) return $aParts[0] * 60 + $aParts[1];
  return (int) $pDuration;
}
