<?php
include ("../../../inc/includes.php");

if (empty($_GET["id"])) {
   $_GET["id"] = "";
}

$container = new PluginFieldsContainer;

if (isset($_POST["add"])) {
   $container->check(-1, CREATE, $_POST);
   $newID = $container->add($_POST);
   Html::redirect(PLUGINFIELDS_WEB_DIR."/front/container.form.php?id=$newID");

} else if (isset($_POST["delete"])) {
   $container->check($_POST['id'], DELETE);
   $ok = $container->delete($_POST);
   Html::redirect(PLUGINFIELDS_WEB_DIR."/front/container.php");

} else if (isset($_REQUEST["purge"])) {
   $container->check($_REQUEST['id'], PURGE);
   $container->delete($_REQUEST, 1);
   Html::redirect(PLUGINFIELDS_WEB_DIR."/front/container.php");

} else if (isset($_POST["update"])) {
   $container->check($_POST['id'], UPDATE);
   $container->update($_POST);
   Html::back();

} else if (isset($_POST["update_fields_values"])) {
   $container->updateFieldsValues($_REQUEST, $_REQUEST['itemtype'], false);
   Html::back();

} else {
   Html::header(__("Additionnal fields", "fields"), $_SERVER['PHP_SELF'],
                "config", "pluginfieldsmenu", "fieldscontainer");
   $container->display(['id' => $_GET["id"]]);
   Html::footer();
}
