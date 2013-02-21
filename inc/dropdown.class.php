<?php

class PluginFieldsDropdown {

   static function install(Migration $migration) {
      global $LANG;

      //check if "inc", "front" and "ajax" directories are writeable for httpd
      $directories = array(
         "../plugins/fields/inc",
         "../plugins/fields/front",
         "../plugins/fields/ajax",
      );
      foreach ($directories as $directory) {
         if(!is_writable($directory)) {
            Session::addMessageAfterRedirect($LANG['fields']['error']['dir_write'], false, ERROR);
            return false;
         }
      }

      return true;
   }

   static function uninstall() {
      global $DB;

      //remove dropdown tables and files
      $field = new PluginFieldsField;
      $dropdowns = $field->find("`type` = 'dropdown'");
      foreach ($dropdowns as $dropdown) {
         self::destroy($dropdown['name']);
      }

      return true;
   }
   
   static function create($input) {
      //get template
      $template = file_get_contents(GLPI_ROOT."/plugins/fields/templates/dropdown.class.tpl");
      if ($template === false) return false;

      $classname = "PluginFields".ucfirst($input['name'])."Dropdown";
      $filename = $input['name']."dropdown.class.php";

      //create dropdown class file
      $template = str_replace("%%CLASSNAME%%", $classname, $template);
      $template = str_replace("%%FIELDNAME%%", $input['name'], $template);
      if (file_put_contents(GLPI_ROOT."/plugins/fields/inc/$filename", 
                            $template) === false) return false;

      //call install method (create table)
      $classname::install(); 

      return true;
   }

   static function destroy($dropdown_name) {
      //call uninstall method in dropdown class
      $classname = "PluginFields".ucfirst($dropdown_name)."Dropdown";
      if ($classname::uninstall() === false) return false;

      //remove class file for this dropdown
      $filename = GLPI_ROOT."/plugins/fields/inc/".$dropdown_name."dropdown.class.php";
      return unlink($filename);
   }
}