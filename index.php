<?php

require 'module/Google_doc.php';

// set up
$code = (isset($_GET['code'])) ? $_GET['code'] : null;
$google = new Google_Doc($code);
$new = $google->exportDocumentToHtml('18c_hh_B4CfZecWaBS_BmfId8mR5aU2epwqAc9_TkRPc');

echo $new;
