<?php
include ("../../../inc/includes.php");
include ("../hook.php");

Session::checkRight('entity', READ);

plugin_fields_checkFiles(true);

Html::back();