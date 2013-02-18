<?php
define('GLPI_ROOT', '../../..');
include (GLPI_ROOT."/inc/includes.php");

if (empty($_GET["id"])) {
   $_GET["id"] = "";
}

$field = new PluginFieldsField;

if (isset($_POST["add"])) {
   $field->check(-1,'w',$_POST);
   $field->add($_POST);
} elseif (isset($_POST["delete"])) {
   $field->check($_POST['id'],'d');
   $field->delete($_POST);
} elseif (isset($_REQUEST["purge"])) {
   $field->check($_REQUEST['id'],'d');
   $field->delete($_REQUEST,1);
} elseif (isset($_POST["update"])) {
   $field->check($_POST['id'],'w');
   $field->update($_POST);
}
Html::back();
?>