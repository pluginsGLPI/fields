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
   Html::back();
} else if (isset($_POST["delete"])) {
   $field->check($_POST['id'], DELETE);
   $field->delete($_POST);
   Html::back();
} else if (isset($_REQUEST["purge"])) {
   $field->check($_REQUEST['id'], PURGE);
   $field->delete($_REQUEST, 1);
   $field->redirectToList();
} else if (isset($_POST["update"])) {
   $field->check($_POST['id'], UPDATE);
   $field->update($_POST);
   Html::back();
} else if (isset($_GET["id"])) {
   $field->check($_GET['id'], READ);

   Html::header(PluginFieldsField::getTypeName(1), $_SERVER['PHP_SELF']);

   $field->getFromDB($_GET['id']);
   $field->display(['id'        => $_GET['id'],
                    'parent_id' => $field->fields['plugin_fields_containers_id']]);

   Html::footer();
}
