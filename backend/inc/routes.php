<?php

// Single source of truth for every public URL. The page router, fUrl(), the
// API dispatcher, and the OpenAPI document at /api/doc/ all read these tables.

function fRoutePages() {
  return [
    'home'          => '/',
    'trending'      => '/trending/',
    'search'        => '/search/',
    'watch'         => '/watch/{id}/',
    'history'       => '/history/',
    'liked'         => '/liked/',
    'watchlater'    => '/watch-later/',
    'playlists'     => '/playlists/',
    'playlist'      => '/playlists/{id}/',
    'upload'        => '/upload/',
    'login'         => '/auth/login/',
    'register'      => '/auth/register/',
    'recover'       => '/auth/recover/',
    'profile'       => '/user/dashboard/profile/',
    'account'       => '/user/dashboard/account/',
    'users'         => '/admin/dashboard/users/',
    'site-settings' => '/admin/dashboard/settings/',
  ];
}

// Section roots without content of their own redirect to their default tab.
function fRouteSectionDefaults() {
  return [
    '/auth/'            => 'login',
    '/user/'            => 'profile',
    '/user/dashboard/'  => 'profile',
    '/admin/'           => 'users',
    '/admin/dashboard/' => 'users',
  ];
}

// Page names used by the former /index.php?page=... URLs.
function fRouteLegacyPages() {
  return [
    'home'       => 'home',
    'trending'   => 'trending',
    'search'     => 'search',
    'watch'      => 'watch',
    'history'    => 'history',
    'liked'      => 'liked',
    'watchlater' => 'watchlater',
    'playlists'  => 'playlists',
    'playlist'   => 'playlist',
    'upload'     => 'upload',
    'login'      => 'login',
    'register'   => 'register',
    'profile'    => 'profile',
    'settings'   => 'account',
    'users'      => 'users',
  ];
}

function fRouteDashboardTabs() {
  return [
    'user' => [
      'profile' => 'dashboard.tab_profile',
      'account' => 'dashboard.tab_account',
    ],
    'admin' => [
      'users'         => 'dashboard.tab_users',
      'site-settings' => 'dashboard.tab_settings',
    ],
  ];
}

