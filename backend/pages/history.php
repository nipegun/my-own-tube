<?php

$vUserId = fUserId();

$dPagination = fPaginate(
  'SELECT COUNT(*) FROM history h INNER JOIN videos v ON v.id = h.video_id WHERE h.user_id = ? AND v.is_available = 1',
  'SELECT v.*, h.progress, h.watched_at FROM history h
  INNER JOIN videos v ON v.id = h.video_id
  WHERE h.user_id = ? AND v.is_available = 1
  ORDER BY h.watched_at DESC, v.id',
  [$vUserId]
);
$ldHistory = $dPagination['rows'];

$dGroups = [];
$aOrder = [];
foreach ($ldHistory as $dRow) {
  $vGroup = fFormatDay((int) $dRow['watched_at']);
  if (!isset($dGroups[$vGroup])) {
    $dGroups[$vGroup] = [];
    $aOrder[] = $vGroup;
  }
  $dGroups[$vGroup][] = $dRow;
}

require cFrontendPath . '/views/pages/history.php';
