<?php
include ("../../../inc/includes.php");


if (isset($_POST["update"])) {
   PluginFieldsProfile::updateProfile($_POST);
}
Html::back();