function fRouteApi() {
  $dCsrf = ['name' => 'csrf_token', 'type' => 'string', 'required' => true, 'description' => 'Anti-CSRF token rendered in every form of the signed-in session.'];
  $dVideoId = ['name' => 'id', 'type' => 'string', 'required' => true, 'description' => 'Video identifier.'];
  $dRedirect = ['303' => 'Redirects back to the related page and stores a notice for the next page view.'];

  return [
    [
      'path' => '/api/auth/login',
      'methods' => ['POST'],
      'handler' => 'actions/login.php',
      'tag' => 'auth',
      'access' => 'public',
      'summary' => 'Sign in with email and password.',
      'fields' => [
        $dCsrf,
        ['name' => 'email', 'type' => 'string', 'required' => true, 'description' => 'Account email address.'],
        ['name' => 'password', 'type' => 'string', 'required' => true, 'description' => 'Account password.'],
        ['name' => 'next', 'type' => 'string', 'required' => false, 'description' => 'Internal path to open after signing in.'],
      ],
      'responses' => ['303' => 'Redirects to `next` after a successful sign-in, or back to /auth/login/ with a notice.'],
    ],
    [
      'path' => '/api/auth/register',
      'methods' => ['POST'],
      'handler' => 'actions/register.php',
      'tag' => 'auth',
      'access' => 'public',
      'summary' => 'Create a standard account when public registration is enabled.',
      'fields' => [
        $dCsrf,
        ['name' => 'name', 'type' => 'string', 'required' => true, 'description' => 'Display name.'],
        ['name' => 'email', 'type' => 'string', 'required' => true, 'description' => 'Account email address.'],
        ['name' => 'password', 'type' => 'string', 'required' => true, 'description' => 'New password (minimum 12 characters).'],
        ['name' => 'password_confirm', 'type' => 'string', 'required' => true, 'description' => 'Repeated new password.'],
        ['name' => 'next', 'type' => 'string', 'required' => false, 'description' => 'Internal path to open after registering.'],
      ],
      'responses' => ['303' => 'Redirects to `next` with a signed-in session, or back to /auth/register/ with a notice.'],
    ],
    [
      'path' => '/api/auth/logout',
      'methods' => ['POST'],
      'handler' => 'actions/logout.php',
      'tag' => 'auth',
      'access' => 'user',
      'summary' => 'Sign out and rotate the session.',
      'fields' => [$dCsrf],
      'responses' => ['303' => 'Redirects to /auth/login/.'],
    ],
    [
      'path' => '/api/user/profile',
      'methods' => ['POST'],
      'handler' => 'actions/update-profile.php',
      'tag' => 'user',
      'access' => 'user',
      'summary' => 'Change the display name of the current user.',
      'fields' => [$dCsrf, ['name' => 'name', 'type' => 'string', 'required' => true, 'description' => 'New display name (up to 120 characters).']],
      'responses' => ['303' => 'Redirects to /user/dashboard/profile/.'],
    ],
    [
      'path' => '/api/user/password',
      'methods' => ['POST'],
      'handler' => 'actions/change-password.php',
      'tag' => 'user',
      'access' => 'user',
      'summary' => 'Replace the current password and revoke the other sessions.',
      'fields' => [
        $dCsrf,
        ['name' => 'current_password', 'type' => 'string', 'required' => true, 'description' => 'Current password.'],
        ['name' => 'new_password', 'type' => 'string', 'required' => true, 'description' => 'New password (minimum 12 characters).'],
        ['name' => 'new_password_confirm', 'type' => 'string', 'required' => true, 'description' => 'Repeated new password.'],
      ],
      'responses' => ['303' => 'Redirects to /user/dashboard/account/.'],
    ],
    [
      'path' => '/api/user/language',
      'methods' => ['POST'],
      'handler' => 'actions/set-language.php',
      'tag' => 'user',
      'access' => 'user',
      'summary' => 'Save the interface language of the current user.',
      'fields' => [
        $dCsrf,
        ['name' => 'locale', 'type' => 'string', 'required' => true, 'description' => 'One of en-gb, en-us, es-ar, es-es.'],
        ['name' => 'back', 'type' => 'string', 'required' => false, 'description' => 'Internal path to return to.'],
      ],
      'responses' => ['303' => 'Redirects to `back`.'],
    ],
    [
      'path' => '/api/user/searches',
      'methods' => ['POST'],
      'handler' => 'actions/record-search.php',
      'tag' => 'user',
      'access' => 'user',
      'summary' => 'Record a private search suggestion and open the results.',
      'fields' => [$dCsrf, ['name' => 'q', 'type' => 'string', 'required' => false, 'description' => 'Search text (up to 200 characters).']],
      'responses' => ['303' => 'Redirects to /search/?q=...'],
    ],
    [
      'path' => '/api/user/searches/clear',
      'methods' => ['POST'],
      'handler' => 'actions/clear-search-history.php',
      'tag' => 'user',
      'access' => 'user',
      'summary' => 'Delete the private search suggestions of the current user.',
      'fields' => [$dCsrf],
      'responses' => ['303' => 'Redirects to /user/dashboard/account/.'],
    ],
    [
      'path' => '/api/user/likes/toggle',
      'methods' => ['POST'],
      'handler' => 'actions/toggle-like.php',
      'tag' => 'user',
      'access' => 'user',
      'summary' => 'Add or remove a video from the liked videos.',
      'fields' => [$dCsrf, $dVideoId],
      'responses' => $dRedirect,
    ],
    [
      'path' => '/api/user/watch-later/toggle',
      'methods' => ['POST'],
      'handler' => 'actions/toggle-watchlater.php',
      'tag' => 'user',
      'access' => 'user',
      'summary' => 'Add or remove a video from Watch later.',
      'fields' => [$dCsrf, $dVideoId],
      'responses' => $dRedirect,
    ],
    [
      'path' => '/api/user/history/progress',
      'methods' => ['POST'],
      'handler' => 'actions/save-progress.php',
      'tag' => 'user',
      'access' => 'user',
      'summary' => 'Save the playback progress; the first save of a video counts one view.',
      'fields' => [
        $dCsrf,
        ['name' => 'video_id', 'type' => 'string', 'required' => true, 'description' => 'Video identifier.'],
        ['name' => 'progress', 'type' => 'integer', 'required' => true, 'description' => 'Watched percentage from 1 to 100.'],
      ],
      'responses' => [
        '200' => 'JSON `{"ok": true, "progress": 42}`.',
        '401' => 'JSON `{"ok": false}`: no signed-in session.',
        '403' => 'JSON `{"ok": false}`: invalid CSRF token.',
        '500' => 'JSON `{"ok": false}`: the video or its file was not found.',
      ],
    ],
    [
      'path' => '/api/user/history/remove',
      'methods' => ['POST'],
      'handler' => 'actions/remove-history.php',
      'tag' => 'user',
      'access' => 'user',
      'summary' => 'Remove one video from the playback history.',
      'fields' => [$dCsrf, $dVideoId],
      'responses' => $dRedirect,
    ],
    [
      'path' => '/api/user/history/clear',
      'methods' => ['POST'],
      'handler' => 'actions/clear-history.php',
      'tag' => 'user',
      'access' => 'user',
      'summary' => 'Delete the whole playback history of the current user.',
      'fields' => [$dCsrf],
      'responses' => ['303' => 'Redirects to /history/.'],
    ],
    [
      'path' => '/api/user/comments',
      'methods' => ['POST'],
      'handler' => 'actions/post-comment.php',
      'tag' => 'user',
      'access' => 'user',
      'summary' => 'Publish a comment on a video.',
      'fields' => [
        $dCsrf,
        ['name' => 'video_id', 'type' => 'string', 'required' => true, 'description' => 'Video identifier.'],
        ['name' => 'text', 'type' => 'string', 'required' => true, 'description' => 'Comment text (up to 2000 characters).'],
      ],
      'responses' => ['303' => 'Redirects to /watch/{id}/.'],
    ],
    [
      'path' => '/api/user/playlists/create',
      'methods' => ['POST'],
      'handler' => 'actions/create-playlist.php',
      'tag' => 'user',
      'access' => 'user',
      'summary' => 'Create a playlist owned by the current user.',
      'fields' => [
        $dCsrf,
        ['name' => 'name', 'type' => 'string', 'required' => true, 'description' => 'Playlist name (up to 120 characters).'],
        ['name' => 'description', 'type' => 'string', 'required' => false, 'description' => 'Description (up to 500 characters).'],
      ],
      'responses' => ['303' => 'Redirects to /playlists/{id}/ of the new playlist.'],
    ],
    [
      'path' => '/api/user/playlists/update',
      'methods' => ['POST'],
      'handler' => 'actions/update-playlist.php',
      'tag' => 'user',
      'access' => 'user',
      'summary' => 'Rename an owned playlist or change its description.',
      'fields' => [
        $dCsrf,
        ['name' => 'playlist_id', 'type' => 'string', 'required' => true, 'description' => 'Playlist identifier.'],
        ['name' => 'name', 'type' => 'string', 'required' => true, 'description' => 'Playlist name.'],
        ['name' => 'description', 'type' => 'string', 'required' => false, 'description' => 'Description.'],
      ],
      'responses' => ['303' => 'Redirects to /playlists/{id}/.'],
    ],
    [
      'path' => '/api/user/playlists/delete',
      'methods' => ['POST'],
      'handler' => 'actions/delete-playlist.php',
      'tag' => 'user',
      'access' => 'user',
      'summary' => 'Delete an owned playlist.',
      'fields' => [$dCsrf, ['name' => 'playlist_id', 'type' => 'string', 'required' => true, 'description' => 'Playlist identifier.']],
      'responses' => ['303' => 'Redirects to /playlists/.'],
    ],
    [
      'path' => '/api/user/playlists/items',
      'methods' => ['POST'],
      'handler' => 'actions/update-playlist-item.php',
      'tag' => 'user',
      'access' => 'user',
      'summary' => 'Add a video to an owned playlist or remove it.',
      'fields' => [
        $dCsrf,
        ['name' => 'playlist_id', 'type' => 'string', 'required' => true, 'description' => 'Playlist identifier.'],
        ['name' => 'video_id', 'type' => 'string', 'required' => true, 'description' => 'Video identifier.'],
        ['name' => 'operation', 'type' => 'string', 'required' => false, 'description' => '`add` (default) or `remove`.'],
      ],
      'responses' => ['303' => 'Redirects to /watch/{id}/ after adding, or to /playlists/{id}/ after removing.'],
    ],
    [
      'path' => '/api/user/videos/upload',
      'methods' => ['POST'],
      'handler' => 'actions/upload-video.php',
      'tag' => 'user',
      'access' => 'user',
      'summary' => 'Upload a video file, validate it with ffprobe, and import it.',
      'content_type' => 'multipart/form-data',
      'fields' => [
        $dCsrf,
        ['name' => 'video', 'type' => 'file', 'required' => true, 'description' => 'Video file (mp4, m4v, avi, wmv, 3gp, mpg, divx, mp5, mpeg, asf; up to 10 GiB).'],
        ['name' => 'title', 'type' => 'string', 'required' => false, 'description' => 'Title; the file name is used when empty.'],
        ['name' => 'category', 'type' => 'string', 'required' => false, 'description' => 'Category; `Uploads` when empty.'],
        ['name' => 'description', 'type' => 'string', 'required' => false, 'description' => 'Description.'],
      ],
      'responses' => ['303' => 'Redirects to /watch/{id}/ of the new video, or back to /upload/ with a notice.'],
    ],
    [
      'path' => '/api/user/videos/{id}/stream',
      'methods' => ['GET', 'HEAD'],
      'handler' => 'media.php',
      'tag' => 'user',
      'access' => 'user',
      'summary' => 'Stream a video file with HTTP range support.',
      'parameters' => [
        ['name' => 'id', 'in' => 'path', 'type' => 'string', 'required' => true, 'description' => 'Video identifier.'],
        ['name' => 'download', 'in' => 'query', 'type' => 'string', 'required' => false, 'description' => '`1` to download the file as an attachment.'],
        ['name' => 'Range', 'in' => 'header', 'type' => 'string', 'required' => false, 'description' => 'Byte range, for example `bytes=0-1048575`.'],
      ],
      'responses' => [
        '200' => 'Complete video file.',
        '206' => 'Requested byte range.',
        '302' => 'No signed-in session: redirects to /auth/login/.',
        '404' => 'Unknown, unavailable, or missing video.',
        '416' => 'Invalid byte range.',
      ],
    ],
    [
      'path' => '/api/user/videos/{id}/thumbnail',
      'methods' => ['GET', 'HEAD'],
      'handler' => 'thumb.php',
      'tag' => 'user',
      'access' => 'user',
      'summary' => 'Return the JPEG thumbnail of a video.',
      'parameters' => [
        ['name' => 'id', 'in' => 'path', 'type' => 'string', 'required' => true, 'description' => 'Video identifier.'],
      ],
      'responses' => [
        '200' => 'JPEG image.',
        '302' => 'No signed-in session: redirects to /auth/login/.',
        '404' => 'Unknown video or missing thumbnail.',
      ],
    ],
    [
      'path' => '/api/admin/videos/sync',
      'methods' => ['POST'],
      'handler' => 'actions/sync-videos.php',
      'tag' => 'admin',
      'access' => 'admin',
      'summary' => 'Synchronise the videos folder with the catalogue.',
      'fields' => [$dCsrf],
      'responses' => ['303' => 'Redirects to /upload/ with the imported, generated, and error counts.'],
    ],
    [
      'path' => '/api/admin/settings',
      'methods' => ['POST'],
      'handler' => 'actions/save-settings.php',
      'tag' => 'admin',
      'access' => 'admin',
      'summary' => 'Save the default site language and the public registration switch.',
      'fields' => [
        $dCsrf,
        ['name' => 'default_locale', 'type' => 'string', 'required' => true, 'description' => 'One of en-gb, en-us, es-ar, es-es.'],
        ['name' => 'allow_registration', 'type' => 'string', 'required' => false, 'description' => '`1` to allow public registration.'],
      ],
      'responses' => ['303' => 'Redirects to /admin/dashboard/settings/.'],
    ],
    [
      'path' => '/api/admin/users/create',
      'methods' => ['POST'],
      'handler' => 'actions/create-user.php',
      'tag' => 'admin',
      'access' => 'admin',
      'summary' => 'Create an account.',
      'fields' => [
        $dCsrf,
        ['name' => 'name', 'type' => 'string', 'required' => true, 'description' => 'Display name.'],
        ['name' => 'email', 'type' => 'string', 'required' => true, 'description' => 'Email address.'],
        ['name' => 'password', 'type' => 'string', 'required' => true, 'description' => 'Initial password (minimum 12 characters).'],
        ['name' => 'role', 'type' => 'string', 'required' => false, 'description' => '`user` (default) or `admin`.'],
      ],
      'responses' => ['303' => 'Redirects to /admin/dashboard/users/.'],
    ],
    [
      'path' => '/api/admin/users/role',
      'methods' => ['POST'],
      'handler' => 'actions/update-user-role.php',
      'tag' => 'admin',
      'access' => 'admin',
      'summary' => 'Change the role of an account; the last administrator cannot be demoted.',
      'fields' => [
        $dCsrf,
        ['name' => 'user_id', 'type' => 'integer', 'required' => true, 'description' => 'Account identifier.'],
        ['name' => 'role', 'type' => 'string', 'required' => true, 'description' => '`user` or `admin`.'],
      ],
      'responses' => ['303' => 'Redirects to /admin/dashboard/users/.'],
    ],
    [
      'path' => '/api/admin/users/password',
      'methods' => ['POST'],
      'handler' => 'actions/set-user-password.php',
      'tag' => 'admin',
      'access' => 'admin',
      'summary' => 'Set a new password for another account and close its sessions (the recovery path behind /auth/recover/).',
      'fields' => [
        $dCsrf,
        ['name' => 'user_id', 'type' => 'integer', 'required' => true, 'description' => 'Account identifier; the own account is changed from /user/dashboard/account/.'],
        ['name' => 'password', 'type' => 'string', 'required' => true, 'description' => 'New password (minimum 12 characters).'],
      ],
      'responses' => ['303' => 'Redirects to /admin/dashboard/users/.'],
    ],
    [
      'path' => '/api/admin/users/delete',
      'methods' => ['POST'],
      'handler' => 'actions/delete-user.php',
      'tag' => 'admin',
      'access' => 'admin',
      'summary' => 'Delete an account and its private data; the own account and the last administrator are protected.',
      'fields' => [$dCsrf, ['name' => 'user_id', 'type' => 'integer', 'required' => true, 'description' => 'Account identifier.']],
      'responses' => ['303' => 'Redirects to /admin/dashboard/users/.'],
    ],
    [
      'path' => '/api/doc/',
      'methods' => ['GET', 'HEAD'],
      'handler' => 'api-doc.php',
      'tag' => 'doc',
      'access' => 'public',
      'summary' => 'This interactive API reference.',
      'responses' => ['200' => 'HTML document.'],
    ],
    [
      'path' => '/api/doc/openapi.json',
      'methods' => ['GET', 'HEAD'],
      'handler' => 'api-doc.php',
      'tag' => 'doc',
      'access' => 'public',
      'summary' => 'OpenAPI 3.1 description of every /api/ endpoint.',
      'responses' => ['200' => 'JSON document.'],
    ],
  ];
}

