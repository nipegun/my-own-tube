<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/inc/helpers.php';
require_once __DIR__ . '/inc/openapi.php';

header_remove('X-Powered-By');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-cache');

try {
  $dSpec = fOpenApiSpec();

  if (substr(fRouteRequestPath(), -strlen('/openapi.json')) === '/openapi.json') {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($dSpec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit(0);
  }

  header("Content-Security-Policy: default-src 'self'; style-src 'self'; script-src 'none'; object-src 'none'; frame-ancestors 'none'; base-uri 'self'; form-action 'none'");
  header('X-Frame-Options: DENY');
  header('Content-Type: text/html; charset=UTF-8');
  $dTags = fOpenApiTags();
  require cFrontendPath . '/views/api-doc.php';
} catch (Throwable $vE) {
  error_log('API documentation failed: ' . $vE->getMessage());
  if (!headers_sent()) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
  }
  echo 'The API documentation could not be generated.';
}
