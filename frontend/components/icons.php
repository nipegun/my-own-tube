<?php

function fIcon($pPath, $pOptions = []) {
  $vSize        = $pOptions['size']        ?? 24;
  $vFill        = $pOptions['fill']        ?? 'none';
  $vStroke      = $pOptions['stroke']      ?? 'currentColor';
  $vStrokeWidth = $pOptions['strokeWidth'] ?? 2;
  $vStyle       = $pOptions['style']       ?? '';

  $vStyleAttr = $vStyle !== '' ? ' style="' . fE($vStyle) . '"' : '';

  return '<svg xmlns="http://www.w3.org/2000/svg" width="' . (int) $vSize . '" height="' . (int) $vSize . '" viewBox="0 0 24 24"'
    . ' fill="' . fE($vFill) . '" stroke="' . fE($vStroke) . '" stroke-width="' . fE($vStrokeWidth) . '" stroke-linecap="round" stroke-linejoin="round"'
    . $vStyleAttr . '>' . $pPath . '</svg>';
}

function fIconMenu       ($p = []) { return fIcon('<line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>', $p); }
function fIconSearch     ($p = []) { return fIcon('<circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.5" y2="16.5"/>', $p); }
function fIconMic        ($p = []) { return fIcon('<rect x="9" y="3" width="6" height="12" rx="3"/><path d="M5 11a7 7 0 0 0 14 0"/><line x1="12" y1="18" x2="12" y2="22"/><line x1="8" y1="22" x2="16" y2="22"/>', $p); }
function fIconBell       ($p = []) { return fIcon('<path d="M6 8a6 6 0 1 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10 21a2 2 0 0 0 4 0"/>', $p); }
function fIconCreate     ($p = []) { return fIcon('<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>', $p); }
function fIconHome       ($p = []) { return fIcon('<path d="M3 11l9-8 9 8"/><path d="M5 10v10h14V10"/>', $p); }
function fIconTrending   ($p = []) { return fIcon('<polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/>', $p); }
function fIconLibrary    ($p = []) { return fIcon('<rect x="3" y="4" width="14" height="16" rx="1"/><path d="M7 4v16"/><path d="M21 6v14"/>', $p); }
function fIconHeart      ($p = []) { return fIcon('<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>', $p); }
function fIconHeartFilled($p = []) { return fIcon('<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>', array_merge($p, ['fill' => 'currentColor'])); }
function fIconClock      ($p = []) { return fIcon('<circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 14"/>', $p); }
function fIconHistory    ($p = []) { return fIcon('<path d="M3 12a9 9 0 1 0 3-6.7"/><polyline points="3 3 3 9 9 9"/><polyline points="12 7 12 12 15 14"/>', $p); }
function fIconPlaylist   ($p = []) { return fIcon('<line x1="3" y1="6" x2="15" y2="6"/><line x1="3" y1="12" x2="15" y2="12"/><line x1="3" y1="18" x2="9" y2="18"/><polygon points="17 14 21 17 17 20 17 14" fill="currentColor" stroke="none"/>', $p); }
function fIconPlay       ($p = []) { return fIcon('<polygon points="6 4 20 12 6 20 6 4"/>', array_merge($p, ['fill' => 'currentColor', 'stroke' => 'none'])); }
function fIconPause      ($p = []) { return fIcon('<rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/>', array_merge($p, ['fill' => 'currentColor', 'stroke' => 'none'])); }
function fIconNext       ($p = []) { return fIcon('<polygon points="4 4 14 12 4 20 4 4"/><rect x="16" y="4" width="3" height="16"/>', array_merge($p, ['fill' => 'currentColor', 'stroke' => 'none'])); }
function fIconVolume     ($p = []) { return fIcon('<polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5" fill="currentColor" stroke="currentColor"/><path d="M15.54 8.46a5 5 0 0 1 0 7.07"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14"/>', $p); }
function fIconFullscreen ($p = []) { return fIcon('<path d="M8 3H5a2 2 0 0 0-2 2v3"/><path d="M21 8V5a2 2 0 0 0-2-2h-3"/><path d="M3 16v3a2 2 0 0 0 2 2h3"/><path d="M16 21h3a2 2 0 0 0 2-2v-3"/>', $p); }
function fIconSettings   ($p = []) { return fIcon('<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 0 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.9.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.5-1H3a2 2 0 0 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.9l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.9.3h.1a1.7 1.7 0 0 0 1-1.5V3a2 2 0 0 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.9-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.9v.1a1.7 1.7 0 0 0 1.5 1H21a2 2 0 0 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>', $p); }
function fIconMore       ($p = []) { return fIcon('<circle cx="12" cy="6" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="12" cy="18" r="1.5"/>', array_merge($p, ['fill' => 'currentColor', 'stroke' => 'none'])); }
function fIconMoreH      ($p = []) { return fIcon('<circle cx="6" cy="12" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="18" cy="12" r="1.5"/>', array_merge($p, ['fill' => 'currentColor', 'stroke' => 'none'])); }
function fIconThumbUp    ($p = []) { return fIcon('<path d="M7 10v12"/><path d="M15 5.88L14 10h5.83a2 2 0 0 1 1.92 2.56l-2.33 8A2 2 0 0 1 17.5 22H7V10l4.3-8.6a1 1 0 0 1 1.7.25c.24.75.08 1.6-.4 2.22L12 5.88z"/>', $p); }
function fIconThumbDown  ($p = []) { return fIcon('<path d="M17 14V2"/><path d="M9 18.12L10 14H4.17a2 2 0 0 1-1.92-2.56l2.33-8A2 2 0 0 1 6.5 2H17v12l-4.3 8.6a1 1 0 0 1-1.7-.25c-.24-.75-.08-1.6.4-2.22L12 18.12z"/>', $p); }
function fIconShare      ($p = []) { return fIcon('<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/>', $p); }
function fIconDownload   ($p = []) { return fIcon('<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>', $p); }
function fIconSave       ($p = []) { return fIcon('<path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/>', $p); }
function fIconChevronDown($p = []) { return fIcon('<polyline points="6 9 12 15 18 9"/>', $p); }
function fIconChevronRight($p = []){ return fIcon('<polyline points="9 18 15 12 9 6"/>', $p); }
function fIconChevronLeft($p = []) { return fIcon('<polyline points="15 18 9 12 15 6"/>', $p); }
function fIconX          ($p = []) { return fIcon('<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>', $p); }
function fIconClose      ($p = []) { return fIcon('<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>', $p); }
function fIconCheck      ($p = []) { return fIcon('<polyline points="20 6 9 17 4 12"/>', $p); }
function fIconFlag       ($p = []) { return fIcon('<line x1="4" y1="22" x2="4" y2="15"/><path d="M4 15V4s2-2 5-2 5 2 8 2 4-1 4-1v10s-1 1-4 1-5-2-8-2-5 2-5 2"/>', $p); }
function fIconCaptions   ($p = []) { return fIcon('<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M7 15h3"/><path d="M14 15h3"/><path d="M7 11h10"/>', $p); }
function fIconTheater    ($p = []) { return fIcon('<rect x="2" y="4" width="20" height="16" rx="2"/>', $p); }
function fIconPiP        ($p = []) { return fIcon('<rect x="2" y="4" width="20" height="14" rx="2"/><rect x="13" y="11" width="8" height="7" rx="1" fill="currentColor"/>', $p); }
function fIconMyList     ($p = []) { return fIcon('<path d="M3 6h13"/><path d="M3 12h13"/><path d="M3 18h9"/><path d="M17 15l4 3-4 3z" fill="currentColor" stroke="none"/>', $p); }
function fIconEye        ($p = []) { return fIcon('<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"/><circle cx="12" cy="12" r="3"/>', $p); }
function fIconEdit       ($p = []) { return fIcon('<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4z"/>', $p); }
function fIconFilter     ($p = []) { return fIcon('<polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>', $p); }
function fIconSparkles   ($p = []) { return fIcon('<path d="M12 3l2.5 6.5L21 12l-6.5 2.5L12 21l-2.5-6.5L3 12l6.5-2.5z"/>', $p); }
function fIconGlobe      ($p = []) { return fIcon('<circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>', $p); }
function fIconHelp       ($p = []) { return fIcon('<circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/>', $p); }
function fIconLogOut     ($p = []) { return fIcon('<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>', $p); }
function fIconTrash      ($p = []) { return fIcon('<polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/>', $p); }
function fIconPlus       ($p = []) { return fIcon('<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>', $p); }
function fIconLock       ($p = []) { return fIcon('<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>', $p); }
function fIconRefresh    ($p = []) { return fIcon('<path d="M21 12a9 9 0 0 1-15 6.7L3 16"/><path d="M3 21v-5h5"/><path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M21 3v5h-5"/>', $p); }
function fIconSort       ($p = []) { return fIcon('<line x1="3" y1="6" x2="17" y2="6"/><line x1="3" y1="12" x2="13" y2="12"/><line x1="3" y1="18" x2="9" y2="18"/><polyline points="19 9 19 18 22 15"/><polyline points="19 18 16 15"/>', $p); }

function fIconVerified($pSize = 14) {
  return '<svg width="' . (int) $pSize . '" height="' . (int) $pSize . '" viewBox="0 0 24 24" fill="#b5b8c1" style="display:inline-block;vertical-align:middle">'
    . '<path d="M12 1l2.5 3.2 4 .6 1 4-2.5 3.2 2.5 3.2-1 4-4 .6L12 23l-2.5-3.2-4-.6-1-4L7 12 4.5 8.8l1-4 4-.6z"/>'
    . '<path d="M8 12l3 3 5-6" stroke="#0e0f12" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/>'
    . '</svg>';
}
