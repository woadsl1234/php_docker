<?php
define('APP_DIR', realpath('./'));
defined('DS') or define('DS', DIRECTORY_SEPARATOR);
define('INCL_DIR', APP_DIR.DS.'protected'.DS.'include');
define ('XS_APP_ROOT', APP_DIR . '/protected/resources/xs_app_root');

require('vendor/autoload.php');
require(INCL_DIR.DS.'core.php');