<?php

define('GLPI_ROOT', '../..');
include (GLPI_ROOT."/inc/includes.php");

//change mimetype
header("Content-type: application/javascript");

if ($plugin->isInstalled("fields") && $plugin->isActivated("fields")) {
   PluginFieldsField::showForDomContainer();
}