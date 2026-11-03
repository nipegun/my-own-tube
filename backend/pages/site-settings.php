<?php

$dLocales = fLocaleNames();
$vSiteLocale = fSiteDefaultLocale();
$vRegistrationEnabled = fRegistrationEnabled();

require cFrontendPath . '/views/pages/site-settings.php';
