<?php

define('cBasePath',         __DIR__);
define('cFrontendPath',     dirname(__DIR__) . '/frontend');
define('cAppName',          'MyOwnTube');
define('cDbPath',           cBasePath . '/db/mytube.db');
define('cVideosPath',       cBasePath . '/videos');
define('cDefaultLocale',    'en-us');
define('cSupportedLocales', ['en-gb', 'en-us', 'es-ar', 'es-es']);
define('cLocaleNames', [
  'en-gb' => 'English (UK)',
  'en-us' => 'English (US)',
  'es-ar' => 'Español (Argentina)',
  'es-es' => 'Español (España)',
]);
define('cAdminEmail',       'admin@mytube.home.arpa');
define('cAdminName',        'Administrator');
define('cAdminHandle',      '@admin');
define('cAdminInitial',     'A');
define('cAdminPasswordEnvironment', 'MYTUBE_ADMIN_PASSWORD');
define('cPasswordAlgorithm',          PASSWORD_ARGON2ID);
define('cMinimumPasswordLength',    12);
define('cMaximumPasswordLength',    4096);
define('cMaximumVideoBytes',        10 * 1024 * 1024 * 1024);
define('cLoginMaximumAttempts',     8);
define('cLoginWindowSeconds',       900);
define('cPageSize',                  24);

date_default_timezone_set('Europe/Madrid');

if (function_exists('mb_internal_encoding')) {
  mb_internal_encoding('UTF-8');
}
