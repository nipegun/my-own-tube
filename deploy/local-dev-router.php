<?php

$vRequestUri = $_SERVER['REQUEST_URI'] ?? '/';
$vRequestPath = is_string($vRequestUri) ? parse_url($vRequestUri, PHP_URL_PATH) : false;
$vRequestPath = is_string($vRequestPath) ? rawurldecode($vRequestPath) : '/';
$vRequestPath = str_replace('\\', '/', $vRequestPath);
$vBasename = strtolower(basename($vRequestPath));

$vPrivateDirectory = preg_match('#^/(?:db|inc|lang|pages|videos|backend|frontend|deploy|doc|tests)(?:/|$)#i', $vRequestPath) === 1;
$vPrivateEntryPoint = preg_match('/^(?:config|seed|.*-cli)\.php$/i', $vBasename) === 1;
$vDotPath = preg_match('#(?:^|/)\.#', $vRequestPath) === 1;
$vTraversalPath = preg_match('#(?:^|/)\.\.(?:/|$)#', $vRequestPath) === 1;

if ($vPrivateDirectory || $vPrivateEntryPoint || $vDotPath || $vTraversalPath) {
  http_response_code(404);
  header('Content-Type: text/plain; charset=UTF-8');
  header('Cache-Control: no-store');
  header('X-Content-Type-Options: nosniff');
  echo 'Not Found';
  return true;
}

// Real public files other than PHP scripts (styles.css, ui.js) are served as they are.
$vPublicFile = $_SERVER['DOCUMENT_ROOT'] . $vRequestPath;
if ($vRequestPath !== '/' && substr($vBasename, -4) !== '.php' && is_file($vPublicFile)) {
  return false;
}

// Every other address is answered by the front controller, as Apache does with .htaccess.
require $_SERVER['DOCUMENT_ROOT'] . '/index.php';
return true;
