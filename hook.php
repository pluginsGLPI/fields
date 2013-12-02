<?php

function plugin_fields_install() {
   global $LANG, $CFG_GLPI;

   set_time_limit(900);
   ini_set('memory_limit','2048M');

   $plugin_fields = new Plugin;
   $plugin_fields->getFromDBbyDir('fields');
   $version = $plugin_fields->fields['version'];

   $classesToInstall = array(
      'PluginFieldsDropdown',
      'PluginFieldsField',
      'PluginFieldsContainer',
      'PluginFieldsContainer_Field',
      'PluginFieldsValue',
      'PluginFieldsProfile', 
      'PluginFieldsMigration'   
   );

   $migration = new Migration($version);

   echo "<center>";
   echo "<table class='tab_cadre_fixe'>";
   echo "<tr><th>".$LANG['fields']['install'][0]."<th></tr>";

   echo "<tr class='tab_bg_1'>";
   echo "<td align='center'>";
   foreach ($classesToInstall as $class) {
      if ($plug=isPluginItemType($class)) {
         $dir=$CFG_GLPI['root_doc'] . "/plugins/fields/inc/";
         $item=strtolower($plug['class']);
         if (file_exists("$dir$item.class.php")) {
            include_once ("$dir$item.class.php");
            if (!call_user_func(array($class,'install'), $migration, $version)) return false;
         }
      }
   }

   echo "</td>";
   echo "</tr>";
   echo "</table></center>";

   return true;
}


function plugin_fields_uninstall() {
   global $LANG, $CFG_GLPI;

   $_SESSION['uninstall_fields'] = true;

   $classesToUninstall = array(
      'PluginFieldsDropdown',
      'PluginFieldsContainer',
      'PluginFieldsContainer_Field',
      'PluginFieldsField',
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
         $dir=$CFG_GLPI['root_doc'] . "/plugins/fields/inc/";
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

   unset($_SESSION['uninstall_fields']);

   return true;
}


function plugin_fields_getAddSearchOptions($itemtype) {
   global $LANG;

   if (isset($_SESSION['glpiactiveentities'])) {

      $itemtypes = PluginFieldsContainer::getEntries('all');

      if ($itemtypes !== false && in_array($itemtype, $itemtypes)) {
         return PluginFieldsContainer::getAddSearchOptions($itemtype);
      }
   }

   return null;  
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
      PluginFieldsField::showSingle($options['itemtype'], $options['options'], true);
      return true;
   }

   // Need to return false on non display item
   return false;
}
