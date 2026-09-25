<?php

$dLocales = fLocaleNames();
$vUserLocale = fUserLocale();
$vSiteLocale = fSiteDefaultLocale();
$vRegistrationEnabled = fRegistrationEnabled();

require cFrontendPath . '/views/pages/settings.php';
