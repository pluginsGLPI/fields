<?php
include ("../../../inc/includes.php");

PluginFieldsField::AjaxForDomContainer($_REQUEST['itemtype'], 
                                       $_REQUEST['items_id'], 
                                       isset($_REQUEST['type'])?$_REQUEST['type']:"dom",
                                       isset($_REQUEST['subtype'])?$_REQUEST['subtype']:"");