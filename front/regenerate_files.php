<?php
include ("../../../inc/includes.php");
include ("../hook.php");

Session::checkRight('entity', READ);

regenerateFiles();

Html::back();