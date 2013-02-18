<?php
define('GLPI_ROOT', '../../..');
include (GLPI_ROOT."/inc/includes.php");

PluginFieldsField::AjaxForDomContainer($_REQUEST['itemtype'], $_REQUEST['items_id']);