<?php

include "../../../inc/includes.php";
if (preg_match('/[a-z]/i', $_REQUEST['ddtype']) !== 1) {
   throw new \RuntimeException(sprintf('Invalid itemtype "%"', $_REQUEST['ddtype']));
}
$path = PLUGINFIELDS_FRONT_PATH . '/' . $_REQUEST['ddtype'] . '.php';
require_once $path;
