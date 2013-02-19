<?php

function plugin_fields_install() {
   global $LANG;

   $classesToInstall = array(
      'PluginFieldsField',
      'PluginFieldsContainer',
      'PluginFieldsContainer_Field',
      'PluginFieldsValue',
      'PluginFieldsProfile'
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
      'PluginFieldsField',
      'PluginFieldsContainer',
      'PluginFieldsContainer_Field',
      'PluginFieldsValue',
      'PluginFieldsProfile'
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


function plugin_fields_searchOptionsValues($options=array()) {
   global $LANG;

   $table = $options['searchoption']['table'];
   $field = $options['searchoption']['field'];

   Html::printCleanArray($options);

   switch ($table.".".$field) {
      case "glpi_plugin_fields_containers.type" :
         Dropdown::showFromArray('type', PluginFieldsContainer::getTypes(), 
                                 array('value' => $options['value']));
         return true;
   }
   return false;
}

function plugin_fields_addWhere($link,$nott,$type,$ID,$val) {

   $searchopt = &Search::getOptions($type);
   $table     = $searchopt[$ID]["table"];
   $field     = $searchopt[$ID]["field"];

   Toolbox::logDebug($link,$nott,$type,$ID,$val);

   switch ($table.".".$field) {
      case "glpi_plugin_fields_containers.type" :
         return $link." `$table`.`$field` = '$val' ";
   }
   return "";
}