<?php
include ("../../../inc/includes.php");

if (empty($_GET["id"])) {
   $_GET["id"] = "";
}

Session::checkRight('entity', READ);

$field = new PluginFieldsField;

if (isset($_POST["add"])) {
   $field->check(-1, CREATE, $_POST);
   $field->add($_POST);

} elseif (isset($_POST["delete"])) {
   $field->check($_POST['id'], DELETE);
   $field->delete($_POST);

} elseif (isset($_REQUEST["purge"])) {
   $field->check($_REQUEST['id'], PURGE);
   $field->delete($_REQUEST,1);

} elseif (isset($_POST["update"])) {
   $field->check($_POST['id'], UPDATE);
   $field->update($_POST);
}

Html::back();
?>