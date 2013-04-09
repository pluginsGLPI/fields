<?php

function plugin_fields_install() {
   global $LANG;

   ini_set('memory_limit', '512M');
   set_time_limit(300);

   $classesToInstall = array(
      'PluginFieldsDropdown',
      'PluginFieldsField',
      'PluginFieldsContainer',
      'PluginFieldsContainer_Field',
      'PluginFieldsValue',
      'PluginFieldsProfile', 
      'PluginFieldsMigration'   
   );

   $migration = new Migration("1.0");
   echo "<center>";
   echo "<table class='tab_cadre_fixe'>";
   echo "<tr><th>".$LANG['fields']['install'][0]."<th></tr>";

   echo "<tr class='tab_bg_1'>";
   echo "<td align='center'>";
   foreach ($classesToInstall as $class) {
      if ($plug=isPluginItemType($class)) {
         $dir=GLPI_ROOT . "/plugins/fields/inc/";
         $item=strtolower($plug['class']);
         if (file_exists("$dir$item.class.php")) {
            include_once ("$dir$item.class.php");
            if (!call_user_func(array($class,'install'), $migration)) return false;
         }
      }
   }

   echo "</td>";
   echo "</tr>";
   echo "</table></center>";

   return true;
}


function plugin_fields_uninstall() {
   global $LANG;

   $classesToUninstall = array(
      'PluginFieldsDropdown',
      'PluginFieldsField',
      'PluginFieldsContainer',
      'PluginFieldsContainer_Field',
      'PluginFieldsValue',
      'PluginFieldsProfile', 
      'PluginFieldsMigration' 
   );

   echo "<center>";
   echo "<table class='tab_cadre_fixe'>";
   echo "<tr><th>".$LANG['fields']['uninstall'][0]."<th></tr>";

   echo "<tr class='tab_bg_1'>";
   echo "<td align='center'>";

   foreach ($classesToUninstall as $class) {
      if ($plug=isPluginItemType($class)) {
         $dir=GLPI_ROOT . "/plugins/fields/inc/";
         $item=strtolower($plug['class']);
         if (file_exists("$dir$item.class.php")) {
            include_once ("$dir$item.class.php");
            if(!call_user_func(array($class,'uninstall'))) return false;
         }
      }
   }

   echo "</td>";
   echo "</tr>";
   echo "</table></center>";

   return true;
}


function plugin_fields_getAddSearchOptions($itemtype) {
   global $LANG;

   $itemtypes = PluginFieldsContainer::getEntries('all');

   if (in_array($itemtype, $itemtypes)) {
      return PluginFieldsContainer::getAddSearchOptions($itemtype);
   }

   return null;  
}


function plugin_fields_searchOptionsValues($options=array()) {
   global $LANG;

   $table = $options['searchoption']['table'];
   $field = $options['searchoption']['field'];

   switch ($table.".".$field) {
      case "glpi_plugin_fields_containers.type" :
         Dropdown::showFromArray('type', PluginFieldsContainer::getTypes(), 
                                 array('value' => $options['value']));
         return true;
   }
   return false;
}

function plugin_fields_addWhere($link,$nott,$type,$ID,$val, $searchtype) {

   $searchopt = &Search::getOptions($type);
   $table     = $searchopt[$ID]["table"];
   $field     = $searchopt[$ID]["field"];
   
   switch ($table.".".$field) {
      case "glpi_plugin_fields_containers.type" :
         return $link." `$table`.`$field` = '$val' ";
   }

   //for itemtype search options
   if (!isset($_SESSION['pass_addwhere_fields'])) {
      if ($table === "glpi_plugin_fields_values") {
         $_SESSION['pass_addwhere_fields'] = true;
         
         $condition = $searchopt[$ID]["condition"];
         $where     = Search::addWhere($link, $nott, $type, $ID, $searchtype, $val);
         unset($_SESSION['pass_addwhere_fields']);
         return "$condition AND $where";

      } elseif (preg_match("/glpi_plugin_fields_.*dropdowns/", $table)) {
         //dropdown search

         $_SESSION['pass_addwhere_fields'] = true;

         $linkfield = str_replace(array("plugin_fields_", "dropdowns_id"), "", 
                                  $searchopt[$ID]['linkfield']);
         
         $where     = Search::addWhere($link, $nott, $type, $ID, $searchtype, $val);
         unset($_SESSION['pass_addwhere_fields']);
         return "$where AND `glpi_plugin_fields_fields`.`name` = '$linkfield'";
      }
   }

   unset($_SESSION['pass_addwhere_fields']);

   return "";
}

function plugin_fields_addLeftJoin($type, $ref_table, $new_table, $linkfield) {

   //for itemtype search options
   if ($new_table === "glpi_plugin_fields_values") {

      return " LEFT JOIN `$new_table` 
         ON (`$ref_table`.`id` = `$new_table`.`items_id` AND `$new_table`.`itemtype` = '$type')
      LEFT JOIN `glpi_plugin_fields_fields` 
         ON (`$new_table`.`plugin_fields_fields_id` = `glpi_plugin_fields_fields`.`id`) ";

   } elseif (preg_match("/glpi_plugin_fields_.*dropdowns/", $new_table)) {

      return " LEFT JOIN `glpi_plugin_fields_values`
         ON (`$ref_table`.`id` = `glpi_plugin_fields_values`.`items_id` 
            AND `glpi_plugin_fields_values`.`itemtype` = '$type')
      LEFT JOIN `glpi_plugin_fields_fields`
         ON (`glpi_plugin_fields_fields`.`id` 
                                    = `glpi_plugin_fields_values`.`plugin_fields_fields_id`)
      LEFT JOIN `$new_table`
         ON (`$new_table`.`id` = `glpi_plugin_fields_values`.`value_int`)";
   }
   return "";
}

// Define Dropdown tables to be manage in GLPI :
function plugin_fields_getDropdown() {
   $dropdowns = array();

   $field_obj = new PluginFieldsField;
   $fields = $field_obj->find("`type` = 'dropdown'");
   foreach ($fields as $field) {
      $dropdowns["PluginFields".ucfirst($field['name'])."Dropdown"] = $field['label'];
   }

   return $dropdowns;
}


/**** MASSIVE ACTIONS ****/


// Display specific massive actions for plugin fields
function plugin_fields_MassiveActionsFieldsDisplay($options=array()) {
   $itemtypes = PluginFieldsContainer::getEntries('all');

   if (in_array($options['itemtype'], $itemtypes)) {
      PluginFieldsField::showSingle($options['itemtype'], $options['options']);
      return true;
   }

   // Need to return false on non display item
   return false;
}