function fRoutePathPattern($pPath) {
  $vPattern = preg_quote($pPath, '#');
  $vPattern = str_replace('\\{id\\}', '([^/]+)', $vPattern);
  return '#^' . $vPattern . '$#D';
}

function fRouteRequestPath() {
  $vRequestUri = $_SERVER['REQUEST_URI'] ?? '/';
  $vPath = is_string($vRequestUri) ? parse_url($vRequestUri, PHP_URL_PATH) : false;
  return is_string($vPath) && $vPath !== '' ? $vPath : '/';
}

function fRouteCurrentPage($pPage = null) {
  static $vPage = 'home';

  if ($pPage !== null) {
    $vPage = (string) $pPage;
  }
  return $vPage;
}

function fRouteMatchPage($pPath) {
  foreach (fRoutePages() as $vPage => $vPath) {
    if (preg_match(fRoutePathPattern($vPath), $pPath, $aMatch) === 1) {
      $dParams = [];
      if (isset($aMatch[1])) {
        $dParams['id'] = rawurldecode($aMatch[1]);
      }
      return ['page' => $vPage, 'params' => $dParams];
    }
  }
  return null;
}

function fRouteMatchApi($pPath) {
  $vPath = $pPath;
  if ($vPath !== '/api/doc/' && strlen($vPath) > 5 && substr($vPath, -1) === '/') {
    $vPath = rtrim($vPath, '/');
  }
  if ($vPath === '/api/doc') {
    $vPath = '/api/doc/';
  }

  foreach (fRouteApi() as $dRoute) {
    if (preg_match(fRoutePathPattern($dRoute['path']), $vPath, $aMatch) === 1) {
      $dParams = [];
      if (isset($aMatch[1])) {
        $dParams['id'] = rawurldecode($aMatch[1]);
      }
      return ['route' => $dRoute, 'params' => $dParams];
    }
  }
  return null;
}

