<?php

require_once __DIR__ . '/inc/routes.php';

function fRouterPlainResponse($pStatus, $pMessage) {
  http_response_code($pStatus);
  header('Content-Type: text/plain; charset=UTF-8');
  header('Cache-Control: no-store');
  header('X-Content-Type-Options: nosniff');
  echo $pMessage;
}

try {
  $vRequestPath = fRouteRequestPath();

  if ($vRequestPath === '/api' || $vRequestPath === '/api/') {
    header('Location: /api/doc/', true, 302);
    return;
  }

  if (strncmp($vRequestPath, '/api/', 5) === 0) {
    $dApiMatch = fRouteMatchApi($vRequestPath);
    if ($dApiMatch === null) {
      fRouterPlainResponse(404, 'Not Found');
      return;
    }

    $vRequestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $aMethods = $dApiMatch['route']['methods'];
    if (!in_array($vRequestMethod, $aMethods, true)) {
      header('Allow: ' . implode(', ', $aMethods));
      fRouterPlainResponse(405, 'Method Not Allowed');
      return;
    }

    foreach ($dApiMatch['params'] as $vName => $vValue) {
      $_GET[$vName] = $vValue;
    }
    require __DIR__ . '/' . $dApiMatch['route']['handler'];
    return;
  }

  require __DIR__ . '/index.php';
} catch (Throwable $vE) {
  error_log('Request routing failed: ' . $vE->getMessage());
  if (!headers_sent()) {
    fRouterPlainResponse(500, 'Internal Server Error');
  }
}
