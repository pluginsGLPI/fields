<?php

include "../../../inc/includes.php";
$path = PLUGINFIELDS_FRONT_PATH . '/' . $_GET['ddtype'] . '.form.php';
if (strpos(realpath($path), PLUGINFIELDS_FRONT_PATH) === 0) {
    include_once $path;
} else {
    throw new \RuntimeException('Attempt to load unsecure or missing ' . $path .'!');
}
