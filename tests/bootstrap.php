<?php
// fix empty CFG_GLPI on boostrap; see https://github.com/sebastianbergmann/phpunit/issues/325
global $CFG_GLPI;

define('TU_USER', true); // Used by GLPI::initLogger() to create TestHandler for logs

//define plugin paths
define("PLUGINFIELDS_DOC_DIR", __DIR__ . "/generated_test_data");

define('GLPI_ROOT', dirname(dirname(dirname(__DIR__))));
define("GLPI_CONFIG_DIR", GLPI_ROOT . "/tests");

define(
   'PLUGINS_DIRECTORIES',
   [
      GLPI_ROOT . '/plugins',
      GLPI_ROOT . '/tests/fixtures/plugins',
   ]
);

include GLPI_ROOT . "/inc/includes.php";
include_once GLPI_ROOT . '/tests/GLPITestCase.php';
include_once GLPI_ROOT . '/tests/DbTestCase.php';

//install plugin
$plugin = new \Plugin();
$plugin->checkStates(true);
$plugin->getFromDBbyDir('fields');
if (!$plugin->isInstalled('fields')) {
   $plugin->install($plugin->getID());
}
if (!$plugin->isActivated('fields')) {
   $plugin->activate($plugin->getID());
}

include_once __DIR__ . '/FieldsDbTestCase.php';
