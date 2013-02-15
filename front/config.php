<?php

define('GLPI_ROOT', '../../..');
include (GLPI_ROOT."/inc/includes.php");

Html::header($LANG['fields']['title'][2], $_SERVER['PHP_SELF'] ,"plugins", "fields", "config");

PluginFieldsConfig::show();

Html::footer();
?>
