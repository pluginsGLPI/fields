<?php
define('GLPI_ROOT', '../../..');
include (GLPI_ROOT."/inc/includes.php");


if (isset($_POST["update"])) {
   PluginFieldsProfile::updateProfile($_POST);
}
Html::back();
?>