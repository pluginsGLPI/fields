<?php

include ("../../inc/includes.php");

//change mimetype
header("Content-type: application/javascript");

if ($plugin->isInstalled("fields") && $plugin->isActivated("fields")) {
   PluginFieldsField::showForDomContainer();
   PluginFieldsField::showForDomtabContainer();
}