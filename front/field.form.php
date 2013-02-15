<?php
define('GLPI_ROOT', '../../..');
include (GLPI_ROOT."/inc/includes.php");

if (empty($_GET["id"])) {
   $_GET["id"] = "";
}

$field = new PluginFieldsField;

if (isset($_POST["add"])) {

   $field->check(-1,'w',$_POST);
   $newID = $field->add($_POST);
   Html::redirect($CFG_GLPI["root_doc"]."/plugins/fields/front/field.form.php?id=$newID");

} elseif (isset($_POST["delete"])) {
   $field->check($_POST['id'],'d');
   $ok = $field->delete($_POST);
   Html::redirect($CFG_GLPI["root_doc"]."/plugins/fields/front/field.php");

} elseif (isset($_REQUEST["purge"])) {
   $field->check($_REQUEST['id'],'d');
   $field->delete($_REQUEST,1);
   Html::redirect($CFG_GLPI["root_doc"]."/plugins/fields/front/field.php");

} elseif (isset($_POST["update"])) {
   $field->check($_POST['id'],'w');
   $field->update($_POST);
   Html::back();

} else {
   Html::header($LANG['fields']["title"][1], $_SERVER['PHP_SELF'], "plugins", "field");
   $field->showForm($_GET["id"]);
   Html::footer();
}

Html::footer();
?>
