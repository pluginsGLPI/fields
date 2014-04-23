<?php

include ("../../../inc/includes.php");

Html::header(%%CLASSNAME%%::getTypeName(), $_SERVER['PHP_SELF'], "plugins", "fields", 
             "%%CLASSNAME%%");

Search::show('%%CLASSNAME%%');

Html::footer();
