<?php
include ("../../../inc/includes.php");
include ("../hook.php");

Session::checkRight('entity', READ);

$ID = null;
if (isset($_GET['id'])) {
   $ID = $_GET['id'];
}

if (plugin_fields_exportBlockAsYaml($ID)) {
   $filename = "fields_conf.yaml";
   $path = GLPI_TMP_DIR."/fields_conf.yaml";
   Toolbox::sendFile($path, $filename);
} else {
   Session::addMessageAfterRedirect("No data to export", false, INFO);
   Html::back();
}

