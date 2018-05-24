<?php
// fix empty CFG_GLPI on boostrap; see https://github.com/sebastianbergmann/phpunit/issues/325
global $CFG_GLPI;

//define plugin paths
define("PLUGINFIELDS_DOC_DIR", __DIR__ . "/generated_test_data");

define('GLPI_ROOT', dirname(dirname(dirname(__DIR__))));
define("GLPI_CONFIG_DIR", GLPI_ROOT . "/tests");
include GLPI_ROOT . "/inc/includes.php";
include_once GLPI_ROOT . '/tests/GLPITestCase.php';
include_once GLPI_ROOT . '/tests/DbTestCase.php';

//install plugin
$plugin = new \Plugin();
$plugin->getFromDBbyDir('fields');
//check from prerequisites as Plugin::install() does not!
if (!plugin_fields_check_prerequisites()) {
   echo "\nPrerequisites are not met!";
   die(1);
}
if (!$plugin->isInstalled('fields')) {
   call_user_func([$plugin, 'install'], $plugin->getID());
}
if (!$plugin->isActivated('fields')) {
   call_user_func([$plugin, 'activate'], $plugin->getID());
}

include_once __DIR__ . '/FieldsDbTestCase.php';
