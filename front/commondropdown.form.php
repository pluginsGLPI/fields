<?php

include "../../../inc/includes.php";
$path = PLUGINFIELDS_FRONT_PATH . '/' . $_REQUEST['ddtype'] . '.form.php';
$realpath = str_replace( "\\", "/", realpath($path));
$frontpath = str_replace( "\\", "/", PLUGINFIELDS_FRONT_PATH );
if (strpos($realpath, $frontpath) === 0) {
    include_once $path;
} else {
    throw new \RuntimeException('Attempt to load unsecure or missing ' . $path .'!');
}
