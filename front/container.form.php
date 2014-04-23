<?php
include ("../../../inc/includes.php");

if (empty($_GET["id"])) {
   $_GET["id"] = "";
}

$container = new PluginFieldsContainer;

$url = $CFG_GLPI["root_doc"]."/plugins/fields/front";

if (isset($_POST["add"])) {
   $container->check(-1,'w',$_POST);
   $newID = $container->add($_POST);
   Html::redirect($url."/container.form.php?id=$newID");

} elseif (isset($_POST["delete"])) {
   $container->check($_POST['id'],'d');
   $ok = $container->delete($_POST);
   Html::redirect($url."/container.php");

} elseif (isset($_REQUEST["purge"])) {
   $container->check($_REQUEST['id'],'d');
   $container->delete($_REQUEST,1);
   Html::redirect($url."/container.php");

} elseif (isset($_POST["update"])) {
   $container->check($_POST['id'],'w');
   $container->update($_POST);
   Html::back();

} elseif (isset($_POST["update_fields_values"])) {
   $container->updateFieldsValues($_REQUEST);
   Html::back();

} else {
   Html::header(__("Additionnal fields", "fields"), $_SERVER['PHP_SELF'], 
                "plugins", "fields", "container");
   $container->showForm($_GET["id"]);
   Html::footer();
}

Html::footer();
