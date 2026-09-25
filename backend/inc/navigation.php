<?php

function fNavigationData() {
  $vQuery = fParam('q', '');
  $vQuery = is_string($vQuery) ? mb_substr(trim($vQuery), 0, 200) : '';
  $ldNotifications = fAll('SELECT * FROM notifications ORDER BY created_at DESC');
  $ldSuggestions = [];
  $dLocales = fLocaleNames();
  $vLocale = fCurrentLocale();
  $vBack = $_SERVER['REQUEST_URI'] ?? fUrl('home');
  if ($vQuery !== '') {
    $vLike = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $vQuery) . '%';
    $ldHistorySuggestions = fAll(
      'SELECT text, 1 AS is_history FROM search_history WHERE user_id = ? AND text LIKE ? ESCAPE \'\\\' ORDER BY searched_at DESC LIMIT 6',
      [fUserId(), $vLike]
    );
    $ldCuratedSuggestions = fAll(
      'SELECT text, 0 AS is_history FROM search_suggestions WHERE text LIKE ? ESCAPE \'\\\' ORDER BY display_order LIMIT 6',
      [$vLike]
    );
    $dSeenSuggestions = [];
    foreach (array_merge($ldHistorySuggestions, $ldCuratedSuggestions) as $dSuggestion) {
      $vSuggestionKey = mb_strtolower((string) $dSuggestion['text']);
      if (isset($dSeenSuggestions[$vSuggestionKey])) {
        continue;
      }
      $dSeenSuggestions[$vSuggestionKey] = true;
      $ldSuggestions[] = $dSuggestion;
      if (count($ldSuggestions) >= 6) {
        break;
      }
    }
  }
  $vUnread = 0;
  foreach ($ldNotifications as $dNotification) {
    if ((int) $dNotification['unread'] === 1) $vUnread++;
  }

  return compact('vQuery', 'ldNotifications', 'ldSuggestions', 'dLocales', 'vLocale', 'vBack', 'vUnread');
}
