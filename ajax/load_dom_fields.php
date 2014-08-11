<?php
include ("../../../inc/includes.php");

if (isset($_REQUEST['itemtype']) && isset($_REQUEST['items_id'])) {
   PluginFieldsField::AjaxForDomContainer($_REQUEST['itemtype'], 
                                          $_REQUEST['items_id'], 
                                          isset($_REQUEST['type'])?$_REQUEST['type']:"dom",
                                          isset($_REQUEST['subtype'])?$_REQUEST['subtype']:"");
}