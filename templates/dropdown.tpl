<?php

define('GLPI_ROOT', '../../..');
include (GLPI_ROOT . "/inc/includes.php");

Html::header(%%CLASSNAME%%::getTypeName(),$_SERVER['PHP_SELF'], "plugins", "fields", 
             "%%CLASSNAME%%");

Search::show('%%CLASSNAME%%');

Html::footer();
