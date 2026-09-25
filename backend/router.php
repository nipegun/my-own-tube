<?php

$vRequestUri = $_SERVER['REQUEST_URI'] ?? '/';
$vRequestPath = is_string($vRequestUri) ? parse_url($vRequestUri, PHP_URL_PATH) : false;
$vRequestPath = is_string($vRequestPath) ? rawurldecode($vRequestPath) : '';

if ($vRequestPath === '/' || $vRequestPath === '/index.php') {
  require __DIR__ . '/index.php';
  return;
}
if ($vRequestPath === '/media.php' || $vRequestPath === '/thumb.php') {
  require __DIR__ . $vRequestPath;
  return;
}
if (preg_match('#^/actions/([a-z0-9-]+)\.php$#D', $vRequestPath, $aRoute) === 1) {
  $vActionPath = __DIR__ . '/actions/' . $aRoute[1] . '.php';
  if (is_file($vActionPath)) {
    require $vActionPath;
    return;
  }
}

http_response_code(404);
header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
echo 'Not Found';