function fUrl($pPage, $pParams = []) {
  $dPages = fRoutePages();
  $vPath = $dPages[$pPage] ?? $dPages['home'];
  $dQuery = is_array($pParams) ? $pParams : [];

  if (strpos($vPath, '{id}') !== false) {
    $vId = (string) ($dQuery['id'] ?? '');
    unset($dQuery['id']);
    if ($vId === '') {
      return $pPage === 'playlist' ? $dPages['playlists'] : $dPages['home'];
    }
    $vPath = str_replace('{id}', rawurlencode($vId), $vPath);
  }

  $vQuery = http_build_query($dQuery);
  return $vQuery !== '' ? $vPath . '?' . $vQuery : $vPath;
}

function fApiUrl($pPath, $pId = '') {
  $vPath = (string) $pPath;
  if ($pId !== '') {
    $vPath = str_replace('{id}', rawurlencode((string) $pId), $vPath);
  }
  return $vPath;
}

// Maps an old /index.php?page=... request to its new address.
function fRouteLegacyDestination($pQuery) {
  $dLegacy = fRouteLegacyPages();
  $vLegacyPage = $pQuery['page'] ?? '';
  if (!is_string($vLegacyPage) || !isset($dLegacy[$vLegacyPage])) {
    return fUrl('home');
  }

  $dParams = [];
  foreach ($pQuery as $vName => $vValue) {
    if ($vName !== 'page' && is_string($vName) && is_string($vValue)) {
      $dParams[$vName] = $vValue;
    }
  }
  return fUrl($dLegacy[$vLegacyPage], $dParams);
}
