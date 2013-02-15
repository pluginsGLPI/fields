<?php
define('GLPI_ROOT', '../../..');
include (GLPI_ROOT."/inc/includes.php");

if (empty($_GET["id"])) {
   $_GET["id"] = "";
}

$container = new PluginFieldsContainer;

if (isset($_POST["add"])) {

   $container->check(-1,'w',$_POST);
   $newID = $container->add($_POST);
   Html::redirect($CFG_GLPI["root_doc"]."/plugins/fields/front/container.form.php?id=$newID");

} elseif (isset($_POST["delete"])) {
   $container->check($_POST['id'],'d');
   $ok = $container->delete($_POST);
   Html::redirect($CFG_GLPI["root_doc"]."/plugins/fields/front/container.php");

} elseif (isset($_REQUEST["purge"])) {
   $container->check($_REQUEST['id'],'d');
   $container->delete($_REQUEST,1);
   Html::redirect($CFG_GLPI["root_doc"]."/plugins/fields/front/container.php");

} elseif (isset($_POST["update"])) {
   $container->check($_POST['id'],'w');
   $container->update($_POST);
   Html::back();

} else {
   Html::header($LANG['fields']["title"][1], $_SERVER['PHP_SELF'], "plugins", "container");
   $container->showForm($_GET["id"]);
   Html::footer();
}

Html::footer();
?>
