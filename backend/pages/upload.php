<?php

$vVideosPath = cVideosPath;
$aExtensionesVideo = fVideoAllowedExtensions();
$vAcceptVideo = 'video/*,.' . implode(',.', $aExtensionesVideo);

require cFrontendPath . '/views/pages/upload.php';
