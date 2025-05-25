<?php

global $CFG_GLPI;

define('GLPI_ROOT', dirname(dirname(dirname(__DIR__))));
define("GLPI_CONFIG_DIR", GLPI_ROOT . "/tests/config");

if (file_exists("vendor/autoload.php")) {
    require_once "vendor/autoload.php";
}

include GLPI_ROOT . "/inc/includes.php";
//include_once GLPI_ROOT . '/tests/GLPITestCase.php';
//include_once GLPI_ROOT . '/tests/DbTestCase.php';

$plugin = new \Plugin();
$plugin->checkPluginState('fields');
$plugin->getFromDBbyDir('fields');

if (!plugin_fields_check_prerequisites()) {
    echo "\nPrerequisites are not met!";
    die(1);
}

if (!$plugin->isInstalled('fields')) {
    $plugin->install($plugin->getID());
}
if (!$plugin->isActivated('fields')) {
    $plugin->activate($plugin->getID());
}
