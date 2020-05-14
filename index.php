<?php

require 'module/Google_doc.php';

// set up
$code = (isset($_GET['code'])) ? $_GET['code'] : null;
$google = new Google_Doc($code);
